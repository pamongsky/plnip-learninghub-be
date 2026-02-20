<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
        try {
            $user = $request->user();

            $courses = $user->courses()
                ->with('instructor:id,name,avatar')
                ->withCount('enrollments as participants_count')
                ->wherePivot('status', 'active')
                ->orderBy('course_enrollments.created_at', 'desc')
                ->get()
                ->map(function ($course) {
                    // Determine Moodle URL for direct access
                    $moodleBase = config('services.moodle.url', env('MOODLE_URL'));
                    $course->moodle_url = $course->moodle_course_id
                        ? "{$moodleBase}/course/view.php?id={$course->moodle_course_id}"
                        : null;
                    return $course;
                });

            return ApiResponse::success($courses);
        } catch (\Exception $e) {
            Log::error('My Courses Error: ' . $e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::serverError('Gagal memuat kursus saya', $e->getMessage());
        }
    }

    /**
     * Get courses where current user is an instructor (Moodle Direct + Portal Fallback)
     * Used for selectors like "Target Kelas" in Announcements.
     */
    public function teachingCourses(Request $request)
    {
        try {
            $user = $request->user();
            $moodleBase = config('services.moodle.url', env('MOODLE_URL'));
            
            // 1. Fetch Moodle Courses where user is teacher
            $moodleCourses = collect([]);
            $moodleUser = null;

            try {
                $moodleUser = DB::connection('moodle')
                    ->table('user')
                    ->where('email', $user->email)
                    ->first();

                if ($moodleUser) {
                    $moodleCourses = DB::connection('moodle')
                        ->table('course as c')
                        ->join('context as ctx', function ($join) {
                            $join->on('ctx.instanceid', '=', 'c.id')
                                 ->where('ctx.contextlevel', '=', 50);
                        })
                        ->join('role_assignments as ra', 'ra.contextid', '=', 'ctx.id')
                        ->join('role as r', 'ra.roleid', '=', 'r.id')
                        ->where('ra.userid', $moodleUser->id)
                        ->whereIn('r.shortname', ['editingteacher', 'teacher'])
                        ->where('c.id', '!=', 1)
                        ->where('c.visible', 1)
                        ->select('c.id', 'c.fullname', 'c.shortname')
                        ->distinct()
                        ->get();
                }
            } catch (\Exception $e) {
                // Ignore Moodle connection errors, proceed with Portal only
            }

            // 2. Portal Fallback (instructor_id)
            $portalOnlyCourses = Course::where('instructor_id', $user->id)->get();
            $allCoursesVec = collect([]);
            $moodleIdsFound = [];

            // Add Moodle courses first
            foreach ($moodleCourses as $mc) {
                $allCoursesVec->push((object)[
                    'moodle_id' => $mc->id,
                    'title' => $mc->fullname,
                    'short_name' => $mc->shortname,
                    'is_portal' => false
                ]);
                $moodleIdsFound[] = $mc->id;
            }

            // Add Portal courses if not already found via Moodle
            foreach ($portalOnlyCourses as $pc) {
                if ($pc->moodle_course_id && in_array($pc->moodle_course_id, $moodleIdsFound)) {
                    continue; // Already added
                }
                $allCoursesVec->push((object)[
                    'moodle_id' => $pc->moodle_course_id,
                    'portal_id' => $pc->id, // Explicit portal ID
                    'title' => $pc->title,
                    'short_name' => $pc->short_name,
                    'is_portal' => true
                ]);
            }

            // 3. Map to Portal IDs and Get Participant Counts
            // We need to return Portal IDs for the announcement target mechanism to work
            $moodleIdsToMap = $allCoursesVec->pluck('moodle_id')->filter()->toArray();
            
            // Map Moodle IDs -> Portal IDs
            $portalIdMap = Course::whereIn('moodle_course_id', $moodleIdsToMap)
                ->pluck('id', 'moodle_course_id')
                ->toArray();

            // Get Participant Counts (from Moodle for accuracy)
            $participantCounts = [];
            if (!empty($moodleIdsToMap)) {
                try {
                    $participantCounts = DB::connection('moodle')
                        ->table('user_enrolments as ue')
                        ->join('enrol as e', 'ue.enrolid', '=', 'e.id')
                        ->whereIn('e.courseid', $moodleIdsToMap)
                        ->select('e.courseid', DB::raw('count(*) as total'))
                        ->groupBy('e.courseid')
                        ->pluck('total', 'courseid')
                        ->toArray();
                } catch (\Exception $e) {}
            }

            // Final Transform
            $results = $allCoursesVec->map(function($c) use ($portalIdMap, $participantCounts) {
                // Determine valid Portal ID or fallback to Moodle ID if sync missing
                // Ideally we sync on fly but that's heavy.
                // If we don't have a portal ID, this announcement might fail to target users if logic relies on portal ID.
                // But mostly it implies the course isn't synced yet.
                
                $finalId = $c->is_portal ? ($c->portal_id ?? $c->moodle_id) : ($portalIdMap[$c->moodle_id] ?? $c->moodle_id);
                
                return [
                    'id' => $finalId, 
                    'title' => $c->title,
                    'short_name' => $c->short_name,
                    'participants_count' => $participantCounts[$c->moodle_id] ?? 0,
                ];
            });

            return ApiResponse::success($results);

        } catch (\Exception $e) {
             Log::error('Teaching Courses Error: ' . $e->getMessage());
             return ApiResponse::serverError('Gagal memuat data kelas ajar', $e->getMessage());
        }
    }

    /**
     * Sync courses from Moodle (Direct DB Strategy)
     */
    public function sync(Request $request)
    {
        // Permission check: Super admin dan Admin bisa sync
        if (!$request->user() || !$request->user()->hasRole(['super-admin', 'admin'])) {
            return ApiResponse::forbidden('Hanya admin yang bisa sync courses dari Moodle');
        }

        try {
            DB::beginTransaction();

            $syncedCount = 0;

            // 1. Get from Moodle DB Directly (Oracle)
            $moodleCourses = DB::connection('moodle')->table('course')
                ->where('id', '!=', 1)
                ->get();

            foreach ($moodleCourses as $mCourse) {
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

            DB::commit();

            return ApiResponse::success(
                ['synced_count' => $syncedCount],
                "Berhasil sinkronisasi $syncedCount kelas dari Moodle (Direct DB)"
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Course Sync Error: " . $e->getMessage());
            return ApiResponse::serverError('Gagal sinkronisasi', $e->getMessage());
        }
    }

    /**
     * Get course details with enrollments
     */
    public function show($id)
    {
        $course = Course::with([
            'instructor:id,name,avatar',
            'enrollments.user:id,name,email,avatar,department,employee_id',
            'students',
        ])->findOrFail($id);

        // Enrich enrollment data with Moodle progress and activity
        if ($course->moodle_course_id && $course->enrollments) {
            try {
                $moodleConn = DB::connection('moodle');
                $moodleCourseId = $course->moodle_course_id;

                // =============================================
                // BATCH QUERY #1: Find all Moodle users at once
                // =============================================
                $enrolledEmails = $course->enrollments->map(fn($e) => $e->user?->email)->filter()->unique()->values()->toArray();

                // Guard: skip Moodle queries if there are no enrolled users
                if (empty($enrolledEmails)) {
                    return response()->json($course);
                }

                $moodleUsersMap = $moodleConn->table('user')
                    ->whereIn('email', $enrolledEmails)
                    ->get(['id', 'email'])
                    ->keyBy('email'); // email => moodle_user_obj

                $moodleUserIds = $moodleUsersMap->pluck('id')->toArray();

                // Guard: skip batch queries if no Moodle users matched
                if (empty($moodleUserIds)) {
                    return response()->json($course);
                }

                // =============================================
                // BATCH QUERY #2: Get completions for all users
                // =============================================
                $completionsMap = $moodleConn->table('course_completions')
                    ->where('course', $moodleCourseId)
                    ->whereIn('userid', $moodleUserIds)
                    ->get(['userid', 'timecompleted'])
                    ->keyBy('userid'); // moodle_userid => completion_obj

                // =============================================
                // BATCH QUERY #3: Total activities in this course (single query)
                // =============================================
                $totalActivities = $moodleConn->table('course_modules as cm')
                    ->join('modules as m', 'cm.module', '=', 'm.id')
                    ->where('cm.course', $moodleCourseId)
                    ->where('cm.visible', 1)
                    ->where('cm.completion', '>', 0)
                    ->count();

                // =============================================
                // BATCH QUERY #4: Completed activities per user
                // =============================================
                $completedActivitiesMap = $moodleConn->table('course_modules_completion')
                    ->join('course_modules as cm', 'course_modules_completion.coursemoduleid', '=', 'cm.id')
                    ->where('cm.course', $moodleCourseId)
                    ->whereIn('course_modules_completion.userid', $moodleUserIds)
                    ->where('course_modules_completion.completionstate', '>', 0)
                    ->select('course_modules_completion.userid', DB::raw('count(*) as completed'))
                    ->groupBy('course_modules_completion.userid')
                    ->get()
                    ->keyBy('userid'); // moodle_userid => {completed: N}

                // =============================================
                // BATCH QUERY #5: Last activity per user
                // =============================================
                $lastActivityMap = $moodleConn->table('logstore_standard_log')
                    ->where('courseid', $moodleCourseId)
                    ->whereIn('userid', $moodleUserIds)
                    ->select('userid', DB::raw('MAX(timecreated) as last_seen'))
                    ->groupBy('userid')
                    ->get()
                    ->keyBy('userid'); // moodle_userid => {last_seen: timestamp}

                // =============================================
                // BATCH QUERY #6: Course Grade (Fallback)
                // =============================================
                $courseTotalItem = $moodleConn->table('grade_items')
                    ->where('courseid', $moodleCourseId)
                    ->where('itemtype', 'course')
                    ->first();

                $gradesMap = collect();
                if ($courseTotalItem) {
                    $gradesMap = $moodleConn->table('grade_grades')
                        ->where('itemid', $courseTotalItem->id)
                        ->whereIn('userid', $moodleUserIds)
                        ->get(['userid', 'finalgrade', 'rawgrade'])
                        ->keyBy('userid');
                }

                // =============================================
                // NOW loop — all data already in memory, no more DB calls
                // =============================================
                foreach ($course->enrollments as $enrollment) {
                    $userEmail = $enrollment->user?->email;
                    $moodleUser = $moodleUsersMap[$userEmail] ?? null;

                    if ($moodleUser) {
                        $mUserId = $moodleUser->id;
                        $completion = $completionsMap[$mUserId] ?? null;

                        if ($completion && $completion->timecompleted) {
                            $enrollment->progress = 100;
                        } elseif ($totalActivities > 0) {
                            $completed = (int) ($completedActivitiesMap[$mUserId]?->completed ?? 0);
                            $enrollment->progress = round(($completed / $totalActivities) * 100);
                        } else {
                            // Fallback to Grade if no completion tracking
                            $grade = $gradesMap[$mUserId] ?? null;
                            if ($grade && $grade->finalgrade !== null && $courseTotalItem) {
                                $maxGrade = $courseTotalItem->grademax ?: 100;
                                $enrollment->progress = round(($grade->finalgrade / $maxGrade) * 100);
                            } else {
                                $enrollment->progress = 0;
                            }
                        }

                        $lastActivity = $lastActivityMap[$mUserId] ?? null;
                        $enrollment->last_activity_at = $lastActivity
                            ? date('Y-m-d H:i:s', $lastActivity->last_seen)
                            : null;
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

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'course_updated',
            'entity_type' => 'Course',
            'entity_id'   => $course->id,
            'changes'     => json_encode($validated),
            'reason'      => 'Course details updated',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

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
            $moodleUser = null;
            $moodleCreatedUser = false;
            $moodleCreatedEnrollment = false;
            $moodleCreatedRole = false;

            if ($course->moodle_course_id) {
                try {
                    $moodleConn = DB::connection('moodle');

                    // Test Moodle connection first
                    $moodleConn->select('SELECT 1 FROM DUAL');

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
                    $moodleCreatedUser = true;
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

                // 5. Insert into mdl_role_assignments
                // Moodle Role ID mapping:
                // 1 = Manager (Super Admin)
                // 2 = Course Creator (Admin)
                // 3 = Editing Teacher (Instruktur Penuh)
                // 4 = Non-Editing Teacher (Asisten)
                // 5 = Student

                // Auto-map Portal role to Moodle role if not explicitly provided
                $roleId = $request->input('role_id');

                if (!$roleId) {
                    // Check user's Portal role and auto-map
                    if ($user->hasRole('super-admin')) {
                        $roleId = 1; // Manager in Moodle
                    } elseif ($user->hasRole('admin')) {
                        $roleId = 2; // Course Creator in Moodle
                    } elseif ($user->hasRole('instructor')) {
                        $roleId = 3; // Editing Teacher in Moodle
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
                        'component' => ' ', // Oracle: empty string = NULL, use space instead
                        'itemid' => 0,
                        'sortorder' => 0
                    ]);
                    $moodleCreatedRole = true;
                }
            } catch (\Exception $moodleException) {
                // Moodle operation failed - rollback Portal transaction
                DB::rollBack();

                Log::error("Moodle enrollment failed", [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'error' => $moodleException->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Gagal mendaftar user ke Moodle. Silakan coba lagi atau hubungi administrator.',
                    'error' => config('app.debug') ? $moodleException->getMessage() : null,
                ], 500);
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

            AuditLog::create([
                'user_id'     => $currentUser->id,
                'action'      => 'course_enrolled',
                'entity_type' => 'CourseEnrollment',
                'entity_id'   => $enrollment->id,
                'changes'     => json_encode(['course_id' => $course->id, 'user_id' => $user->id, 'role_id' => $roleId ?? null]),
                'reason'      => 'Admin enrolled user to course',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'User berhasil didaftarkan (Sync Moodle Direct DB)',
                'data' => $enrollment
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // Check if it's a unique constraint violation (concurrent enrollment attempt)
            if (str_contains($e->getMessage(), 'unique constraint') ||
                str_contains($e->getMessage(), 'UNIQUE constraint') ||
                $e->getCode() === '23000') {

                Log::warning("Concurrent enrollment attempt detected", [
                    'user_id' => $request->user_id,
                    'course_id' => $id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'User sudah terdaftar di kelas ini',
                ], 422);
            }

            // Other database errors
            Log::error("Database Error during enrollment: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mendaftar user ke database',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Enrollment Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mendaftar user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unenroll user from course (delete enrollment + sync to Moodle)
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

        $course = Course::findOrFail($id);
        $user = User::findOrFail($userId);

        // Sync unenroll to Moodle
        if ($course->moodle_course_id && $user->moodle_user_id) {
            try {
                $moodleConn = DB::connection('moodle');

                // Get course context
                $context = $moodleConn->table('context')
                    ->where('contextlevel', 50)
                    ->where('instanceid', $course->moodle_course_id)
                    ->first();

                if ($context) {
                    // Remove all role assignments for this user in this course
                    $moodleConn->table('role_assignments')
                        ->where('contextid', $context->id)
                        ->where('userid', $user->moodle_user_id)
                        ->delete();
                }

                // Delete user enrollment in Moodle (Hard Delete)
                $moodleConn->table('user_enrolments')
                    ->where('userid', $user->moodle_user_id)
                    ->whereIn('enrolid', function ($query) use ($course) {
                        $query->select('id')
                            ->from('enrol')
                            ->where('courseid', $course->moodle_course_id);
                    })
                    ->delete();

            } catch (\Exception $e) {
                Log::warning("Failed to sync unenroll to Moodle for user {$userId}: " . $e->getMessage());
            }
        }

        // Delete enrollment from portal
        $enrollment->delete();

        AuditLog::create([
            'user_id'     => $currentUser->id,
            'action'      => 'course_unenrolled',
            'entity_type' => 'CourseEnrollment',
            'entity_id'   => $id,
            'changes'     => json_encode(['course_id' => $id, 'user_id' => $userId]),
            'reason'      => 'Admin unenrolled user from course',
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json(['message' => 'User berhasil diunenroll dari kelas']);
    }

    /**
     * Update Moodle role for an enrolled user
     */
    public function updateEnrollmentRole(Request $request, $id, $userId)
    {
        $request->validate([
            'role_id' => 'required|integer|in:1,2,3,4,5',
        ]);

        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasRole(['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'Hanya admin yang bisa mengubah role'
            ], 403);
        }

        try {
            $course = Course::findOrFail($id);
            $user = User::findOrFail($userId);
            $newRoleId = $request->role_id;

            $enrollment = CourseEnrollment::where('course_id', $id)
                ->where('user_id', $userId)
                ->firstOrFail();

            $oldRoleId = $enrollment->moodle_role_id;


            // Update Moodle role_assignments if course has Moodle integration
            if ($course->moodle_course_id && $user->moodle_user_id) {
                try {
                    $moodleConn = DB::connection('moodle');

                    $context = $moodleConn->table('context')
                        ->where('contextlevel', 50)
                        ->where('instanceid', $course->moodle_course_id)
                        ->first();

                    if ($context) {
                        // Remove OLD role assignment only if we know what it was
                        if ($oldRoleId !== null) {
                            $moodleConn->table('role_assignments')
                                ->where('contextid', $context->id)
                                ->where('userid', $user->moodle_user_id)
                                ->where('roleid', $oldRoleId)
                                ->delete();
                        } else {
                            // No previous role tracked — remove ALL roles for clean state
                            $moodleConn->table('role_assignments')
                                ->where('contextid', $context->id)
                                ->where('userid', $user->moodle_user_id)
                                ->delete();
                        }

                        // Insert new role assignment
                        $moodleConn->table('role_assignments')->insert([
                            'roleid'       => $newRoleId,
                            'contextid'    => $context->id,
                            'userid'       => $user->moodle_user_id,
                            'timemodified' => now()->timestamp,
                            'modifierid'   => 2,
                            'component'    => ' ',
                            'itemid'       => 0,
                            'sortorder'    => 0,
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log error but ALLOW local update to proceed
                    Log::error("Moodle role sync failed for user {$userId}: " . $e->getMessage());
                    // Optional: return warning message in response
                }
            }

            // Update local enrollment
            $enrollment->update(['moodle_role_id' => $newRoleId]);

            // Update instructor_id if changing to/from teacher role
            if (in_array($newRoleId, [3, 4]) && !$course->instructor_id) {
                $course->update(['instructor_id' => $user->id]);
            }

            $roleNames = [1 => 'Manager', 2 => 'Course Creator', 3 => 'Editing Teacher', 4 => 'Non-Editing Teacher', 5 => 'Student'];

            AuditLog::create([
                'user_id'     => $currentUser->id,
                'action'      => 'enrollment_role_changed',
                'entity_type' => 'CourseEnrollment',
                'entity_id'   => $enrollment->id,
                'changes'     => json_encode([
                    'course_id'   => $id,
                    'user_id'     => $userId,
                    'old_role_id' => $oldRoleId,
                    'new_role_id' => $newRoleId,
                    'new_role'    => $roleNames[$newRoleId],
                ]),
                'reason'      => 'Admin changed enrollment role',
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);

            return response()->json([
                'message' => "Role berhasil diubah ke {$roleNames[$newRoleId]}",
                'data' => $enrollment->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error("Update role error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengubah role: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed progress for a specific user in a course
     */
    public function getUserProgress($courseId, $userId)
    {
        $course = Course::findOrFail($courseId);
        $user = User::findOrFail($userId);

        // SECURITY: Check if user is enrolled to this course
        $enrollment = CourseEnrollment::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak memiliki akses ke course ini'
            ], 403);
        }

        if (!$course->moodle_course_id) {
            return response()->json([
                'message' => 'Course tidak terhubung ke Moodle'
            ], 404);
        }

        try {
            $moodleConn = DB::connection('moodle');

            // Find Moodle user by email
            $moodleUser = $moodleConn->table('user')
                ->where('email', $user->email)
                ->where('deleted', 0)
                ->first();

            if (!$moodleUser) {
                return response()->json([
                    'message' => 'User tidak ditemukan di Moodle'
                ], 404);
            }

            // 1. Get all visible course modules with their types
            $modules = $moodleConn->table('course_modules as cm')
                ->join('modules as m', 'cm.module', '=', 'm.id')
                ->where('cm.course', $course->moodle_course_id)
                ->where('cm.visible', 1)
                ->where('cm.deletioninprogress', 0)
                ->select(
                    'cm.id as cmid',
                    'cm.instance',
                    'cm.section',
                    'cm.completion',
                    'm.name as module_type'
                )
                ->orderBy('cm.section')
                ->orderBy('cm.id')
                ->get();

            // 2. Get activity names from each module-specific table
            $moduleTypes = $modules->pluck('module_type')->unique();
            $namesByType = [];

            foreach ($moduleTypes as $type) {
                $instanceIds = $modules->where('module_type', $type)->pluck('instance')->toArray();
                if (empty($instanceIds)) continue;

                try {
                    $names = $moodleConn->table($type)
                        ->whereIn('id', $instanceIds)
                        ->select('id', 'name')
                        ->get()
                        ->keyBy('id');
                    $namesByType[$type] = $names;
                } catch (\Exception $e) {
                    // Some module types might not have a 'name' column (e.g., label)
                    Log::warning("Could not fetch names for module type '{$type}': " . $e->getMessage());
                    $namesByType[$type] = collect();
                }
            }

            // Assign names to modules
            foreach ($modules as $mod) {
                $names = $namesByType[$mod->module_type] ?? collect();
                $mod->activity_name = isset($names[$mod->instance]) ? $names[$mod->instance]->name : 'Aktivitas';
            }

            // 3. Get completion status for this user
            $completions = $moodleConn->table('course_modules_completion')
                ->where('userid', $moodleUser->id)
                ->whereIn('coursemoduleid', $modules->pluck('cmid')->toArray())
                ->get()
                ->keyBy('coursemoduleid');

            // 4. Get grade items for this course (mod type only)
            $gradeItems = $moodleConn->table('grade_items')
                ->where('courseid', $course->moodle_course_id)
                ->where('itemtype', 'mod')
                ->get();

            // 5. Get grades for this user
            $grades = collect();
            if ($gradeItems->isNotEmpty()) {
                $grades = $moodleConn->table('grade_grades')
                    ->where('userid', $moodleUser->id)
                    ->whereIn('itemid', $gradeItems->pluck('id')->toArray())
                    ->get()
                    ->keyBy('itemid');
            }

            // 6. Build activities list
            $activities = [];
            foreach ($modules as $mod) {
                $completion = $completions->get($mod->cmid);

                // Find matching grade item
                $gradeItem = $gradeItems
                    ->where('itemmodule', $mod->module_type)
                    ->where('iteminstance', $mod->instance)
                    ->first();

                $grade = $gradeItem ? $grades->get($gradeItem->id) : null;

                $gradePercent = null;
                $gradeRaw = null;
                $gradeMax = null;

                if ($grade && $grade->finalgrade !== null && $gradeItem) {
                    $gradeMax = $gradeItem->grademax ?: 100;
                    $gradeRaw = round($grade->finalgrade, 1);
                    $gradePercent = round(($grade->finalgrade / $gradeMax) * 100, 1);
                }

                $activities[] = [
                    'cmid' => $mod->cmid,
                    'name' => $mod->activity_name,
                    'type' => $mod->module_type,
                    'has_completion' => $mod->completion > 0,
                    'completion_status' => $completion ? (int)$completion->completionstate : 0,
                    // 0=not completed, 1=complete, 2=complete-pass, 3=complete-fail
                    'grade' => $gradePercent,
                    'grade_raw' => $gradeRaw,
                    'grade_max' => $gradeMax ? round($gradeMax, 1) : null,
                ];
            }

            // 7. Course total grade
            $courseTotalItem = $moodleConn->table('grade_items')
                ->where('courseid', $course->moodle_course_id)
                ->where('itemtype', 'course')
                ->first();

            $courseGrade = null;
            if ($courseTotalItem) {
                $ctGrade = $moodleConn->table('grade_grades')
                    ->where('userid', $moodleUser->id)
                    ->where('itemid', $courseTotalItem->id)
                    ->first();

                if ($ctGrade && $ctGrade->finalgrade !== null) {
                    $courseGrade = round(
                        ($ctGrade->finalgrade / ($courseTotalItem->grademax ?: 100)) * 100,
                        1
                    );
                }
            }

            // 8. Last access from log
            $lastAccess = $moodleConn->table('logstore_standard_log')
                ->where('userid', $moodleUser->id)
                ->where('courseid', $course->moodle_course_id)
                ->orderBy('timecreated', 'desc')
                ->first();

            // 9. Calculate overall progress (hybrid: completion → grade fallback)
            $totalWithCompletion = $modules->where('completion', '>', 0)->count();
            $completedCount = $completions->filter(fn($c) => $c->completionstate > 0)->count();

            $courseCompletion = $moodleConn->table('course_completions')
                ->where('userid', $moodleUser->id)
                ->where('course', $course->moodle_course_id)
                ->first();

            $overallProgress = 0;
            $progressMode = 'none';

            if ($courseCompletion && $courseCompletion->timecompleted) {
                $overallProgress = 100;
                $progressMode = 'course_complete';
            } elseif ($totalWithCompletion > 0) {
                $overallProgress = round(($completedCount / $totalWithCompletion) * 100);
                $progressMode = 'completion';
            } else {
                // Fallback: no completion configured → use grades
                $totalGradeable = $gradeItems->count();
                $gradedCount = $grades->filter(fn($g) => $g->finalgrade !== null)->count();
                if ($totalGradeable > 0) {
                    $overallProgress = round(($gradedCount / $totalGradeable) * 100);
                    $progressMode = 'grades';
                    $totalWithCompletion = $totalGradeable;
                    $completedCount = $gradedCount;
                }
            }

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                ],
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                ],
                'progress' => $overallProgress,
                'progress_mode' => $progressMode,
                'total_activities' => $modules->count(),
                'completed_activities' => $completedCount,
                'total_with_completion' => $totalWithCompletion,
                'activities' => $activities,
                'course_grade' => $courseGrade,
                'last_access' => $lastAccess ? date('Y-m-d H:i:s', $lastAccess->timecreated) : null,
            ]);

        } catch (\Exception $e) {
            Log::error("Progress tracking error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengambil data progress: ' . $e->getMessage()
            ], 500);
        }
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
