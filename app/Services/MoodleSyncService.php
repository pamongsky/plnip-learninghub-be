<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Carbon\Carbon;

class MoodleSyncService
{
    protected $syncLog = [];
    protected $startTime;

    public function __construct()
    {
        $this->startTime = now();
    }

    /**
     * FULL SYNC - Sync semua data dari Moodle
     */
    public function fullSync(): array
    {
        $this->log('info', 'Starting Full Sync from Moodle');

        $results = [
            'started_at' => $this->startTime->toDateTimeString(),
            'users' => $this->syncUsers(),
            'courses' => $this->syncCourses(),
            'enrollments' => $this->syncEnrollments(),
            'categories' => $this->syncCategories(),
            'completed_at' => now()->toDateTimeString(),
            'duration' => now()->diffInSeconds($this->startTime),
            'logs' => $this->syncLog,
        ];

        $this->log('info', 'Full Sync completed successfully');
        return $results;
    }

    /**
     * SYNC USERS dari Moodle
     * Strategy: Direct DB Read Oracle Moodle -> Sync ke Portal Oracle
     */
    public function syncUsers(): array
    {
        $this->log('info', 'Starting User Sync');
        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            $added = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            // Get all active users dari Moodle (exclude deleted, admin, guest)
            $moodleUsers = DB::connection('moodle')
                ->table('user')
                ->where('deleted', 0)
                ->whereNotIn('id', [1, 2]) // Skip admin & guest
                ->where('suspended', 0)
                ->where('username', '!=', 'guest')
                ->get();

            $this->log('info', "Found {$moodleUsers->count()} users in Moodle");

            foreach ($moodleUsers as $mUser) {
                try {
                    // Check if user exists in Portal by email
                    $portalUser = User::where('email', $mUser->email)->first();

                    $userData = [
                        'name' => trim($mUser->firstname . ' ' . $mUser->lastname),
                        'email' => $mUser->email,
                        'moodle_user_id' => $mUser->id,
                        'is_active' => true,
                    ];

                    if ($portalUser) {
                        // Update existing user
                        $portalUser->update($userData);
                        $updated++;
                        $this->log('debug', "Updated user: {$mUser->email}");
                    } else {
                        // Create new user (assign default password, akan di-reset via email)
                        $userData['password'] = bcrypt('password123'); // Temporary
                        $newUser = User::create($userData);

                        // Assign default role 'user' (peserta)
                        $newUser->assignRole('user');

                        $added++;
                        $this->log('debug', "Created new user: {$mUser->email}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->log('error', "Failed to sync user {$mUser->email}: " . $e->getMessage());
                }
            }

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);
            $result = [
                'total_moodle' => $moodleUsers->count(),
                'added' => $added,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'duration_seconds' => $duration,
            ];

            $this->log('info', "User Sync completed: " . json_encode($result));
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->log('error', 'User Sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * SYNC COURSES dari Moodle
     */
    public function syncCourses(): array
    {
        $this->log('info', 'Starting Course Sync');
        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            $added = 0;
            $updated = 0;
            $errors = 0;

            // Get all courses dari Moodle (exclude site root)
            $moodleCourses = DB::connection('moodle')
                ->table('course')
                ->where('id', '!=', 1)
                ->get();

            $this->log('info', "Found {$moodleCourses->count()} courses in Moodle");

            foreach ($moodleCourses as $mCourse) {
                try {
                    $existingCourse = Course::where('moodle_course_id', $mCourse->id)->first();

                    $courseData = [
                        'title' => $mCourse->fullname ?? 'Untitled',
                        'short_name' => $mCourse->shortname ?? 'NO-CODE',
                        'description' => strip_tags($mCourse->summary ?? ''),
                        'category_id' => $mCourse->category ?? 1,
                        'start_date' => ($mCourse->startdate > 0) ? Carbon::createFromTimestamp($mCourse->startdate)->format('Y-m-d') : null,
                        'end_date' => ($mCourse->enddate > 0) ? Carbon::createFromTimestamp($mCourse->enddate)->format('Y-m-d') : null,
                        'is_active' => ($mCourse->visible == 1),
                        'moodle_course_id' => $mCourse->id,
                    ];

                    if ($existingCourse) {
                        $existingCourse->update($courseData);
                        $updated++;
                    } else {
                        Course::create($courseData);
                        $added++;
                    }

                    $this->log('debug', "Synced course: {$mCourse->fullname}");

                } catch (\Exception $e) {
                    $errors++;
                    $this->log('error', "Failed to sync course {$mCourse->id}: " . $e->getMessage());
                }
            }

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);
            $result = [
                'total_moodle' => $moodleCourses->count(),
                'added' => $added,
                'updated' => $updated,
                'errors' => $errors,
                'duration_seconds' => $duration,
            ];

            $this->log('info', "Course Sync completed: " . json_encode($result));
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->log('error', 'Course Sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * SYNC ENROLLMENTS dari Moodle
     */
    public function syncEnrollments(): array
    {
        $this->log('info', 'Starting Enrollment Sync');
        $startTime = microtime(true);

        try {
            DB::beginTransaction();

            $added = 0;
            $updated = 0;
            $errors = 0;

            // Get all active enrollments dari Moodle
            // Join: user_enrolments -> enrol -> course -> user -> role_assignments
            $moodleEnrollments = DB::connection('moodle')
                ->table('user_enrolments as ue')
                ->join('enrol as e', 'ue.enrolid', '=', 'e.id')
                ->join('course as c', 'e.courseid', '=', 'c.id')
                ->join('user as u', 'ue.userid', '=', 'u.id')
                ->leftJoin('context as ctx', function($join) {
                    $join->on('ctx.instanceid', '=', 'c.id')
                         ->where('ctx.contextlevel', '=', 50); // Course context
                })
                ->leftJoin('role_assignments as ra', function($join) {
                    $join->on('ra.userid', '=', 'u.id')
                         ->on('ra.contextid', '=', 'ctx.id');
                })
                ->where('u.deleted', 0)
                ->where('u.suspended', 0)
                ->where('c.id', '!=', 1)
                ->select(
                    'ue.id as enrolment_id',
                    'u.id as user_id',
                    'u.email',
                    'c.id as course_id',
                    'c.fullname as course_name',
                    'ue.timecreated',
                    'ue.timemodified',
                    'ue.status',
                    'ra.roleid as moodle_role_id'
                )
                ->get();

            $this->log('info', "Found {$moodleEnrollments->count()} enrollments in Moodle");

            foreach ($moodleEnrollments as $mEnroll) {
                try {
                    // Find Portal user by email
                    $portalUser = User::where('email', $mEnroll->email)->first();
                    if (!$portalUser) {
                        $this->log('warning', "User not found in Portal: {$mEnroll->email}");
                        continue;
                    }

                    // Find Portal course by moodle_course_id
                    $portalCourse = Course::where('moodle_course_id', $mEnroll->course_id)->first();
                    if (!$portalCourse) {
                        $this->log('warning', "Course not found in Portal: Moodle ID {$mEnroll->course_id}");
                        continue;
                    }

                    // Check if enrollment exists
                    $existingEnrollment = CourseEnrollment::where('user_id', $portalUser->id)
                        ->where('course_id', $portalCourse->id)
                        ->first();

                    $enrollmentData = [
                        'user_id' => $portalUser->id,
                        'course_id' => $portalCourse->id,
                        'moodle_role_id' => $mEnroll->moodle_role_id ?? 5, // Default to student if role not found
                        'enrolled_at' => Carbon::createFromTimestamp($mEnroll->timecreated),
                        'status' => $mEnroll->status == 0 ? 'active' : 'suspended',
                    ];

                    if ($existingEnrollment) {
                        $existingEnrollment->update($enrollmentData);
                        $updated++;
                    } else {
                        CourseEnrollment::create($enrollmentData);
                        $added++;
                    }

                    // Auto-assign instructor_id if role is teacher (3 or 4) and course has no instructor
                    if (in_array($mEnroll->moodle_role_id, [3, 4]) && !$portalCourse->instructor_id) {
                        $portalCourse->update(['instructor_id' => $portalUser->id]);
                        $this->log('info', "Auto-assigned instructor {$portalUser->name} to course {$portalCourse->title}");
                    }

                    $this->log('debug', "Synced enrollment: {$mEnroll->email} -> {$mEnroll->course_name}");

                } catch (\Exception $e) {
                    $errors++;
                    $this->log('error', "Failed to sync enrollment {$mEnroll->enrolment_id}: " . $e->getMessage());
                }
            }

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);
            $result = [
                'total_moodle' => $moodleEnrollments->count(),
                'added' => $added,
                'updated' => $updated,
                'errors' => $errors,
                'duration_seconds' => $duration,
            ];

            $this->log('info', "Enrollment Sync completed: " . json_encode($result));
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->log('error', 'Enrollment Sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * SYNC CATEGORIES dari Moodle
     */
    public function syncCategories(): array
    {
        $this->log('info', 'Starting Category Sync');
        $startTime = microtime(true);

        try {
            // Get course categories dari Moodle
            $moodleCategories = DB::connection('moodle')
                ->table('course_categories')
                ->get();

            $this->log('info', "Found {$moodleCategories->count()} categories in Moodle");

            // For now, just log categories
            // TODO: Jika ada table categories di Portal, sync ke sana
            foreach ($moodleCategories as $cat) {
                $this->log('debug', "Category: {$cat->name} (ID: {$cat->id})");
            }

            $duration = round(microtime(true) - $startTime, 2);

            return [
                'total_moodle' => $moodleCategories->count(),
                'note' => 'Category table not yet implemented in Portal',
                'duration_seconds' => $duration,
            ];

        } catch (\Exception $e) {
            $this->log('error', 'Category Sync failed: ' . $e->getMessage());
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Moodle Connection Status
     */
    public function getConnectionStatus(): array
    {
        try {
            // Test connection
            $result = DB::connection('moodle')->select('SELECT 1 FROM DUAL');

            // Get Moodle version
            $config = DB::connection('moodle')
                ->table('config')
                ->where('name', 'version')
                ->first();

            // Get counts
            $userCount = DB::connection('moodle')
                ->table('user')
                ->where('deleted', 0)
                ->where('suspended', 0)
                ->count();

            $courseCount = DB::connection('moodle')
                ->table('course')
                ->where('id', '!=', 1)
                ->count();

            return [
                'status' => 'connected',
                'moodle_version' => $config->value ?? 'Unknown',
                'total_users' => $userCount,
                'total_courses' => $courseCount,
                'database' => config('database.connections.moodle.database'),
                'host' => config('database.connections.moodle.host'),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Sync Statistics
     */
    public function getSyncStats(): array
    {
        return [
            'portal_users' => User::count(),
            'portal_courses' => Course::count(),
            'portal_enrollments' => CourseEnrollment::count(),
            'synced_users' => User::whereNotNull('moodle_user_id')->count(),
            'synced_courses' => Course::whereNotNull('moodle_course_id')->count(),
        ];
    }

    /**
     * Log helper
     */
    private function log(string $level, string $message): void
    {
        $this->syncLog[] = [
            'timestamp' => now()->toDateTimeString(),
            'level' => $level,
            'message' => $message,
        ];

        Log::$level("[MoodleSync] $message");
    }
}
