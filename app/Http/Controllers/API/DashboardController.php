<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function employeeDashboard(Request $request)
    {
        $user = $request->user();

        // Latest announcements
        $announcements = Announcement::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->with('creator:id,name,department,position')
            ->orderBy('priority', 'desc')
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

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
                'announcements' => $announcements,
                'course_progress' => $courseProgress,
                'recent_activities' => $recentActivities,
            ],
        ], 200);
    }

    public function instructorDashboard(Request $request)
    {
        $user = $request->user();

        // Latest announcements
        $announcements = Announcement::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->with('creator:id,name,department,position')
            ->orderBy('priority', 'desc')
            ->orderBy('published_at', 'desc')
            ->limit(5)
            ->get();

        // Get instructor's courses from Moodle
        $moodleBase = config('services.moodle.url', env('MOODLE_URL'));

        try {
            // First, get user ID from Moodle
            $moodleUser = DB::connection('moodle')
                ->table('user')
                ->where('email', $user->email)
                ->first();

            if (!$moodleUser) {
                // User not found in Moodle, return empty courses
                $courses = collect([]);
            } else {
                // Get courses where user is enrolled
                $courses = DB::connection('moodle')
                    ->table('course as c')
                    ->join('enrol as e', 'c.id', '=', 'e.courseid')
                    ->join('user_enrolments as ue', 'e.id', '=', 'ue.enrolid')
                    ->where('ue.userid', $moodleUser->id)
                    ->where('c.id', '!=', 1) // Exclude site home course
                    ->select(
                        'c.id',
                        'c.fullname as title',
                        'c.shortname',
                        'c.summary as description',
                        'c.startdate',
                        'c.enddate'
                    )
                    ->distinct()
                    ->get();
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

            return [
                'id' => $course->id,
                'title' => $course->title,
                'short_name' => $course->shortname,
                'description' => strip_tags($course->description ?? ''),
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

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'active_classes' => $activeClasses,
                    'total_participants' => $totalParticipants,
                    'completed_classes' => $completedClasses,
                    'average_attendance' => 87, // Placeholder
                ],
                'announcements' => $announcements,
                'classes' => $mapCourses->values(),
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
