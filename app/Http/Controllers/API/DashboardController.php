<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get today's announcements filtered by role for dashboard widget.
     */
    private function getTodayAnnouncements(Request $request, string $forRole): array
    {
        $today = Carbon::today();

        $query = Announcement::where('published_at', '>=', $today)
            ->where('published_at', '<', $today->copy()->addDay())
            ->with(['creator:id,name,department,position']);

        if ($forRole === 'user') {
            $enrolledCourseIds = $request->user()->courses()->pluck('courses.id')->toArray();

            $query->where(function ($q) use ($enrolledCourseIds) {
                $q->where('scope', 'global')
                  ->orWhere(function ($unitQ) use ($enrolledCourseIds) {
                      $unitQ->where('scope', 'unit')
                            ->where(function ($filterQ) use ($enrolledCourseIds) {
                                $filterQ->where(function ($roleQ) {
                                    $roleQ->whereNull('target_classes')
                                          ->whereIn('target_role', ['all', 'user', 'learner']);
                                });

                                if (!empty($enrolledCourseIds)) {
                                    $filterQ->orWhere(function ($classQ) use ($enrolledCourseIds) {
                                        $classQ->whereNotNull('target_classes')
                                               ->where(function ($jsonQ) use ($enrolledCourseIds) {
                                                   $jsonQ->whereJsonContains('target_classes', 'all');
                                                   foreach ($enrolledCourseIds as $courseId) {
                                                       $jsonQ->orWhereJsonContains('target_classes', $courseId)
                                                             ->orWhereJsonContains('target_classes', (string)$courseId);
                                                   }
                                               });
                                    });
                                }
                            });
                  });
            });
        } else {
            // instructor
            $query->where(function ($q) {
                $q->where('scope', 'global')
                  ->orWhere(function ($unitQ) {
                      $unitQ->where('scope', 'unit')
                            ->whereNull('target_classes')
                            ->whereIn('target_role', ['all', 'instructor']);
                  });
            });
        }

        return $query->orderByRaw("CASE priority
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'normal' THEN 3
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
            ELSE 5 END ASC")
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(function ($ann) {
                return [
                    'id' => $ann->id,
                    'title' => $ann->title,
                    'content' => $ann->content,
                    'priority' => $ann->priority,
                    'created_by' => $ann->creator?->name ?? 'Unknown',
                    'published_at' => $ann->published_at,
                ];
            })
            ->toArray();
    }

    public function employeeDashboard(Request $request)
    {
        $user = $request->user();

        // Statistics (will be dynamic when course system is implemented)
        $stats = [
            'total_courses' => 0,
            'completed_courses' => 0,
            'in_progress_courses' => 0,
            'certificates_earned' => 0,
            'total_learning_hours' => 0,
            'completion_rate' => 0,
        ];

        // Course progress (placeholder - will be real when courses are implemented)
        $courseProgress = [];

        // Recent activities (placeholder - will be real when activity logging is implemented)
        $recentActivities = [];

        // Quick stats for cards
        $quickStats = [
            [
                'title' => 'Active Courses',
                'value' => $stats['in_progress_courses'],
                'icon' => 'book-open',
                'color' => 'blue',
            ],
            [
                'title' => 'Completed',
                'value' => $stats['completed_courses'],
                'icon' => 'check-circle',
                'color' => 'green',
            ],
            [
                'title' => 'Certificates',
                'value' => $stats['certificates_earned'],
                'icon' => 'award',
                'color' => 'yellow',
            ],
            [
                'title' => 'Learning Hours',
                'value' => $stats['total_learning_hours'],
                'icon' => 'clock',
                'color' => 'purple',
            ],
        ];

        // Today's announcements
        $announcements = $this->getTodayAnnouncements($request, 'user');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $user->employee_id,
                    'department' => $user->department,
                    'position' => $user->position,
                    'avatar' => $user->avatar,
                ],
                'stats' => $stats,
                'quick_stats' => $quickStats,
                'course_progress' => $courseProgress,
                'recent_activities' => $recentActivities,
                'announcements' => $announcements,
            ],
        ], 200);
    }

    public function instructorDashboard(Request $request)
    {
        $user = $request->user();

        // Get instructor's courses from Moodle
        $moodleBase = config('services.moodle.url', env('MOODLE_URL'));
        $moodleUser = null; // Initialize variable
        $courses = collect([]); // Initialize courses

        try {
            // First, get user ID from Moodle
            $moodleUser = DB::connection('moodle')
                ->table('user')
                ->where('email', $user->email)
                ->first();

            \Log::info("Instructor Dashboard - Moodle User Check", [
                'email' => $user->email,
                'found' => $moodleUser ? true : false,
                'moodle_user_id' => $moodleUser ? $moodleUser->id : null
            ]);

            if ($moodleUser) {
                // Get courses where user is a teacher/instructor
                // Check for editingteacher or teacher role assignments
                $courses = DB::connection('moodle')
                    ->table('course as c')
                    ->join('context as ctx', function($join) {
                        $join->on('ctx.instanceid', '=', 'c.id')
                             ->where('ctx.contextlevel', '=', 50); // CONTEXT_COURSE = 50
                    })
                    ->join('role_assignments as ra', 'ra.contextid', '=', 'ctx.id')
                    ->join('role as r', 'ra.roleid', '=', 'r.id')
                    ->where('ra.userid', $moodleUser->id)
                    ->whereIn('r.shortname', ['editingteacher', 'teacher']) // Teacher roles
                    ->where('c.id', '!=', 1) // Exclude site home course
                    ->select(
                        'c.id',
                        'c.fullname as title',
                        'c.shortname',
                        'c.startdate',
                        'c.enddate',
                        'c.visible'
                    )
                    ->where('c.visible', 1) // Only visible courses
                    ->distinct()
                    ->get();

                \Log::info("Instructor Dashboard - Courses Query Result", [
                    'courses_count' => $courses->count(),
                    'course_ids' => $courses->pluck('id')->toArray()
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Moodle connection error: ' . $e->getMessage());
            $courses = collect([]);
        }

        // Map courses and calculate stats
        $mapCourses = $courses->map(function ($course) use ($moodleBase) {
            $now = now()->timestamp;
            $status = 'active';

            if ($course->startdate > $now) {
                $status = 'upcoming';
            } elseif ($course->enddate > 0 && $course->enddate < $now) {
                $status = 'completed';
            }

            // Get participant count from Moodle
            try {
                $participantCount = DB::connection('moodle')
                    ->table('user_enrolments as ue')
                    ->join('enrol as e', 'ue.enrolid', '=', 'e.id')
                    ->where('e.courseid', $course->id)
                    ->count();
            } catch (\Exception $e) {
                $participantCount = 0;
            }

            // Find Portal course ID by Moodle course ID
            $portalCourse = Course::where('moodle_course_id', $course->id)->first();

            return [
                'id' => $portalCourse ? $portalCourse->id : $course->id, // Use Portal ID for routing
                'moodle_course_id' => $course->id, // Keep Moodle ID for reference
                'title' => $course->title,
                'short_name' => $course->shortname,
                'description' => '', // Removed to avoid CLOB issues with DISTINCT
                'participants' => $participantCount,
                'schedule' => $course->shortname,
                'status' => $status,
                'progress' => $status === 'completed' ? 100 : ($status === 'active' ? 50 : 0),
                'moodle_url' => "{$moodleBase}/course/view.php?id={$course->id}",
            ];
        });

        // Calculate statistics
        $activeClasses = $mapCourses->where('status', 'active')->count();
        $totalParticipants = $mapCourses->sum('participants');
        $completedClasses = $mapCourses->where('status', 'completed')->count();

        // Calculate average attendance from Moodle logs
        $averageAttendance = 0;
        if ($courses->isNotEmpty() && isset($moodleUser)) {
            try {
                // Get attendance data from Moodle course completion
                $completionStats = DB::connection('moodle')
                    ->table('course_completions as cc')
                    ->join('enrol as e', 'cc.course', '=', 'e.courseid')
                    ->join('user_enrolments as ue', function($join) use ($moodleUser) {
                        $join->on('e.id', '=', 'ue.enrolid')
                             ->where('ue.userid', '=', $moodleUser->id);
                    })
                    ->whereNotNull('cc.timecompleted')
                    ->count();

                $totalCourses = $courses->count();
                if ($totalCourses > 0) {
                    $averageAttendance = round(($completionStats / $totalCourses) * 100);
                }

                // If no completion data, check user activity logs as alternative
                if ($averageAttendance === 0 && $totalCourses > 0) {
                    $activityCount = DB::connection('moodle')
                        ->table('logstore_standard_log')
                        ->where('userid', $moodleUser->id)
                        ->where('action', 'viewed')
                        ->where('target', 'course')
                        ->whereIn('courseid', $courses->pluck('id'))
                        ->distinct('courseid')
                        ->count('courseid');

                    $averageAttendance = round(($activityCount / $totalCourses) * 100);
                }
            } catch (\Exception $e) {
                \Log::error('Error calculating attendance: ' . $e->getMessage());
                $averageAttendance = 0;
            }
        }

        // Today's announcements
        $announcements = $this->getTodayAnnouncements($request, 'instructor');

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'active_classes' => $activeClasses,
                    'total_participants' => $totalParticipants,
                    'completed_classes' => $completedClasses,
                    'average_attendance' => $averageAttendance,
                ],
                'classes' => $mapCourses->values(),
                'announcements' => $announcements,
            ],
        ], 200);
    }

    public function stats(Request $request)
    {
        // Overall platform statistics (for super-admin/admin)
        $totalUsers = User::where('is_active', true)->count();
        $totalAnnouncements = Announcement::where('is_active', true)->count();

        // Department breakdown
        $departmentStats = User::where('is_active', true)
            ->select('department', DB::raw('count(*) as total'))
            ->groupBy('department')
            ->get();

        // Get courses count from Moodle
        $totalCourses = 0;
        try {
            $totalCourses = DB::connection('moodle')
                ->table('course')
                ->where('id', '!=', 1) // Exclude site course
                ->where('visible', 1)
                ->count();
        } catch (\Exception $e) {
            \Log::warning('Could not fetch Moodle courses count: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_announcements' => $totalAnnouncements,
                'total_courses' => $totalCourses,
                'department_breakdown' => $departmentStats,
            ],
        ], 200);
    }
}
