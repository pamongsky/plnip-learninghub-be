<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Events\AnnouncementCreated;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Get all announcements for Super Admin (management view)
     * No scope filtering - see everything.
     */
    public function superAdminIndex(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $priority = $request->input('priority');
        $search = $request->input('search');

        // Base query without scope restrictions
        $query = Announcement::with(['creator:id,name,department,position', 'creator.roles']);

        if ($priority && $priority !== 'all') {
            $query->where('priority', $priority);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Sort: Priority then Created At (descending)
        // Using created_at instead of published_at so admins see newest entries first
        // Sort: Priority then Created At (descending)
        // Using created_at instead of published_at so admins see newest entries first
        $announcements = $query->orderByRaw("CASE priority 
            WHEN 'penting' THEN 1 
            WHEN 'umum' THEN 2 
            WHEN 'informasi' THEN 3 
            ELSE 4 END ASC")
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform data
        $mappedAnnouncements = $announcements->map(function ($ann) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'content' => $ann->content,
                'priority' => $ann->priority,
                'scope' => $ann->scope,
                'target_role' => $ann->target_role,
                'target_classes' => $ann->target_classes,
                'published_at' => $ann->published_at,
                'expires_at' => $ann->expires_at,
                'is_active' => $ann->is_active,
                'views_count' => $ann->views_count ?? 0,
                'created_at' => $ann->created_at,
                'created_by' => $ann->created_by,
                'created_by_id' => $ann->created_by,
                'creator' => $ann->creator ? [
                    'id' => $ann->creator->id,
                    'name' => $ann->creator->name,
                    'department' => $ann->creator->department,
                    'position' => $ann->creator->position,
                ] : null,
                'creator_role' => $ann->creator?->roles->first()?->name ?? 'Unknown',
                'status_label' => $this->getAnnouncementStatus($ann),
                'status' => strtolower($this->getAnnouncementStatus($ann)),
            ];
        });

        return ApiResponse::success($mappedAnnouncements);
    }

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $priority = $request->input('priority');
        $search = $request->input('search');

        $query = Announcement::active()
            ->with(['creator:id,name,department,position', 'creator.roles']);

        // Show announcements based on user role and enrollments
        // 1. GLOBAL: Visible to everyone
        // 2. UNIT (Admin): Visible if target_role matches user role or 'all'
        // 3. CLASS (Instructor): Visible if user enrolled in target_classes or 'all'
        
        $userRole = $request->user()->hasRole('instructor') ? 'instructor' : 'learner';
        
        // Get user's enrolled course IDs if they are a student
        $enrolledCourseIds = [];
        if ($userRole === 'learner') {
            $enrolledCourseIds = $request->user()->courses()->pluck('courses.id')->toArray();
        }

        $query->where(function ($q) use ($userRole, $enrolledCourseIds) {
            // 1. Global Announcements (from super-admin, visible to everyone)
            $q->where('scope', 'global')

            // 2. Unit Announcements
              ->orWhere(function ($unitQ) use ($userRole, $enrolledCourseIds) {
                  $unitQ->where('scope', 'unit')
                        ->where(function ($filterQ) use ($userRole, $enrolledCourseIds) {

                            // A. Role-based Targeting (from Admin, no target_classes)
                            //    Match if target_role = 'all' or matches current user role
                            // Include 'user' as legacy alias for 'learner' (backward compat)
                            $roleMatches = ['all', $userRole];
                            if ($userRole === 'learner') {
                                $roleMatches[] = 'user'; // legacy: old announcements stored as 'user'
                            }

                            $filterQ->where(function ($roleQ) use ($roleMatches) {
                                $roleQ->whereNull('target_classes')
                                      ->whereIn('target_role', $roleMatches);
                            });

                            // B. Class-based Targeting (from Instructor, has target_classes)
                            //    ONLY for learners — instructor should NOT see these
                            if ($userRole === 'learner' && !empty($enrolledCourseIds)) {
                                $filterQ->orWhere(function ($classQ) use ($enrolledCourseIds) {
                                    $classQ->whereNotNull('target_classes')
                                           ->where(function ($jsonQ) use ($enrolledCourseIds) {
                                               // 'all' classes targeted
                                               $jsonQ->whereJsonContains('target_classes', 'all');

                                               // OR specific enrolled course targeted
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

        if ($priority) {
            $query->where('priority', $priority);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Sort: Penting > Umum > Informasi
        $announcements = $query->orderByRaw("CASE priority 
            WHEN 'penting' THEN 1 
            WHEN 'umum' THEN 2 
            WHEN 'informasi' THEN 3 
            ELSE 4 END ASC")
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);

        // Transform data to include creator info properly
        $mappedAnnouncements = $announcements->getCollection()->map(function ($ann) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'content' => $ann->content,
                'priority' => $ann->priority,
                'created_by_id' => $ann->created_by,
                'created_by' => $ann->creator?->name ?? 'Unknown',
                'creator_role' => $ann->creator?->role_label ?? 'User',
                'creator' => [ // Maintain compatibility for current frontend code
                    'id' => $ann->creator?->id,
                    'name' => $ann->creator?->name ?? 'Unknown',
                    'department' => $ann->creator?->department,
                    'position' => $ann->creator?->position,
                ],
                'created_at' => $ann->created_at,
                'published_at' => $ann->published_at,
                'is_active' => $ann->is_active,
            ];
        });

        return ApiResponse::success([
            'announcements' => $mappedAnnouncements,
            'pagination' => [
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'per_page' => $announcements->perPage(),
                'total' => $announcements->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $announcement = Announcement::active()
            ->with(['creator:id,name,department,position', 'creator.roles'])
            ->findOrFail($id);

        $mappedAnnouncement = [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'priority' => $announcement->priority,
            'created_by_id' => $announcement->created_by,
            'created_by' => $announcement->creator?->name ?? 'Unknown',
            'creator_role' => $announcement->creator?->role_label ?? 'User',
            'creator' => [
                'id' => $announcement->creator?->id,
                'name' => $announcement->creator?->name ?? 'Unknown',
                'department' => $announcement->creator?->department,
                'position' => $announcement->creator?->position,
            ],
            'created_at' => $announcement->created_at,
            'published_at' => $announcement->published_at,
            'is_active' => $announcement->is_active,
            'target_classes' => $announcement->target_classes,
            'target_role' => $announcement->target_role,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'announcement' => $mappedAnnouncement,
            ],
        ], 200);
    }

    public function latest(Request $request)
    {
        $limit = $request->input('limit', 5);

        $user = $request->user();
        $userRole = $user?->hasRole('instructor') ? 'instructor' : 'learner';

        $enrolledCourseIds = [];
        if ($userRole === 'learner' && $user) {
            $enrolledCourseIds = $user->courses()->pluck('courses.id')->toArray();
        }

        $announcements = Announcement::active()
            ->with(['creator:id,name,department,position', 'creator.roles'])
            ->where(function ($q) use ($userRole, $enrolledCourseIds) {
                // Global: visible to everyone
                $q->where('scope', 'global')
                  // Unit: role-based (from admin)
                  ->orWhere(function ($unitQ) use ($userRole, $enrolledCourseIds) {
                      $unitQ->where('scope', 'unit')
                            ->where(function ($filterQ) use ($userRole, $enrolledCourseIds) {
                                $roleMatches = ['all', $userRole];
                                if ($userRole === 'learner') {
                                    $roleMatches[] = 'user'; // legacy: old announcements stored as 'user'
                                }

                                $filterQ->where(function ($roleQ) use ($roleMatches) {
                                    $roleQ->whereNull('target_classes')
                                          ->whereIn('target_role', $roleMatches);
                                });

                                // Class-based: only for learners
                                if ($userRole === 'learner' && !empty($enrolledCourseIds)) {
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
            })
            ->orderByRaw("CASE priority 
            WHEN 'penting' THEN 1 
            WHEN 'umum' THEN 2 
            WHEN 'informasi' THEN 3 
            ELSE 4 END ASC")
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        $mappedAnnouncements = $announcements->map(function ($ann) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'content' => $ann->content,
                'priority' => $ann->priority,
                'created_by_id' => $ann->created_by,
                'created_by' => $ann->creator?->name ?? 'Unknown',
                'creator_role' => $ann->creator?->role_label ?? 'User',
                'creator' => [
                    'id' => $ann->creator?->id,
                    'name' => $ann->creator?->name ?? 'Unknown',
                    'department' => $ann->creator?->department,
                    'position' => $ann->creator?->position,
                ],
                'created_at' => $ann->created_at,
                'published_at' => $ann->published_at,
                'is_active' => $ann->is_active,
                'views' => $ann->views_count ?? 0,
                'status' => strtolower($this->getAnnouncementStatus($ann)),
            ];
        });



        return ApiResponse::success($mappedAnnouncements);
    }

    /**
     * Get all announcements (for super admin tracking)
     */
    public function getAllAnnouncements(Request $request)
    {
        $query = Announcement::with(['creator:id,name,department,position', 'creator.roles'])
            ->orderBy('created_at', 'desc');

        $announcements = $query->get()->map(function ($ann) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'content' => $ann->content,
                'priority' => $ann->priority,
                'created_by_id' => $ann->created_by, // Add ID for filtering
                'created_by' => $ann->creator?->name ?? 'Unknown',
                'creator_role' => $ann->creator?->role_label ?? 'Super Admin', // Fallback to Super Admin since this is SA route
                'created_at' => $ann->created_at,
                'published_at' => $ann->published_at,
                'views' => $ann->views_count ?? 0,
                'is_active' => $ann->is_active,
                'status' => strtolower($this->getAnnouncementStatus($ann)),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $announcements,
            'total' => count($announcements),
        ]);
    }

    /**
     * Create global announcement (Super Admin Only)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:3',
            'priority' => 'required|in:informasi,umum,penting',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        try {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'priority' => $validated['priority'],
                'scope' => 'global',
                'broadcast_to' => null, // null = everyone
                'created_by' => auth()->id(),
                'published_at' => $validated['published_at'] ?? now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => true,
            ]);

            // Broadcast real-time event
            broadcast(new AnnouncementCreated($announcement))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman global berhasil dibuat',
                'data' => $announcement,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pengumuman',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get announcement tracking analytics
     */
    public function getAnnouncementTracking(Request $request)
    {
        // Single query for all counts — replaces 5 separate queries
        $counts = Announcement::selectRaw("
            count(*) as total,
            sum(case when is_active = 1 then 1 else 0 end) as active_total,
            sum(case when priority = 'penting' then 1 else 0 end) as cnt_penting,
            sum(case when priority = 'umum' then 1 else 0 end) as cnt_umum,
            sum(case when priority = 'informasi' then 1 else 0 end) as cnt_informasi
        ")->first();

        $stats = [
            'total_announcements' => (int) ($counts->total ?? 0),
            'active_announcements' => (int) ($counts->active_total ?? 0),
            'by_priority' => [
                'penting'    => (int) ($counts->cnt_penting ?? 0),
                'umum'       => (int) ($counts->cnt_umum ?? 0),
                'informasi'  => (int) ($counts->cnt_informasi ?? 0),
            ],
            'by_creator_role' => Announcement::with('creator.roles')
                ->get()
                ->groupBy(function ($ann) {
                    return $ann->creator?->role_label ?? 'Unknown';
                })
                ->map->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Helper: Get announcement status
     */
    private function getAnnouncementStatus($announcement)
    {
        if (!$announcement->is_active) return 'Inactive';
        if ($announcement->published_at && $announcement->published_at > now()) return 'Scheduled';
        if ($announcement->expires_at && $announcement->expires_at < now()) return 'Expired';
        return 'Published';
    }

    /**
     * Update announcement
     */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:3',
            'priority' => 'required|in:informasi,umum,penting',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        $announcement->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil diperbarui',
            'data' => $announcement,
        ]);
    }

    /**
     * Delete announcement
     */
    public function destroy($id)
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dihapus',
        ]);
    }
}
