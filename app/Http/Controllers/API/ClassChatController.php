<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClassMessage;
use App\Events\NewClassMessage;
use App\Events\QuestionAnswered;
use App\Utils\FileValidator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClassChatController extends Controller
{
    /**
     * Check if user is enrolled to the class
     */
    private function checkEnrollment(Request $request, int $classId): ?JsonResponse
    {
        $enrollment = \App\Models\CourseEnrollment::where('course_id', $classId)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak terdaftar di kelas ini'
            ], 403);
        }

        return null; // No error, user is enrolled
    }

    /**
     * Get messages for a class
     * By default loads ALL messages to preserve chat history
     */
    public function index(Request $request, int $classId): JsonResponse
    {
        // Check enrollment first
        if ($error = $this->checkEnrollment($request, $classId)) {
            return $error;
        }

        $perPage = $request->input('per_page', 0); // 0 = all messages (no pagination)

        $query = ClassMessage::forClass($classId)
            ->with([
                'user:id,name,avatar',
                'answeredByUser:id,name',
                'replyToMessage.user:id,name',
                'mentionedUser:id,name'
            ])
            ->orderBy('created_at', 'asc'); // Oldest first

        // If per_page is 0 or not specified, load ALL messages
        if ($perPage <= 0) {
            $messages = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $messages,
                    'total' => $messages->count(),
                ],
            ]);
        }

        // Otherwise use pagination
        $messages = $query->paginate($perPage);

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
        // Check enrollment first
        if ($error = $this->checkEnrollment($request, $classId)) {
            return $error;
        }

        try {
            $validated = $request->validate([
                'message' => 'nullable|string|max:2000',
                'message_type' => 'in:discussion,question',
                'reply_to' => 'nullable|exists:class_messages,id',
                'mentioned_user_id' => 'nullable|exists:users,id',
                'image' => 'nullable|file',
            ]);

            // At least message or image must be present
            if (empty($validated['message']) && !$request->hasFile('image')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pesan atau gambar harus diisi',
                ], 422);
            }

            // Validate file if uploaded
            if ($request->hasFile('image')) {
                $fileValidation = FileValidator::validate($request->file('image'));
                if (!$fileValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'File validation failed',
                        'errors' => $fileValidation['errors']
                    ], 422);
                }
            }

            // Validate reply_to belongs to same class
            if (!empty($validated['reply_to'])) {
                $replyMessage = ClassMessage::find($validated['reply_to']);
                if (!$replyMessage || $replyMessage->class_id != $classId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Pesan yang direply tidak valid',
                    ], 422);
                }
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $sanitizedName = FileValidator::sanitizeFilename($file->getClientOriginalName());
                $extension = $file->getClientOriginalExtension();
                $filename = pathinfo($sanitizedName, PATHINFO_FILENAME) . '_' . time() . '.' . $extension;
                $imagePath = $file->storeAs('class-chat-images', $filename, 'public');
            }

            $message = ClassMessage::create([
                'class_id' => $classId,
                'user_id' => $request->user()->id,
                'message' => $validated['message'] ?? '[Gambar]',
                'message_type' => $validated['message_type'] ?? 'discussion',
                'reply_to' => $validated['reply_to'] ?? null,
                'mentioned_user_id' => $validated['mentioned_user_id'] ?? null,
                'image_path' => $imagePath,
            ]);

            // CRITICAL: Load relationships before broadcasting to prevent queue serialization errors
            $message->load(['user:id,name,avatar']);

            // Load optional relationships only if they exist
            if ($message->reply_to) {
                $message->load('replyToMessage.user:id,name');
            }
            if ($message->mentioned_user_id) {
                $message->load('mentionedUser:id,name');
            }

            // Broadcast to class channel
            broadcast(new NewClassMessage($message))->toOthers();

            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Pesan berhasil dikirim',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('ClassChat store error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'class_id' => $classId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengirim pesan. Silakan coba lagi.',
            ], 500);
        }
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

        // Broadcast question answered event for real-time stats update
        broadcast(new QuestionAnswered($message));

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

        // Get course IDs where user is instructor
        $classIds = \DB::table('courses')
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
