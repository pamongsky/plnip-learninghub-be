<?php

namespace App\Http\Controllers\API;
use AppHelpersApiResponse;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\User;
use App\Events\NewDirectMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DirectMessageController extends Controller
{
    /**
     * Get all conversations for authenticated user
     */
    public function getConversations(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user->id)
            ->active()
            ->with(['userOne:id,name,email,role,avatar', 'userTwo:id,name,email,role,avatar'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        // Transform to include other participant info
        $conversations->getCollection()->transform(function ($conv) use ($user) {
            $other = $conv->getOtherParticipant($user->id);
            return [
                'id' => $conv->id,
                'type' => $conv->type,
                'participant' => [
                    'id' => $other->id,
                    'name' => $other->name,
                    'email' => $other->email,
                    'role' => $other->role,
                    'avatar' => $other->avatar,
                ],
                'last_message' => $conv->last_message,
                'last_message_at' => $conv->last_message_at,
                'unread_count' => $conv->getUnreadCountFor($user->id),
                'created_at' => $conv->created_at,
            ];
        });

        return response()->json($conversations);
    }

    /**
     * Get or create conversation with a user
     */
    public function startConversation(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $currentUser = $request->user();
        $targetUser = User::findOrFail($request->user_id);

        // Determine conversation type based on roles
        $type = Conversation::determineType($currentUser, $targetUser);

        if (!$type) {
            return response()->json([
                'message' => 'Komunikasi antara role ini tidak diizinkan.',
                'allowed_combinations' => [
                    'Admin ↔ Peserta',
                    'Instructor ↔ Admin',
                    'Super Admin ↔ Admin',
                ]
            ], 403);
        }

        // Find or create conversation
        $conversation = Conversation::findOrCreateBetween(
            $currentUser->id,
            $targetUser->id,
            $type
        );

        return response()->json([
            'id' => $conversation->id,
            'type' => $conversation->type,
            'participant' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'avatar' => $targetUser->avatar,
            ],
            'created_at' => $conversation->created_at,
        ], 201);
    }

    /**
     * Get messages in a conversation
     */
    public function getMessages(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check if user is participant
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Mark conversation as read
        $conversation->markAsReadFor($user->id);

        $messages = $conversation->messages()
            ->with('sender:id,name,avatar,role')
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'participant' => $conversation->getOtherParticipant($user->id),
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request, int $conversationId): JsonResponse
    {
        $request->validate([
            'message' => 'required_without:attachment|string|max:5000',
            'attachment' => 'nullable|file|max:10240', // 10MB max
        ]);

        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        // Check if user is participant
        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Handle attachment
        $attachmentData = [];
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('dm-attachments/' . $conversationId, 'public');
            $attachmentData = [
                'attachment_path' => $path,
                'attachment_name' => $file->getClientOriginalName(),
                'attachment_type' => $this->getAttachmentType($file->getMimeType()),
            ];
        }

        // Create message
        $message = DirectMessage::create([
            'conversation_id' => $conversationId,
            'sender_id' => $user->id,
            'message' => $request->message ?? '',
            ...$attachmentData,
        ]);

        // Load sender relation
        $message->load('sender:id,name,avatar,role');

        // Update conversation
        $conversation->updateLastMessage(
            $attachmentData ? '[Attachment] ' . ($request->message ?? $attachmentData['attachment_name']) : $request->message,
            $user->id
        );

        // Increment unread for recipient
        $recipientId = $conversation->user_one_id === $user->id 
            ? $conversation->user_two_id 
            : $conversation->user_one_id;
        $conversation->incrementUnreadFor($recipientId);

        // Broadcast event for real-time
        broadcast(new NewDirectMessage($message, $conversation))->toOthers();

        return response()->json($message, 201);
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request, int $conversationId): JsonResponse
    {
        $user = $request->user();
        $conversation = Conversation::findOrFail($conversationId);

        if (!$conversation->isParticipant($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $conversation->markAsReadFor($user->id);

        return response()->json(['message' => 'Marked as read']);
    }

    /**
     * Get unread count for all conversations
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalUnread = Conversation::forUser($user->id)
            ->active()
            ->get()
            ->sum(fn($conv) => $conv->getUnreadCountFor($user->id));

        return response()->json([
            'unread_count' => $totalUnread,
        ]);
    }

    /**
     * Get users that can be messaged based on role
     */
    public function getAvailableUsers(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;

        $query = User::query()->where('id', '!=', $user->id);

        // Filter based on current user's role
        switch ($role) {
            case 'superadmin':
                // Super admin can message admins
                $query->where('role', 'admin');
                break;
            
            case 'admin':
                // Admin can message: users (peserta), instructors, superadmins
                $query->whereIn('role', ['user', 'instructor', 'superadmin']);
                break;
            
            case 'instructor':
                // Instructor can message admins
                $query->where('role', 'admin');
                break;
            
            case 'user':
                // User (peserta) can message admins
                $query->where('role', 'admin');
                break;
            
            default:
                return response()->json(['users' => []]);
        }

        $users = $query->select('id', 'name', 'email', 'role', 'avatar')
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Search users to message
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        $user = $request->user();
        $searchQuery = $request->input('query');
        $role = $user->role;

        $query = User::query()
            ->where('id', '!=', $user->id)
            ->where(function ($q) use ($searchQuery) {
                $q->where('name', 'like', "%{$searchQuery}%")
                  ->orWhere('email', 'like', "%{$searchQuery}%");
            });

        // Apply role restrictions
        switch ($role) {
            case 'superadmin':
                $query->where('role', 'admin');
                break;
            case 'admin':
                $query->whereIn('role', ['user', 'instructor', 'superadmin']);
                break;
            case 'instructor':
                $query->where('role', 'admin');
                break;
            case 'user':
                $query->where('role', 'admin');
                break;
        }

        $users = $query->select('id', 'name', 'email', 'role', 'avatar')
            ->limit(10)
            ->get();

        return response()->json(['users' => $users]);
    }

    /**
     * Delete a message (soft delete)
     */
    public function deleteMessage(Request $request, int $messageId): JsonResponse
    {
        $user = $request->user();
        $message = DirectMessage::findOrFail($messageId);

        // Only sender can delete their message
        if ($message->sender_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $message->delete();

        return response()->json(['message' => 'Message deleted']);
    }

    /**
     * Get stats for dashboard
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::forUser($user->id)->active()->get();

        $totalConversations = $conversations->count();
        $totalUnread = $conversations->sum(fn($c) => $c->getUnreadCountFor($user->id));
        $activeToday = $conversations->filter(fn($c) => 
            $c->last_message_at && $c->last_message_at->isToday()
        )->count();

        $totalMessages = DirectMessage::whereIn('conversation_id', $conversations->pluck('id'))->count();

        return response()->json(['data' => [
            'total_conversations' => $totalConversations,
            'unread_messages' => $totalUnread,
            'active_today' => $activeToday,
            'total_messages' => $totalMessages,
        ]]);
    }

    /**
     * Determine attachment type from mime type
     */
    private function getAttachmentType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }
        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }
        if (str_contains($mimeType, 'spreadsheet') || str_contains($mimeType, 'excel')) {
            return 'spreadsheet';
        }
        if (str_contains($mimeType, 'document') || str_contains($mimeType, 'word')) {
            return 'document';
        }
        return 'file';
    }
}
