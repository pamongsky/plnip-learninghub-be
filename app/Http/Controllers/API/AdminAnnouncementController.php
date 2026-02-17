<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
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
        
        return ApiResponse::success([
            'all' => $all,
            'mine' => $mine,
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
            'target_role' => 'required|in:all,learner,user,instructor',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        // Normalize legacy 'user' value to 'learner'
        if ($validated['target_role'] === 'user') {
            $validated['target_role'] = 'learner';
        }

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

            return ApiResponse::created($announcement, 'Pengumuman berhasil dibuat');
        } catch (\Exception $e) {
            return ApiResponse::serverError('Gagal membuat pengumuman', $e->getMessage());
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
            'target_role' => 'required|in:all,learner,user,instructor',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        // Normalize legacy 'user' value to 'learner'
        if ($validated['target_role'] === 'user') {
            $validated['target_role'] = 'learner';
        }

        $announcement->update($validated);

        return ApiResponse::updated($announcement, 'Pengumuman berhasil diperbarui');
    }

    /**
     * Delete announcement
     */
    public function destroy($id)
    {
        $announcement = Announcement::where('created_by', auth()->id())
            ->findOrFail($id);

        $announcement->delete();

        return ApiResponse::deleted('Pengumuman berhasil dihapus');
    }
}
