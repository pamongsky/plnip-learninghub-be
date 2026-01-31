<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClassMessage;
use App\Events\NewClassMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClassChatController extends Controller
{
    /**
     * Get messages for a class
     */
    public function index(Request $request, int $classId): JsonResponse
    {
        $messages = ClassMessage::forClass($classId)
            ->with(['user:id,name,avatar', 'answeredByUser:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Send a new message
     */
    public function store(Request $request, int $classId): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'message_type' => 'in:discussion,question',
        ]);

        $message = ClassMessage::create([
            'class_id' => $classId,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'message_type' => $validated['message_type'] ?? 'discussion',
        ]);

        $message->load(['user:id,name,avatar']);

        // Broadcast to class channel
        broadcast(new NewClassMessage($message))->toOthers();

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Pesan berhasil dikirim',
        ], 201);
    }

    /**
     * Mark a question as answered (Instructor only)
     */
    public function markAsAnswered(Request $request, int $classId, int $messageId): JsonResponse
    {
        $message = ClassMessage::forClass($classId)
            ->where('id', $messageId)
            ->where('message_type', 'question')
            ->firstOrFail();

        $message->update([
            'is_answered' => true,
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);

        $message->load(['user:id,name,avatar', 'answeredByUser:id,name']);

        return response()->json([
            'success' => true,
            'data' => $message,
            'message' => 'Pertanyaan ditandai sudah dijawab',
        ]);
    }

    /**
     * Get questions for a class (for instructor)
     */
    public function getQuestions(Request $request, int $classId): JsonResponse
    {
        $questions = ClassMessage::forClass($classId)
            ->questions()
            ->with(['user:id,name,avatar', 'answeredByUser:id,name'])
            ->orderBy('is_answered', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }

    /**
     * Get today's question stats for instructor
     */
    public function getQuestionStats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        // Get class IDs where user is instructor
        // Assuming there's a class_instructor pivot or instructor_id in classes table
        // Adjust this query based on your actual schema
        $classIds = \DB::table('classes')
            ->where('instructor_id', $userId)
            ->pluck('id');

        $todayQuestions = ClassMessage::whereIn('class_id', $classIds)
            ->questions()
            ->today()
            ->count();

        $unansweredQuestions = ClassMessage::whereIn('class_id', $classIds)
            ->questions()
            ->unanswered()
            ->count();

        $totalQuestions = ClassMessage::whereIn('class_id', $classIds)
            ->questions()
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'today' => $todayQuestions,
                'unanswered' => $unansweredQuestions,
                'total' => $totalQuestions,
            ],
        ]);
    }

    /**
     * Get new messages after a specific message ID (for polling fallback)
     */
    public function getNewMessages(Request $request, int $classId): JsonResponse
    {
        $afterId = $request->query('after_id', 0);

        $messages = ClassMessage::forClass($classId)
            ->where('id', '>', $afterId)
            ->with(['user:id,name,avatar', 'answeredByUser:id,name'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $messages,
        ]);
    }
}
