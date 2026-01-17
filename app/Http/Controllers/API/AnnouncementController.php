<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $priority = $request->input('priority');

        $query = Announcement::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->with('creator:id,name,department,position');

        if ($priority) {
            $query->where('priority', $priority);
        }

        $announcements = $query->orderBy('priority', 'desc')
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
        $announcement = Announcement::where('is_active', true)
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

        $announcements = Announcement::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')
                  ->orWhere('published_at', '<=', now());
            })
            ->with('creator:id,name,department,position')
            ->orderBy('priority', 'desc')
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
}
