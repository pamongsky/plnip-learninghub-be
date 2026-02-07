<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Events\AnnouncementCreated;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
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
        
        $userRole = $request->user()->hasRole('instructor') ? 'instructor' : 'user'; // Simplified role check
        // Or better: $userRole = $request->user()->roles->first()?->name ?? 'user';
        
        // Get user's enrolled course IDs if they are a student
        $enrolledCourseIds = [];
        if ($userRole === 'user') {
            $enrolledCourseIds = $request->user()->courses()->pluck('courses.id')->toArray();
        }

        $query->where(function ($q) use ($userRole, $enrolledCourseIds) {
            // 1. Global Announcements
            $q->where('scope', 'global')
            
            // 2. Local/Unit/Class Announcements
              ->orWhere(function ($unitQ) use ($userRole, $enrolledCourseIds) {
                  $unitQ->where('scope', 'unit')
                        ->where(function ($filterQ) use ($userRole, $enrolledCourseIds) {
                            
                            // A. Role-based Targeting (usually from Admin)
                            // If target_classes IS NULL, it's likely a role-based announcement
                            $filterQ->where(function ($roleQ) use ($userRole) {
                                $roleQ->whereNull('target_classes')
                                      ->whereIn('target_role', ['all', $userRole]);
                            })
                            
                            // B. Class-based Targeting (usually from Instructor)
                            // Only relevant if user is a student (role='user') and has enrollments
                            ->orWhere(function ($classQ) use ($enrolledCourseIds) {
                                $classQ->whereNotNull('target_classes')
                                       ->where(function ($jsonQ) use ($enrolledCourseIds) {
                                           // Check if 'all' classes targeted
                                           $jsonQ->whereJsonContains('target_classes', 'all');
                                           
                                           // OR Check if enrolled course is targeted
                                           if (!empty($enrolledCourseIds)) {
                                               foreach ($enrolledCourseIds as $courseId) {
                                                    // Check string or int type in JSON
                                                   $jsonQ->orWhereJsonContains('target_classes', $courseId)
                                                         ->orWhereJsonContains('target_classes', (string)$courseId);
                                               }
                                           }
                                       });
                            });
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

        // Fix: Custom sort agar Urgent paling atas
        $announcements = $query->orderByRaw("CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 END ASC")
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

        return response()->json([
            'success' => true,
            'data' => [
                'announcements' => $mappedAnnouncements,
                'pagination' => [
                    'current_page' => $announcements->currentPage(),
                    'last_page' => $announcements->lastPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                ],
            ],
        ], 200);
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

        $announcements = Announcement::active()
            ->with(['creator:id,name,department,position', 'creator.roles'])
            ->where(function ($q) {
                $q->where('scope', 'global')
                  ->orWhere('scope', 'unit');
            })
            ->orderByRaw("CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 END ASC")
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
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'announcements' => $mappedAnnouncements,
            ],
        ], 200);
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
                'status' => $this->getAnnouncementStatus($ann),
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
    public function createGlobalAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:3',
            'priority' => 'required|in:low,normal,medium,high,urgent',
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
        $stats = [
            'total_announcements' => Announcement::count(),
            'active_announcements' => Announcement::where('is_active', true)->count(),
            'by_priority' => [
                'high' => Announcement::where('priority', 'high')->count(),
                'medium' => Announcement::where('priority', 'medium')->count(),
                'low' => Announcement::where('priority', 'low')->count(),
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
            'priority' => 'required|in:low,normal,medium,high,urgent',
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
