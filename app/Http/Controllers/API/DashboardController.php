<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        if ($forRole === 'learner') {
            $enrolledCourseIds = $request->user()->courses()->pluck('courses.id')->toArray();

            $query->where(function ($q) use ($enrolledCourseIds) {
                $q->where('scope', 'global')
                  ->orWhere(function ($unitQ) use ($enrolledCourseIds) {
                      $unitQ->where('scope', 'unit')
                            ->where(function ($filterQ) use ($enrolledCourseIds) {
                                $filterQ->where(function ($roleQ) {
                                    $roleQ->whereNull('target_classes')
                                          ->whereIn('target_role', ['all', 'learner']);
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
            WHEN 'penting' THEN 1
            WHEN 'umum' THEN 2
            WHEN 'informasi' THEN 3
            ELSE 4 END ASC")
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

    public function learnerDashboard(Request $request)
    {
        try {
            $user = $request->user();

            // Calculate real statistics from enrollments and Moodle data
            $totalCourses = $user->courses()->count();
            $completedCourses = $user->courses()
                ->wherePivot('status', 'completed')
                ->count();
            $inProgressCourses = $user->courses()
                ->wherePivot('status', 'active')
                ->count();

            // Get certificates earned
            $certificatesEarned = DB::table('certificates')
                ->where('user_id', $user->id)
                ->where('is_valid', true)
                ->count();

            // Calculate total learning hours from Moodle
            $totalLearningHours = 0;
            if ($user->moodle_user_id) {
                try {
                    // Get enrolled course IDs from Moodle
                    $enrolledCourseIds = DB::connection('moodle')
                        ->table('user_enrolments as ue')
                        ->join('enrol as e', 'ue.enrolid', '=', 'e.id')
                        ->where('ue.userid', $user->moodle_user_id)
                        ->pluck('e.courseid')
                        ->toArray();

                    if (!empty($enrolledCourseIds)) {
                        // Calculate time spent from log entries
                        // Each 'viewed' action represents active learning time
                        $logCount = DB::connection('moodle')
                            ->table('logstore_standard_log')
                            ->where('userid', $user->moodle_user_id)
                            ->whereIn('courseid', $enrolledCourseIds)
                            ->where('action', 'viewed')
                            ->count();

                        // Estimate: 1 log entry ≈ 3 minutes of active learning
                        // Adjust this multiplier based on your actual data analysis
                        $totalLearningHours = round(($logCount * 3) / 60, 1);
                    }
                } catch (\Exception $e) {
                    Log::error('Error calculating learning hours: ' . $e->getMessage());
                    $totalLearningHours = 0;
                }
            }

            // Calculate completion rate
            $completionRate = $totalCourses > 0
                ? round(($completedCourses / $totalCourses) * 100)
                : 0;

            $stats = [
                'total_courses' => $totalCourses,
                'completed_courses' => $completedCourses,
                'in_progress_courses' => $inProgressCourses,
                'certificates_earned' => $certificatesEarned,
                'total_learning_hours' => $totalLearningHours,
                'completion_rate' => $completionRate,
            ];

            // Get course progress from enrolled courses
            $courseProgress = $user->courses()
                ->get()
                ->map(function ($course) use ($user) {
                    $enrollment = $course->pivot;

                    // Get completion percentage from Moodle if available
                    $completionPercentage = 0;
                    if ($user->moodle_user_id && $course->moodle_course_id) {
                        try {
                            $completion = DB::connection('moodle')
                                ->table('course_completions')
                                ->where('userid', $user->moodle_user_id)
                                ->where('course', $course->moodle_course_id)
                                ->first();

                            if ($completion && $completion->timecompleted) {
                                $completionPercentage = 100;
                            } else {
                                // Calculate from completed activities
                                $totalActivities = DB::connection('moodle')
                                    ->table('course_modules')
                                    ->where('course', $course->moodle_course_id)
                                    ->where('visible', 1)
                                    ->count();

                                if ($totalActivities > 0) {
                                    $completedActivities = DB::connection('moodle')
                                        ->table('course_modules_completion')
                                        ->join('course_modules as cm', 'course_modules_completion.coursemoduleid', '=', 'cm.id')
                                        ->where('course_modules_completion.userid', $user->moodle_user_id)
                                        ->where('cm.course', $course->moodle_course_id)
                                        ->where('course_modules_completion.completionstate', '>', 0)
                                        ->count();

                                    $completionPercentage = round(($completedActivities / $totalActivities) * 100);
                                }
                            }
                        } catch (\Exception $e) {
                            \Log::warning("Error getting course completion: " . $e->getMessage());
                        }
                    }

                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'category' => $course->category?->name ?? 'Uncategorized',
                        'progress' => $completionPercentage,
                        'status' => $enrollment->status,
                        'enrolled_at' => $enrollment->enrolled_at,
                    ];
                })
                ->values()
                ->toArray();

            // Get recent activities from Moodle logs
            $recentActivities = [];
            if ($user->moodle_user_id) {
                try {
                    $recentActivities = DB::connection('moodle')
                        ->table('logstore_standard_log as l')
                        ->join('course as c', 'l.courseid', '=', 'c.id')
                        ->where('l.userid', $user->moodle_user_id)
                        ->where('l.action', 'viewed')
                        ->where('c.id', '!=', 1) // Exclude site course
                        ->select(
                            'c.fullname as course_name',
                            'l.target',
                            'l.action',
                            'l.timecreated'
                        )
                        ->orderBy('l.timecreated', 'desc')
                        ->limit(5)
                        ->get()
                        ->map(function ($log) {
                            return [
                                'course' => $log->course_name,
                                'activity' => ucfirst($log->action) . ' ' . $log->target,
                                'time' => date('Y-m-d H:i:s', $log->timecreated),
                            ];
                        })
                        ->toArray();
                } catch (\Exception $e) {
                    \Log::warning('Error fetching recent activities: ' . $e->getMessage());
                    $recentActivities = [];
                }
            }

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
            $announcements = $this->getTodayAnnouncements($request, 'learner');

            return ApiResponse::success([
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
            ]);
        } catch (\Exception $e) {
            Log::error('Learner Dashboard Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::serverError('Gagal memuat dashboard', $e->getMessage());
        }
    }

    public function instructorDashboard(Request $request)
    {
            try {
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

            // Fallback: include portal courses where instructor_id = this user
            // (in case Moodle query missed some due to visibility or missing moodle user)
            $portalOnlyCourses = Course::where('instructor_id', $user->id)->get();
            $moodleCourseIds = $courses->pluck('id')->toArray();

            foreach ($portalOnlyCourses as $pc) {
                // Only add if not already in Moodle result set
                if (!in_array($pc->moodle_course_id, $moodleCourseIds)) {
                    $now = now()->timestamp;
                    $status = 'active';
                    if ($pc->start_date && strtotime($pc->start_date) > $now) $status = 'upcoming';
                    elseif ($pc->end_date && strtotime($pc->end_date) < $now) $status = 'completed';

                    $courses->push((object)[
                        'id' => $pc->moodle_course_id ?? $pc->id,
                        'title' => $pc->title,
                        'shortname' => $pc->short_name ?? $pc->title,
                        'startdate' => $pc->start_date ? strtotime($pc->start_date) : 0,
                        'enddate' => $pc->end_date ? strtotime($pc->end_date) : 0,
                        'visible' => 1,
                        '_portal_id' => $pc->id,
                    ]);
                    $moodleCourseIds[] = $pc->moodle_course_id ?? $pc->id;
                }
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

                // Find Portal course ID by Moodle course ID (or use pre-resolved _portal_id)
                if (isset($course->_portal_id)) {
                    $portalId = $course->_portal_id;
                } else {
                    $portalCourse = Course::where('moodle_course_id', $course->id)->first();
                    $portalId = $portalCourse ? $portalCourse->id : $course->id;
                }

                return [
                    'id' => $portalId, // Use Portal ID for routing
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

            return ApiResponse::success([
                'stats' => [
                    'active_classes' => $activeClasses,
                    'total_participants' => $totalParticipants,
                    'completed_classes' => $completedClasses,
                    'average_attendance' => $averageAttendance,
                ],
                'classes' => $mapCourses->values(),
                'announcements' => $announcements,
            ]);
        } catch (\Exception $e) {
            Log::error('Instructor Dashboard Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::serverError('Gagal memuat dashboard instructor', $e->getMessage());
        }
    }

    public function stats(Request $request)
    {
        try {
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
                Log::warning('Could not fetch Moodle courses count: ' . $e->getMessage());
            }

            return ApiResponse::success([
                'total_users' => $totalUsers,
                'total_announcements' => $totalAnnouncements,
                'total_courses' => $totalCourses,
                'department_breakdown' => $departmentStats,
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard Stats Error: ' . $e->getMessage());
            return ApiResponse::serverError('Gagal memuat statistik dashboard', $e->getMessage());
        }
    }
}
