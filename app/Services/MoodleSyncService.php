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
            'instructor_roles' => $this->syncInstructorRoles(),
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

                    if ($portalUser) {
                        // Update existing user
                        
                        // PROTECT MANUAL/ERP DATA: Jangan overwrite nama/email jika user created di Portal/ERP
                        if (in_array($portalUser->source, ['manual', 'erp'])) {
                            $portalUser->update([
                                'moodle_user_id' => $mUser->id,
                                'is_active' => true,
                            ]);
                            $this->log('debug', "Linked Moodle ID for {$portalUser->source} user: {$mUser->email} (Name preserved)");
                        } else {
                            // Full Sync untuk user yang memang source-nya dari Moodle (atau null/legacy)
                            $portalUser->update([
                                'moodle_user_id' => $mUser->id,
                                'name' => trim($mUser->firstname . ' ' . $mUser->lastname),
                                'email' => $mUser->email, // Sync email if changed in Moodle
                                'is_active' => true,
                            ]);
                            $this->log('debug', "Updated user from Moodle: {$mUser->email}");
                        }

                        $updated++;
                    } else {
                        // Create new user (assign default password, akan di-reset via email)
                        $userData = [
                            'name' => trim($mUser->firstname . ' ' . $mUser->lastname),
                            'email' => $mUser->email,
                            'moodle_user_id' => $mUser->id,
                            'is_active' => true,
                            'password' => bcrypt('password123'), // Temporary
                        ];
                        $newUser = User::create($userData);

                        // Assign default role 'learner' (peserta)
                        $newUser->assignRole('learner');

                        $added++;
                        $this->log('debug', "Created new user: {$mUser->email}");
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->log('error', "Failed to sync user {$mUser->email}: " . $e->getMessage());
                }
            }

            // Deactivate users that are suspended in Moodle
            $suspendedEmails = DB::connection('moodle')
                ->table('user')
                ->where('deleted', 0)
                ->whereNotIn('id', [1, 2])
                ->where('suspended', 1)
                ->pluck('email')
                ->toArray();

            foreach ($suspendedEmails as $email) {
                $portalUser = User::where('email', $email)->first();
                if ($portalUser && !$portalUser->hasAnyRole(['super-admin', 'admin'])) {
                    $portalUser->update(['is_active' => false]);
                    $this->log('debug', "Deactivated suspended Moodle user: {$email}");
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
                'suspended_deactivated' => count($suspendedEmails),
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
            $suspended = 0;

            // 1. Get all active enrollments from Portal (to track what should be kept)
            // Format: 'user_id-course_id' => enrollment_id
            $existingPortalEnrollments = CourseEnrollment::where('status', 'active')
                ->get()
                ->mapWithKeys(function ($item) {
                    return ["{$item->user_id}-{$item->course_id}" => $item->id];
                })
                ->toArray();
            
            $seenEnrollmentKeys = [];

            // 2. Get all active enrollments from Moodle
            // Join: user_enrolments -> enrol -> course -> user
            $moodleEnrollments = DB::connection('moodle')
                ->table('user_enrolments as ue')
                ->join('enrol as e', 'ue.enrolid', '=', 'e.id')
                ->join('course as c', 'e.courseid', '=', 'c.id')
                ->join('user as u', 'ue.userid', '=', 'u.id')
                ->where('u.deleted', 0)
                ->where('u.suspended', 0)
                ->where('c.id', '!=', 1)
                ->select(
                    'ue.id as enrolment_id',
                    'u.id as moodle_user_id',
                    'u.email',
                    'c.id as moodle_course_id',
                    'c.fullname as course_name',
                    'ue.timecreated',
                    'ue.timemodified',
                    'ue.status'
                )
                ->get();

            $this->log('info', "Found {$moodleEnrollments->count()} enrollments in Moodle");

            foreach ($moodleEnrollments as $mEnroll) {
                try {
                    // Find Portal user by email (Preferred) or moodle_user_id
                    $portalUser = User::where('email', $mEnroll->email)->first();
                    if (!$portalUser) {
                        // Try by moodle_id as fallback
                        $portalUser = User::where('moodle_user_id', $mEnroll->moodle_user_id)->first();
                    }

                    if (!$portalUser) {
                        $this->log('warning', "User not found in Portal: {$mEnroll->email}");
                        continue;
                    }

                    // Find Portal course by moodle_course_id
                    $portalCourse = Course::where('moodle_course_id', $mEnroll->moodle_course_id)->first();
                    if (!$portalCourse) {
                        $this->log('warning', "Course not found in Portal: Moodle ID {$mEnroll->moodle_course_id}");
                        continue;
                    }

                    // Generate Unique Key for checking
                    $key = "{$portalUser->id}-{$portalCourse->id}";
                    $seenEnrollmentKeys[] = $key;

                    // Check if enrollment exists
                    $existingEnrollment = CourseEnrollment::where('user_id', $portalUser->id)
                        ->where('course_id', $portalCourse->id)
                        ->first();

                    $enrollmentData = [
                        'user_id' => $portalUser->id,
                        'course_id' => $portalCourse->id,
                        'enrolled_at' => Carbon::createFromTimestamp($mEnroll->timecreated),
                        'status' => $mEnroll->status == 0 ? 'active' : 'suspended',
                    ];

                    if ($existingEnrollment) {
                        // Only update if changes detected to save DB hits? 
                        // For now just update to ensure consistency
                        $existingEnrollment->update($enrollmentData);
                        $updated++;
                    } else {
                        CourseEnrollment::create($enrollmentData);
                        $added++;
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->log('error', "Failed to sync enrollment {$mEnroll->enrolment_id}: " . $e->getMessage());
                }
            }

            // 3. Suspend Portal enrollments that are NOT in Moodle anymore
            // If it was active locally, but not found in the Moodle list -> It's deleted/unenrolled in Moodle.
            $keysToSuspend = array_diff(array_keys($existingPortalEnrollments), $seenEnrollmentKeys);

            if (!empty($keysToSuspend)) {
                $idsToSuspend = [];
                foreach ($keysToSuspend as $key) {
                    $idsToSuspend[] = $existingPortalEnrollments[$key];
                }

                CourseEnrollment::whereIn('id', $idsToSuspend)
                    ->update(['status' => 'suspended']);
                
                $suspended = count($idsToSuspend);
                $this->log('info', "Suspended {$suspended} enrollments that are missing in Moodle.");
            }

            DB::commit();

            $duration = round(microtime(true) - $startTime, 2);
            $result = [
                'total_moodle' => $moodleEnrollments->count(),
                'added' => $added,
                'updated' => $updated,
                'suspended_local' => $suspended,
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
     * SYNC INSTRUCTOR ROLES dari Moodle role_assignments
     * Reads Moodle teacher assignments → updates courses.instructor_id + portal user roles
     * Moodle role IDs: 3 = editingteacher, 4 = teacher, 5 = student, 1 = manager/admin
     */
    public function syncInstructorRoles(): array
    {
        $this->log('info', 'Starting Instructor Role Sync');
        $startTime = microtime(true);

        try {
            $updated = 0;
            $errors = 0;

            // Get all teacher-role assignments in Moodle (course context = contextlevel 50)
            $teacherAssignments = DB::connection('moodle')
                ->table('role_assignments as ra')
                ->join('context as ctx', 'ra.contextid', '=', 'ctx.id')
                ->join('user as u', 'ra.userid', '=', 'u.id')
                ->where('ctx.contextlevel', 50) // Course context
                ->whereIn('ra.roleid', [3, 4])   // 3 = editingteacher, 4 = teacher
                ->where('u.deleted', 0)
                ->where('u.suspended', 0)
                ->select(
                    'u.email',
                    'u.id as moodle_user_id',
                    'ctx.instanceid as moodle_course_id',
                    'ra.roleid'
                )
                ->get();

            $this->log('info', "Found {$teacherAssignments->count()} teacher assignments in Moodle");

            // Build priority map per course: prefer editingteacher (roleid=3), skip suspended
            // For each course, pick ONE instructor to display — skip admin/super-admin portal users
            $preferredByCourse = [];
            foreach ($teacherAssignments as $assignment) {
                $portalUserCheck = User::where('email', $assignment->email)->first();
                // Admin/super-admin cannot be shown as course instructor
                if ($portalUserCheck && $portalUserCheck->hasAnyRole(['super-admin', 'admin'])) {
                    continue;
                }
                $courseId = $assignment->moodle_course_id;
                // Prefer editingteacher (3) over non-editing teacher (4)
                if (!isset($preferredByCourse[$courseId]) || (int)$assignment->roleid === 3) {
                    $preferredByCourse[$courseId] = $assignment;
                }
            }

            foreach ($preferredByCourse as $moodleCourseId => $assignment) {
                try {
                    // Find portal user
                    $portalUser = User::where('email', $assignment->email)->first();
                    if (!$portalUser) continue;

                    // Find portal course
                    $portalCourse = Course::where('moodle_course_id', $moodleCourseId)->first();
                    if (!$portalCourse) continue;

                    // Skip admin/super-admin: they are never shown as course instructor
                    if ($portalUser->hasAnyRole(['super-admin', 'admin'])) {
                        $this->log('debug', "Skipped instructor_id for admin user: {$assignment->email}");
                        continue;
                    }

                    // Promote role: learner → instructor (only for non-admin users)
                    if ($portalUser->hasRole('learner')) {
                        $portalUser->removeRole('learner');
                        $portalUser->assignRole('instructor');
                        $this->log('debug', "Promoted to instructor: {$assignment->email}");
                    }

                    // Set course instructor_id
                    if ($portalCourse->instructor_id !== $portalUser->id) {
                        $portalCourse->update(['instructor_id' => $portalUser->id]);
                        $this->log('debug', "Set instructor for course {$portalCourse->title}: {$assignment->email}");
                    }

                    $updated++;
                } catch (\Exception $e) {
                    $errors++;
                    $this->log('error', "Failed instructor sync for {$assignment->email}: " . $e->getMessage());
                }
            }

            // Clear instructor_id from courses whose instructor is no longer a teacher in Moodle
            // Build per-course SET of teacher emails (multiple teachers allowed per course)
            $teachersByCourse = [];
            foreach ($teacherAssignments as $ta) {
                $teachersByCourse[$ta->moodle_course_id][] = $ta->email;
            }
            $cleared = 0;

            $coursesWithInstructor = Course::whereNotNull('instructor_id')
                ->whereNotNull('moodle_course_id')
                ->with('instructor')
                ->get();
            foreach ($coursesWithInstructor as $course) {
                if (!$course->instructor) continue;

                // Clear if current instructor is admin/super-admin (should never be set as instructor)
                if ($course->instructor->hasAnyRole(['super-admin', 'admin'])) {
                    $course->update(['instructor_id' => null]);
                    $this->log('debug', "Cleared instructor from {$course->title}: was admin user ({$course->instructor->email})");
                    $cleared++;
                    continue;
                }

                $moodleTeachers = $teachersByCourse[$course->moodle_course_id] ?? [];

                // Clear if instructor is not among the Moodle teachers for this course
                if (!in_array($course->instructor->email, $moodleTeachers)) {
                    $course->update(['instructor_id' => null]);
                    $this->log('debug', "Cleared instructor from {$course->title}: no longer a teacher in Moodle");
                    $cleared++;
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            $result = [
                'total_assignments' => $teacherAssignments->count(),
                'updated' => $updated,
                'cleared' => $cleared,
                'errors' => $errors,
                'duration_seconds' => $duration,
            ];

            $this->log('info', "Instructor Role Sync completed: " . json_encode($result));
            return $result;

        } catch (\Exception $e) {
            $this->log('error', 'Instructor Role Sync failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
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

            $userCount = DB::connection('moodle')
                ->table('user')
                ->where('deleted', 0)
                ->where('suspended', 0)
                ->whereNotIn('id', [1, 2]) // Exclude guest and admin to match syncUsers
                ->where('username', '!=', 'guest')
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
