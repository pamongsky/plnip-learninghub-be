<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Services\MoodleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    protected $moodleService;

    public function __construct(MoodleService $moodleService)
    {
        $this->moodleService = $moodleService;
    }

    /**
     * List all courses with enrollment counts
     */
    public function index(Request $request)
    {
        $courses = Course::withCount('enrollments')
            ->with('instructor:id,name,avatar')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($courses);
    }

    /**
     * Get courses enrolled by the current user
     */
    public function myCourses(Request $request)
    {
        $user = $request->user();

        $courses = $user->courses()
            ->with('instructor:id,name,avatar')
            ->withCount('enrollments as participants_count')
            ->wherePivot('status', 'active')
            ->orderBy('course_enrollments.created_at', 'desc')
            ->get()
            ->map(function ($course) {
                // Determine Moodle URL for direct access
                // Format: http://moodle-url/course/view.php?id=MOODLE_COURSE_ID
                $moodleBase = config('services.moodle.url', env('MOODLE_URL'));
                $course->moodle_url = $course->moodle_course_id
                    ? "{$moodleBase}/course/view.php?id={$course->moodle_course_id}"
                    : null;
                return $course;
            });

        return response()->json([
            'data' => $courses
        ]);
    }

    /**
     * Sync courses from Moodle
     */
    /**
     * Sync courses from Moodle (Direct DB Strategy)
     */
    public function sync(Request $request)
    {
        // Permission check: Super admin dan Admin bisa sync
        if (!$request->user() || !$request->user()->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Hanya admin yang bisa sync courses dari Moodle'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $syncedCount = 0;

            // 1. Get from Moodle DB Directly (Oracle)
            // Table: mdl_course
            // Exclude id 1 (Site root)
            $moodleCourses = DB::connection('moodle')->table('course')
                ->where('id', '!=', 1)
                ->get();

            foreach ($moodleCourses as $mCourse) {
                // Map fields
                // Oracle usually returns lowercase keys if configured, but check case
                // We assume standard Laravel-OCI behavior (lowercase)

                Course::updateOrCreate(
                    ['moodle_course_id' => $mCourse->id],
                    [
                        'title' => $mCourse->fullname ?? 'Untitled',
                        'short_name' => $mCourse->shortname ?? 'No Code',
                        'description' => strip_tags($mCourse->summary ?? ''),
                        'category_id' => $mCourse->category ?? 1,
                        'start_date' => ($mCourse->startdate > 0) ? date('Y-m-d', $mCourse->startdate) : null,
                        'end_date' => ($mCourse->enddate > 0) ? date('Y-m-d', $mCourse->enddate) : null,
                        'is_active' => ($mCourse->visible == 1),
                    ]
                );
                $syncedCount++;
            }

            // 2. Deactivate courses in Portal that are deleted/hidden in Moodle?
            // For now, let's just sync additions/updates.
            // Full sync might require checking diffs.

            DB::commit();

            return response()->json([
                'message' => "Berhasil sinkronisasi $syncedCount kelas dari Moodle (Direct DB)",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Course Sync Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal sinkronisasi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get course details with enrollments
     */
    public function show($id)
    {
        $course = Course::with([
            'instructor:id,name,avatar',
            'enrollments.user:id,name,email,avatar,department',
            'students',
        ])->findOrFail($id);

        // Enrich enrollment data with Moodle progress and activity
        if ($course->moodle_course_id && $course->enrollments) {
            try {
                $moodleConn = DB::connection('moodle');

                foreach ($course->enrollments as $enrollment) {
                    $user = $enrollment->user;

                    // Get Moodle user
                    $moodleUser = $moodleConn->table('user')
                        ->where('email', $user->email)
                        ->first();

                    if ($moodleUser) {
                        // Get course completion progress
                        $completion = $moodleConn->table('course_completions')
                            ->where('userid', $moodleUser->id)
                            ->where('course', $course->moodle_course_id)
                            ->first();

                        if ($completion && $completion->timecompleted) {
                            $enrollment->progress = 100;
                        } else {
                            // Calculate progress from completed activities
                            $totalActivities = $moodleConn->table('course_modules as cm')
                                ->join('modules as m', 'cm.module', '=', 'm.id')
                                ->where('cm.course', $course->moodle_course_id)
                                ->where('cm.visible', 1)
                                ->where('cm.completion', '>', 0)
                                ->count();

                            if ($totalActivities > 0) {
                                $completedActivities = $moodleConn->table('course_modules_completion')
                                    ->join('course_modules as cm', 'course_modules_completion.coursemoduleid', '=', 'cm.id')
                                    ->where('cm.course', $course->moodle_course_id)
                                    ->where('course_modules_completion.userid', $moodleUser->id)
                                    ->where('course_modules_completion.completionstate', '>', 0)
                                    ->count();

                                $enrollment->progress = round(($completedActivities / $totalActivities) * 100);
                            } else {
                                $enrollment->progress = 0;
                            }
                        }

                        // Get last activity
                        $lastActivity = $moodleConn->table('logstore_standard_log')
                            ->where('userid', $moodleUser->id)
                            ->where('courseid', $course->moodle_course_id)
                            ->orderBy('timecreated', 'desc')
                            ->first();

                        if ($lastActivity) {
                            $enrollment->last_activity_at = date('Y-m-d H:i:s', $lastActivity->timecreated);
                        } else {
                            $enrollment->last_activity_at = null;
                        }
                    } else {
                        $enrollment->progress = 0;
                        $enrollment->last_activity_at = null;
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error fetching Moodle progress data: ' . $e->getMessage());
                // Continue without enrichment
            }
        }

        return response()->json($course);
    }

    /**
     * Update course details
     */
    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
            'instructor_id' => 'nullable|exists:users,id',
        ]);

        // Note: For now we only update local DB.
        // Moodle update logic can be added later if needed.
        $course->update($validated);

        return response()->json([
            'message' => 'Kelas berhasil diperbarui',
            'data' => $course
        ]);
    }

    /**
     * Enroll a user to the course
     */
    public function enrollUser(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'integer' // 5 = student, 3 = teacher/instructor
        ]);

        // Permission check: Admin bisa enroll user
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Hanya admin yang bisa enroll user ke course'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $course = Course::findOrFail($id);
            $user = User::findOrFail($request->user_id);

            // Check already enrolled locally (only active enrollments)
            $existingEnrollment = CourseEnrollment::where('course_id', $course->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingEnrollment) {
                if ($existingEnrollment->status === 'active') {
                    return response()->json(['message' => 'User sudah terdaftar di kelas ini'], 422);
                }
                // If suspended, we'll reactivate it later
            }

            // --- MOODLE DIRECT DB SYNC ---
            if ($course->moodle_course_id) {
                $moodleConn = DB::connection('moodle');

                // 1. Find or CREATE Moodle User (Hybrid System)
                $moodleUser = $moodleConn->table('user')->where('email', $user->email)->first();

                if (!$moodleUser) {
                    // Auto-create user in Moodle if not exists
                    Log::info("Creating Moodle user for: {$user->email}");

                    $moodleUserId = $moodleConn->table('user')->insertGetId([
                        'auth' => 'manual',
                        'confirmed' => 1,
                        'username' => strtolower(str_replace(['@', '.'], ['_', '_'], $user->email)), // email tanpa @ dan .
                        'password' => password_hash($user->employee_id ?? 'password123', PASSWORD_BCRYPT), // temporary password
                        'firstname' => explode(' ', $user->name)[0] ?? $user->name,
                        'lastname' => substr($user->name, strpos($user->name, ' ') + 1) ?: '-',
                        'email' => $user->email,
                        'mnethostid' => 1,
                        'timecreated' => now()->timestamp,
                        'timemodified' => now()->timestamp,
                    ]);

                    $moodleUser = $moodleConn->table('user')->where('id', $moodleUserId)->first();
                    Log::info("Moodle user created with ID: {$moodleUserId}");
                }

                // Sync moodle_user_id back to local user record
                if (!$user->moodle_user_id && $moodleUser) {
                    $user->update(['moodle_user_id' => $moodleUser->id]);
                    Log::info("Synced moodle_user_id {$moodleUser->id} to local user {$user->email}");
                }

                // 2. Find Course Context (contextlevel = 50 for Course)
                $context = $moodleConn->table('context')
                    ->where('contextlevel', 50)
                    ->where('instanceid', $course->moodle_course_id)
                    ->first();

                if (!$context) {
                    // Try to generate? No, simpler to fail.
                     throw new \Exception("Moodle Context not found for Course ID: {$course->moodle_course_id}");
                }

                // 3. Find 'manual' enrol instance for this course
                $enrolInstance = $moodleConn->table('enrol')
                    ->where('courseid', $course->moodle_course_id)
                    ->where('enrol', 'manual')
                    ->first();

                if (!$enrolInstance) {
                     // Create Manual Instance if missing? (Advanced, let's assume it exists)
                     throw new \Exception("Manual Enrollment method not enabled for this course in Moodle.");
                }

                // 4. Insert or UPDATE mdl_user_enrolments (The "Join" action)
                // Check if already exists first
                $existingEnrol = $moodleConn->table('user_enrolments')
                    ->where('enrolid', $enrolInstance->id)
                    ->where('userid', $moodleUser->id)
                    ->first();

                if ($existingEnrol) {
                    // Reactivate if suspended (status = 1 means suspended)
                    if ($existingEnrol->status == 1) {
                        $moodleConn->table('user_enrolments')
                            ->where('id', $existingEnrol->id)
                            ->update([
                                'status' => 0, // 0 = Active
                                'timemodified' => now()->timestamp,
                            ]);
                    }
                } else {
                    // Create new enrollment
                    $moodleConn->table('user_enrolments')->insert([
                        'status' => 0, // 0 = Active
                        'enrolid' => $enrolInstance->id,
                        'userid' => $moodleUser->id,
                        'timestart' => now()->timestamp,
                        'timeend' => 0,
                        'modifierid' => 2, // Admin ID usually
                        'timecreated' => now()->timestamp,
                        'timemodified' => now()->timestamp,
                    ]);
                }

                // 5. Insert into mdl_role_assignments (The "Student" label)
                // Role ID mapping:
                // 1 = Manager (Super Admin)
                // 2 = Course Creator (Admin)
                // 3 = Non-Editing Teacher
                // 4 = Editing Teacher
                // 5 = Student

                // Auto-map Portal role to Moodle role if not explicitly provided
                $roleId = $request->input('role_id');

                if (!$roleId) {
                    // Check user's Portal role and auto-map
                    if ($user->hasRole('super_admin')) {
                        $roleId = 1; // Manager in Moodle
                    } elseif ($user->hasRole('admin')) {
                        $roleId = 2; // Course Creator in Moodle
                    } elseif ($user->hasRole('instructor')) {
                        $roleId = 4; // Editing Teacher in Moodle
                    } else {
                        $roleId = 5; // Student (default)
                    }
                }

                $existingRole = $moodleConn->table('role_assignments')
                    ->where('contextid', $context->id)
                    ->where('userid', $moodleUser->id)
                    ->where('roleid', $roleId)
                    ->first();

                if (!$existingRole) {
                    $moodleConn->table('role_assignments')->insert([
                        'roleid' => $roleId,
                        'contextid' => $context->id,
                        'userid' => $moodleUser->id,
                        'timemodified' => now()->timestamp,
                        'modifierid' => 2,
                        'itemid' => 0,
                        'sortorder' => 0
                    ]);
                }
            }

            // --- LOCAL UPDATE ---
            // Reactivate if suspended, or create new if doesn't exist
            if ($existingEnrollment) {
                $existingEnrollment->update([
                    'status' => 'active',
                    'moodle_role_id' => $roleId,
                    'enrolled_at' => now(),
                ]);
                $enrollment = $existingEnrollment;
            } else {
                $enrollment = CourseEnrollment::create([
                    'course_id' => $course->id,
                    'user_id' => $user->id,
                    'moodle_role_id' => $roleId,
                    'status' => 'active',
                    'enrolled_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'User berhasil didaftarkan (Sync Moodle Direct DB)',
                'data' => $enrollment
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Enrollment Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mendaftar user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unenroll/Suspend user
     */
    public function unenrollUser(Request $request, $id, $userId)
    {
        // Permission check: Admin bisa unenroll
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Hanya admin yang bisa unenroll user dari course'
            ], 403);
        }

        $enrollment = CourseEnrollment::where('course_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $enrollment->update(['status' => 'suspended']);

        return response()->json(['message' => 'User berhasil disuspend dari kelas']);
    }

    /**
     * Get enrollment tracking (Super Admin: all, Admin: dept only)
     */
    public function getEnrollmentTracking(Request $request)
    {
        $currentUser = $request->user();

        // Permission check
        if (!$currentUser || !$currentUser->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses'
            ], 403);
        }

        $query = CourseEnrollment::with(['user:id,name,email,department', 'course:id,title,short_name'])
            ->orderBy('created_at', 'desc');

        // Admin hanya lihat enrollment dari dept-nya
        if ($currentUser->hasRole('admin') && !$currentUser->hasRole('super-admin')) {
            $query->whereHas('user', function($q) use ($currentUser) {
                $q->where('department', $currentUser->department);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $enrollments = $query->paginate($request->get('per_page', 20));

        return response()->json($enrollments);
    }
}
