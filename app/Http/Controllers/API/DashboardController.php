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

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'total_announcements' => $totalAnnouncements,
                'total_courses' => 0, // Will be dynamic when course system exists
                'department_breakdown' => $departmentStats,
            ],
        ], 200);
    }
}
