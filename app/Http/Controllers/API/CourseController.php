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
    public function index()
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
            'students' // Add this
        ])->findOrFail($id);
        
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
            'role_id' => 'integer' // 5 = student, 3 = teacher
        ]);

        try {
            DB::beginTransaction();

            $course = Course::findOrFail($id);
            $user = User::findOrFail($request->user_id);

            // Check already enrolled locally
             if (CourseEnrollment::where('course_id', $course->id)->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'User sudah terdaftar di kelas ini'], 422);
            }

            // --- MOODLE DIRECT DB SYNC ---
            if ($course->moodle_course_id) {
                $moodleConn = DB::connection('moodle');
                
                // 1. Find Moodle User ID (by Email)
                $moodleUser = $moodleConn->table('user')->where('email', $user->email)->first();
                if (!$moodleUser) {
                    throw new \Exception("User Moodle tidak ditemukan untuk email: {$user->email}");
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

                // 4. Insert into mdl_user_enrolments (The "Join" action)
                // Check if already exists first to avoid duplicate errors
                $existingEnrol = $moodleConn->table('user_enrolments')
                    ->where('enrolid', $enrolInstance->id)
                    ->where('userid', $moodleUser->id)
                    ->first();

                if (!$existingEnrol) {
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
                // Role ID 5 = Student (Standard Moodle)
                $roleId = $request->input('role_id', 5); 
                
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
            $enrollment = CourseEnrollment::create([
                'course_id' => $course->id,
                'user_id' => $user->id,
                'moodle_role_id' => $request->input('role_id', 5),
                'status' => 'active',
                'enrolled_at' => now(),
            ]);

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
    public function unenrollUser($id, $userId)
    {
        // For now, we just delete the local record or set status to suspended
        // Real unenroll in Moodle API is different function.
        // Let's soft delete (set suspended)
        
        $enrollment = CourseEnrollment::where('course_id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $enrollment->update(['status' => 'suspended']);

        return response()->json(['message' => 'User berhasil disuspend dari kelas']);
    }
}
