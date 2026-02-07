<?php

namespace App\Http\Controllers\API;

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

        // Helper for priority sorting
        $prioritySort = "CASE priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'medium' THEN 3 
            WHEN 'low' THEN 4 
            ELSE 5 END ASC";

        // Get all active announcements
        $all = Announcement::with(['creator:id,name,department,position', 'creator.roles'])
            ->active()
            ->where(function ($q) {
                $q->where('scope', 'global')
                  ->orWhere('scope', 'unit');
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

        return response()->json([
            'success' => true,
            'data' => [
                'all' => $all,
                'mine' => $mine,
            ],
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
            'priority' => 'required|in:low,normal,medium,high,urgent',
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
                'target_role' => 'user', // Instructor always targets users (students)
                'target_classes' => json_encode($targetClasses),
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
            'priority' => 'required|in:low,medium,high',
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
            $validated['target_classes'] = json_encode($targetClasses);
        }

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
