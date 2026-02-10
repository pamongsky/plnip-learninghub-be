<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Events\AnnouncementCreated;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    /**
     * Get all announcements for admin (inbox + sent)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Priority sorting: Penting > Umum > Informasi
        $prioritySort = "CASE priority 
            WHEN 'penting' THEN 1 
            WHEN 'umum' THEN 2 
            WHEN 'informasi' THEN 3 
            ELSE 4 END ASC";
        
        // Admin sees:
        // - Global (from super-admin)
        // - Unit announcements (from admin, including other admins)
        // - Instructor's class-targeted announcements (for tracking/monitoring)
        $all = Announcement::with(['creator:id,name,department,position', 'creator.roles'])
            ->active()
            ->orderByRaw($prioritySort)
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(function ($ann) {
                $ann->creator_role = $ann->creator?->role_label ?? 'Administrator';
                return $ann;
            });
        
        // Get announcements created by this admin
        $mine = Announcement::with(['creator:id,name,department,position', 'creator.roles'])
            ->where('created_by', $user->id)
            ->orderByRaw($prioritySort)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ann) {
                $ann->creator_role = $ann->creator?->role_label ?? 'Administrator';
                return $ann;
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'all' => $all,
                'mine' => $mine,
            ],
        ]);
    }

    /**
     * Create announcement (Admin can target User/Instructor/Both)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:3',
            'priority' => 'required|in:informasi,umum,penting',
            'target_role' => 'required|in:all,user,instructor',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        try {
            $announcement = Announcement::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'priority' => $validated['priority'],
                'scope' => 'unit', // Admin creates unit-level announcements
                'target_role' => $validated['target_role'],
                'created_by' => auth()->id(),
                'published_at' => $validated['published_at'] ?? now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => true,
            ]);

            // Broadcast real-time event
            broadcast(new AnnouncementCreated($announcement))->toOthers();

            return response()->json([
                'success' => true,
                'message' => 'Pengumuman berhasil dibuat',
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
     * Update announcement
     */
    public function update(Request $request, $id)
    {
        $announcement = Announcement::where('created_by', auth()->id())
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:3',
            'priority' => 'required|in:informasi,umum,penting',
            'target_role' => 'required|in:all,user,instructor',
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
        $announcement = Announcement::where('created_by', auth()->id())
            ->findOrFail($id);

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pengumuman berhasil dihapus',
        ]);
    }
}
