<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportReply;
use App\Events\NewSupportReply;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupportTicketController extends Controller
{
    /**
     * Get all tickets (Admin) or user's tickets
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SupportTicket::with(['user:id,name,email,avatar', 'assignedAdmin:id,name']);

        // If not admin, only show user's own tickets
        if (!$user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin', 'Super Admin'])) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        $tickets = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Create a new support ticket
     */
    public function store(Request $request): JsonResponse
    {
        // Debug log
        \Log::info('Support ticket creation request:', [
            'all_data' => $request->all(),
            'category' => $request->input('category'),
            'has_files' => $request->hasFile('attachments'),
        ]);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:5000',
            'category' => 'required|in:technical,learning,certificate,payment,other,schedule,content,student,certification,coordination',
            'priority' => 'in:low,medium,high,urgent',
            'class_id' => 'nullable|integer',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpeg,png,jpg,pdf,doc,docx',
        ]);

        \Log::info('Validated data:', $validated);

        $attachments = $this->handleAttachments($request);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'priority' => $validated['priority'] ?? 'medium',
            'class_id' => $validated['class_id'] ?? null,
            'status' => 'open',
            'attachments' => $attachments,
        ]);

        \Log::info('Ticket created:', $ticket->toArray());

        $ticket->load(['user:id,name,email,avatar']);

        return response()->json([
            'success' => true,
            'data' => $ticket,
            'message' => 'Tiket berhasil dibuat dengan nomor ' . $ticket->ticket_number,
        ], 201);
    }

    /**
     * Get a specific ticket with replies
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $ticket = SupportTicket::with([
            'user:id,name,email,avatar',
            'assignedAdmin:id,name,avatar',
            'replies.user:id,name,avatar',
        ])->findOrFail($id);

        // Check authorization
        if (
            !$user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin', 'Super Admin'])
            && (int) $ticket->user_id !== (int) $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Add a reply to a ticket
     */
    public function addReply(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $ticket = SupportTicket::findOrFail($id);

        // Check authorization
        if (
            !$user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin', 'Super Admin'])
            && (int) $ticket->user_id !== (int) $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240|mimes:jpeg,png,jpg,pdf,doc,docx',
        ]);

        $attachments = $this->handleAttachments($request);

        $isAdmin = $user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin', 'Super Admin']);

        $reply = SupportReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
            'is_admin_reply' => $isAdmin,
            'attachments' => $attachments,
        ]);

        // If admin replies and ticket is open, set to in_progress
        if ($isAdmin && $ticket->status === 'open') {
            $ticket->update([
                'status' => 'in_progress',
                'assigned_to' => $user->id,
            ]);
        }

        $reply->load(['user:id,name,avatar']);

        // Broadcast new reply
        broadcast(new NewSupportReply($reply))->toOthers();

        return response()->json([
            'success' => true,
            'data' => $reply,
            'message' => 'Balasan berhasil dikirim',
        ], 201);
    }

    /**
     * Update ticket status (Admin only)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin', 'Super Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'resolved') {
            $updateData['resolved_at'] = now();
        }

        if (in_array($validated['status'], ['in_progress', 'resolved']) && !$ticket->assigned_to) {
            $updateData['assigned_to'] = $user->id;
        }

        $ticket->update($updateData);

        return response()->json([
            'success' => true,
            'data' => $ticket->fresh(['user:id,name,email', 'assignedAdmin:id,name']),
            'message' => 'Status tiket berhasil diubah',
        ]);
    }

    /**
     * Get ticket statistics (Admin only)
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['admin', 'Admin', 'super-admin', 'superadmin', 'Super Admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $stats = [
            'total' => SupportTicket::count(),
            'open' => SupportTicket::open()->count(),
            'in_progress' => SupportTicket::inProgress()->count(),
            'resolved' => SupportTicket::resolved()->count(),
            'today' => SupportTicket::today()->count(),
            'urgent' => SupportTicket::open()->byPriority('urgent')->count(),
            'high' => SupportTicket::open()->byPriority('high')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Handle attachment uploads
     */
    private function handleAttachments(Request $request): ?array
    {
        if (!$request->hasFile('attachments')) {
            return null;
        }

        $paths = [];
        $files = $request->file('attachments');

        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            $path = $file->store('tickets', 'public');
            $paths[] = '/storage/' . $path;
        }

        return $paths;
    }
}
