<?php

namespace App\Http\Controllers\API;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Events\AnnouncementCreated;
use Illuminate\Http\Request;

class InstructorAnnouncementController extends Controller
{
    /**
     * Get all announcements for instructor (all + mine)
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

        // Get announcements visible to instructor:
        // - Global (from super-admin, visible to everyone)
        // - Unit where target_role = 'all' or 'instructor' (from admin)
        // - NOT unit where target_role = 'learner' (that's learner-only)
        // - NOT class-targeted (those are for students, made by other instructors)
        $all = Announcement::with(['creator:id,name,department,position', 'creator.roles'])
            ->active()
            ->where(function ($q) {
                $q->where('scope', 'global')
                  ->orWhere(function ($unitQ) {
                      $unitQ->where('scope', 'unit')
                            ->whereNull('target_classes')
                            ->whereIn('target_role', ['all', 'instructor']);
                  });
            })
            ->orderByRaw($prioritySort)
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(function ($ann) {
                $ann->creator_role = $ann->creator?->role_label ?? 'Administrator';
                return $ann;
            });

        // Get announcements created by this instructor
        $mine = Announcement::with(['creator:id,name,department,position', 'creator.roles'])
            ->where('created_by', $user->id)
            ->orderByRaw($prioritySort)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ann) {
                $ann->creator_role = $ann->creator?->role_label ?? 'Instructor';
                return $ann;
            });

        return ApiResponse::success([
            'all' => $all,
            'mine' => $mine,
        ]);
    }

    /**
     * Create announcement (Instructor can target specific classes or all)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:3',
            'priority' => 'required|in:informasi,umum,penting',
            'target_classes' => 'nullable|array', // Can be ['all'] or [1, 2, 3]
            'target_classes.*' => 'nullable', // Don't validate individual IDs, allow 'all'
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        try {
            // Process target_classes
            $targetClasses = $validated['target_classes'] ?? ['all'];
            
            // If 'all' is in the array, just use 'all'
            if (in_array('all', $targetClasses)) {
                $targetClasses = ['all'];
            }

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'content' => $validated['content'],
                'priority' => $validated['priority'],
                'scope' => 'unit',
                'target_role' => 'learner', // Instructor always targets learners (students)
                'target_classes' => $targetClasses,
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
            'target_classes' => 'nullable|array',
            'target_classes.*' => 'nullable',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ]);

        // Process target_classes
        if (isset($validated['target_classes'])) {
            $targetClasses = $validated['target_classes'];
            if (in_array('all', $targetClasses)) {
                $targetClasses = ['all'];
            }
            $validated['target_classes'] = $targetClasses;
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
