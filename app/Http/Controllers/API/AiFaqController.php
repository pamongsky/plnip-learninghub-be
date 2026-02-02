<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AiFaq;
use App\Models\AiFaqSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AiFaqController extends Controller
{
    /**
     * Get all FAQs with filters
     */
    public function index(Request $request)
    {
        $query = AiFaq::with(['creator', 'updater']);

        // Filters
        if ($request->has('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->is_verified);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('question', 'LIKE', "%{$search}%")
                  ->orWhere('answer', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'usage_count');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $faqs = $query->paginate($perPage);

        return response()->json($faqs);
    }

    /**
     * Get FAQ statistics
     */
    public function statistics()
    {
        $stats = [
            'total_faqs' => AiFaq::count(),
            'active_faqs' => AiFaq::where('is_active', true)->count(),
            'verified_faqs' => AiFaq::where('is_verified', true)->count(),
            'pending_suggestions' => AiFaqSuggestion::where('status', 'pending')->count(),
            'total_usage' => AiFaq::sum('usage_count'),
            'avg_confidence' => round(AiFaq::avg('confidence_score'), 1),
            'by_category' => AiFaq::select('category', DB::raw('count(*) as count'))
                ->groupBy('category')
                ->get(),
            'top_used' => AiFaq::orderByDesc('usage_count')
                ->take(5)
                ->get(['id', 'question', 'usage_count', 'success_count', 'failure_count']),
            'recent_activity' => AiFaq::whereNotNull('last_used_at')
                ->orderByDesc('last_used_at')
                ->take(5)
                ->get(['id', 'question', 'last_used_at', 'usage_count']),
        ];

        return response()->json($stats);
    }

    /**
     * Store new FAQ
     */
    public function store(Request $request)
    {
        $request->validate([
            'category' => 'required|string|in:login,course,technical,general',
            'question' => 'required|string|max:500',
            'question_variations' => 'nullable|array',
            'answer' => 'required|string',
            'answer_short' => 'nullable|string|max:1000',
            'confidence_score' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
        ]);

        $faq = AiFaq::create([
            'category' => $request->category,
            'question' => $request->question,
            'question_variations' => $request->question_variations,
            'answer' => $request->answer,
            'answer_short' => $request->answer_short ?: substr($request->answer, 0, 1000),
            'confidence_score' => $request->confidence_score ?? 70,
            'is_active' => $request->is_active ?? true,
            'is_verified' => $request->is_verified ?? false,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'FAQ berhasil dibuat',
            'data' => $faq
        ], 201);
    }

    /**
     * Show single FAQ
     */
    public function show($id)
    {
        $faq = AiFaq::with(['creator', 'updater', 'analytics' => function($q) {
            $q->latest()->take(10);
        }])->findOrFail($id);

        return response()->json($faq);
    }

    /**
     * Update FAQ
     */
    public function update(Request $request, $id)
    {
        $faq = AiFaq::findOrFail($id);

        $request->validate([
            'category' => 'sometimes|string|in:login,course,technical,general',
            'question' => 'sometimes|string|max:500',
            'question_variations' => 'nullable|array',
            'answer' => 'sometimes|string',
            'answer_short' => 'nullable|string|max:1000',
            'confidence_score' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
            'is_verified' => 'boolean',
        ]);

        $faq->update(array_merge(
            $request->only([
                'category', 'question', 'question_variations', 
                'answer', 'answer_short', 'confidence_score',
                'is_active', 'is_verified'
            ]),
            ['updated_by' => Auth::id()]
        ));

        return response()->json([
            'message' => 'FAQ berhasil diupdate',
            'data' => $faq
        ]);
    }

    /**
     * Delete FAQ
     */
    public function destroy($id)
    {
        $faq = AiFaq::findOrFail($id);
        $faq->delete();

        return response()->json([
            'message' => 'FAQ berhasil dihapus'
        ]);
    }

    /**
     * Bulk activate/deactivate
     */
    public function bulkToggle(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'is_active' => 'required|boolean',
        ]);

        AiFaq::whereIn('id', $request->ids)
            ->update([
                'is_active' => $request->is_active,
                'updated_by' => Auth::id()
            ]);

        return response()->json([
            'message' => 'Status FAQ berhasil diupdate'
        ]);
    }

    /**
     * Get FAQ suggestions (auto-learned)
     */
    public function suggestions(Request $request)
    {
        $query = AiFaqSuggestion::with('reviewer');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $suggestions = $query->orderByDesc('occurrence_count')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($suggestions);
    }

    /**
     * Approve suggestion → Convert to FAQ
     */
    public function approveSuggestion(Request $request, $id)
    {
        $suggestion = AiFaqSuggestion::findOrFail($id);

        $request->validate([
            'category' => 'required|string|in:login,course,technical,general',
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
        ]);

        // Create FAQ from suggestion
        $faq = AiFaq::create([
            'category' => $request->category,
            'question' => $request->question ?? $suggestion->question,
            'answer' => $request->answer ?? $suggestion->answer,
            'answer_short' => substr($request->answer ?? $suggestion->answer, 0, 1000),
            'confidence_score' => 60, // Start with medium confidence
            'is_active' => true,
            'is_verified' => true,
            'created_by' => Auth::id(),
        ]);

        // Update suggestion status
        $suggestion->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Suggestion berhasil diapprove dan dijadikan FAQ',
            'data' => $faq
        ]);
    }

    /**
     * Reject suggestion
     */
    public function rejectSuggestion(Request $request, $id)
    {
        $suggestion = AiFaqSuggestion::findOrFail($id);

        $request->validate([
            'review_notes' => 'nullable|string',
        ]);

        $suggestion->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $request->review_notes,
        ]);

        return response()->json([
            'message' => 'Suggestion berhasil direject'
        ]);
    }
}
