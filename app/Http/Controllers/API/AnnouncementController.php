<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 15);
        $priority = $request->input('priority');
        $search = $request->input('search');

        $query = Announcement::active()
            ->with('creator:id,name,department,position');

        // Show only global or unit announcements
        $query->where(function ($q) {
            $q->where('scope', 'global')
              ->orWhere('scope', 'unit');
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

        // Fix: Custom sort agar High muncul paling atas (High=1, Medium=2, Low=3)
        $announcements = $query->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'announcements' => $announcements->items(),
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
            ->with('creator:id,name,department,position')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'announcement' => $announcement,
            ],
        ], 200);
    }

    public function latest(Request $request)
    {
        $limit = $request->input('limit', 5);

        $announcements = Announcement::active()
            ->with('creator:id,name,department,position')
            ->where(function ($q) {
                $q->where('scope', 'global')
                  ->orWhere('scope', 'unit');
            })
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END ASC")
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'announcements' => $announcements,
            ],
        ], 200);
    }

    /**
     * Get all announcements (for super admin tracking)
     */
    public function getAllAnnouncements(Request $request)
    {
        $query = Announcement::with('creator:id,name,department,position')
            ->orderBy('created_at', 'desc');

        $announcements = $query->get()->map(function ($ann) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'content' => $ann->content,
                'priority' => $ann->priority,
                'created_by' => $ann->creator?->name ?? 'Unknown',
                'creator_role' => $ann->creator?->roles?->first()?->display_name ?? 'N/A',
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
            'content' => 'required|string',
            'priority' => 'required|in:low,medium,high',
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
                    return $ann->creator?->roles?->first()?->display_name ?? 'Unknown';
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
}
