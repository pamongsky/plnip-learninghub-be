<?php

namespace App\Http\Controllers\API;
use AppHelpersApiResponse;

use App\Http\Controllers\Controller;
use App\Models\EscalationTicket;
use App\Models\EscalationReply;
use App\Models\SupportTicket;
use App\Events\NewEscalationReply;
use App\Events\EscalationStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EscalationTicketController extends Controller
{
    /**
     * Get all escalation tickets (for admin or superadmin)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = EscalationTicket::with(['admin:id,name,email', 'superadmin:id,name,email', 'supportTicket:id,ticket_number,subject']);

        // Admin and Superadmin see all tickets (Team Collaboration Mode)
        // Previously: if ($user->role === 'admin') { $query->where('admin_id', $user->id); }

        // Filters
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        $query->withCount('replies');
        $query->orderBy('created_at', 'desc');

        $perPage = $request->input('per_page', 15);
        $tickets = $query->paginate($perPage);

        return response()->json([
            'tickets' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Get escalation ticket statistics
     */
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $query = EscalationTicket::query();

        if ($user->role === 'admin') {
            $query->where('admin_id', $user->id);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->where('status', 'open')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'resolved' => (clone $query)->where('status', 'resolved')->count(),
            'escalations' => (clone $query)->where('type', 'escalation')->count(),
            'standalone' => (clone $query)->where('type', 'standalone')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Check active escalation by support ticket ID
     */
    public function checkBySupportTicket($supportTicketId): JsonResponse
    {
        $escalation = EscalationTicket::where('support_ticket_id', $supportTicketId)
            ->whereIn('status', ['open', 'in_progress'])
            ->with(['admin:id,name', 'superadmin:id,name'])
            ->first();

        if (!$escalation) {
            return response()->json(['exists' => false]);
        }

        return response()->json([
            'exists' => true,
            'escalation' => $escalation
        ]);
    }

    // Duplicate method removed

    /**
     * Create standalone ticket (Admin creates new ticket to Super Admin)
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'category' => 'required|in:technical,learning,certificate,other',
        ]);

        $user = Auth::user();

        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return response()->json(['message' => 'Only admins can create escalation tickets'], 403);
        }

        $ticket = EscalationTicket::create([
            'admin_id' => $user->id,
            'type' => 'standalone',
            'subject' => $request->subject,
            'description' => $request->description,
            'priority' => $request->priority,
            'category' => $request->category,
            'status' => 'open',
        ]);

        return response()->json([
            'message' => 'Tiket berhasil dibuat',
            'ticket' => $ticket->load('admin:id,name,email'),
        ], 201);
    }

    /**
     * Escalate a support ticket to Super Admin
     */
    public function escalate(Request $request, $id): JsonResponse
    {
        $supportTicket = SupportTicket::findOrFail($id);

        $request->validate([
            'reason' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $user = Auth::user();

        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return response()->json(['message' => 'Only admins can escalate tickets'], 403);
        }

        // Check if already escalated
        $existingEscalation = EscalationTicket::where('support_ticket_id', $supportTicket->id)
            ->whereIn('status', ['open', 'in_progress'])
            ->first();

        if ($existingEscalation) {
            return response()->json(['message' => 'Tiket ini sudah dieskalasi'], 400);
        }

        DB::beginTransaction();
        try {
            $escalation = EscalationTicket::create([
                'admin_id' => $user->id,
                'support_ticket_id' => $supportTicket->id,
                'type' => 'escalation',
                'subject' => '[Eskalasi] ' . $supportTicket->subject,
                'description' => $request->reason,
                'priority' => $request->priority,
                'category' => $supportTicket->category ?? 'other',
                'status' => 'open',
                'escalated_at' => now(),
            ]);

            // Update support ticket status to in_progress (not escalated, to maintain simpler flow for users)
            $supportTicket->update(['status' => 'in_progress']);

            DB::commit();

            return response()->json([
                'message' => 'Tiket berhasil dieskalasi ke Super Admin',
                'escalation' => $escalation->load(['admin:id,name,email', 'supportTicket']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to escalate ticket', [
                'error' => $e->getMessage(),
                'ticket_id' => $supportTicket->id,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'message' => 'Gagal mengekskalasi tiket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single escalation ticket with details
     */
    public function show(EscalationTicket $escalationTicket): JsonResponse
    {
        $user = Auth::user();

        // Check access: Allow admin and super-admin
        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            \Illuminate\Support\Facades\Log::warning('Escalation access denied. User ID: ' . $user->id);
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $escalationTicket->load([
            'admin:id,name,email',
            'superadmin:id,name,email',
            'replies.user:id,name,email',
        ]);

        // If escalation type, load support ticket with its replies (history)
        if ($escalationTicket->type === 'escalation' && $escalationTicket->support_ticket_id) {
            $escalationTicket->load([
                'supportTicket' => function ($query) {
                    $query->with([
                        'user:id,name,email',
                        'replies' => function ($q) {
                            $q->with('user:id,name,email')->orderBy('created_at', 'asc');
                        }
                    ]);
                }
            ]);
        }

        return response()->json($escalationTicket);
    }

    /**
     * Add reply to escalation ticket
     */
    public function reply(Request $request, EscalationTicket $escalationTicket): JsonResponse
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = Auth::user();

        // Check access: Only Admin and Superadmin allowed
        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (in_array($escalationTicket->status, ['resolved', 'closed'])) {
            return response()->json(['message' => 'Tidak dapat membalas tiket yang sudah selesai atau ditutup'], 400);
        }

        $reply = EscalationReply::create([
            'escalation_ticket_id' => $escalationTicket->id,
            'user_id' => $user->id,
            'message' => $request->message,
            'attachments' => $this->handleAttachments($request),
        ]);

        // Load user relationship for broadcasting
        $reply->load('user:id,name,email');

        // Update ticket status if superadmin replies
        if ($user->hasRole('super-admin') && $escalationTicket->status === 'open') {
            $escalationTicket->update([
                'status' => 'in_progress',
                'superadmin_id' => $user->id,
            ]);
        }

        // Broadcast new reply to all connected users
        broadcast(new NewEscalationReply($reply))->toOthers();

        return response()->json([
            'message' => 'Balasan berhasil dikirim',
            'reply' => $reply,
        ], 201);
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

    /**
     * Update escalation ticket status
     */
    public function updateStatus(Request $request, EscalationTicket $escalationTicket): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $user = Auth::user();

        // Only superadmin can change status (or admin can close their own)
        if (!$user->hasRole('super-admin') && !($user->hasRole('admin') && $request->status === 'closed')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $updateData = ['status' => $request->status];

        if ($request->status === 'resolved') {
            $updateData['resolved_at'] = now();
        }

        if ($user->hasRole('super-admin') && !$escalationTicket->superadmin_id) {
            $updateData['superadmin_id'] = $user->id;
        }

        $escalationTicket->update($updateData);

        // Broadcast status update to all connected users
        broadcast(new EscalationStatusUpdated($escalationTicket))->toOthers();

        return response()->json([
            'message' => 'Status tiket berhasil diperbarui',
            'ticket' => $escalationTicket->fresh(),
        ]);
    }
}
