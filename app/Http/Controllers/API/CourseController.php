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
     * Sync courses from Moodle
     */
    public function sync(Request $request)
    {
        try {
            DB::beginTransaction();

            // 1. Get from Moodle
            $moodleCourses = $this->moodleService->getAllCourses();
            
            $syncedCount = 0;
            foreach ($moodleCourses as $mData) {
                // Skip site course (id 1)
                $moodleId = $mData['id'];
                if ($moodleId == 1) continue;

                Course::updateOrCreate(
                    ['moodle_course_id' => $moodleId],
                    [
                        'title' => $mData['fullname'],
                        'short_name' => $mData['shortname'],
                        'description' => strip_tags($mData['summary'] ?? ''),
                        'category_id' => $mData['categoryid'] ?? 1,
                        'start_date' => (isset($mData['startdate']) && $mData['startdate'] > 0) ? date('Y-m-d', $mData['startdate']) : null,
                        'end_date' => (isset($mData['enddate']) && $mData['enddate'] > 0) ? date('Y-m-d', $mData['enddate']) : null,
                        'is_active' => true, 
                    ]
                );
                $syncedCount++;
            }

            // 2. Deactivate courses that are no longer in Moodle response (except mock/manual ones if any)
            // We use the collected IDs to find what's missing.
            // Note: If using Moodle pagination, this might be dangerous as we only get partial list. 
            // Assumption: getAllCourses returns ALL courses.
            
            $moodleIds = array_column($moodleCourses, 'id');
            if (!empty($moodleIds)) {
                Course::whereNotIn('moodle_course_id', $moodleIds)
                    ->update(['is_active' => false]);
            }

            DB::commit();

            return response()->json([
                'message' => "Berhasil sinkronisasi $syncedCount kelas dari Moodle",
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Sync Error: " . $e->getMessage());
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
        $course = Course::with(['instructor:id,name,avatar', 'enrollments.user:id,name,email,avatar,department'])
            ->findOrFail($id);
            
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

        $course = Course::findOrFail($id);
        $user = User::findOrFail($request->user_id);

        // Check already enrolled
        if (CourseEnrollment::where('course_id', $course->id)->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'User sudah terdaftar di kelas ini'], 422);
        }

        try {
            // Sync to Moodle
            // If Moodle Course ID is missing (e.g. old data), we might skip or error
            // But since we just created it, it should be fine.
            // Safe check:
            if ($course->moodle_course_id) {
                $this->moodleService->enrollUser(
                    $course->moodle_course_id, 
                    $user, 
                    $request->input('role_id', 5)
                );
            }

            // Save Local
            $enrollment = CourseEnrollment::create([
                'course_id' => $course->id,
                'user_id' => $user->id,
                'moodle_role_id' => $request->input('role_id', 5),
                'status' => 'active',
                'enrolled_at' => now(),
            ]);

            return response()->json([
                'message' => 'User berhasil didaftarkan',
                'data' => $enrollment
            ]);

        } catch (\Exception $e) {
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
