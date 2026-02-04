<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Direct Message Channels
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    return $user->id === $conversation->user_one_id || $user->id === $conversation->user_two_id;
});

// User's personal messages channel
Broadcast::channel('user.{userId}.messages', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Class Chat Channel
Broadcast::channel('class-chat.{classId}', function ($user, $classId) {
    // User must be enrolled in the class or be instructor/admin
    // For now, allow all authenticated users
    return $user !== null;
});

// Support Ticket Channel
Broadcast::channel('support-ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\SupportTicket::find($ticketId);
    if (!$ticket) {
        \Log::warning('Broadcasting auth failed: Ticket not found', ['ticket_id' => $ticketId]);
        return false;
    }

    // Allow ticket owner, assigned admin, or any admin/super-admin
    $isOwner = (int) $user->id === (int) $ticket->user_id;
    $isAssigned = (int) $user->id === (int) $ticket->assigned_to;
    $isAdmin = $user->hasRole(['admin', 'super-admin']);

    \Log::info('Broadcasting auth check', [
        'user_id' => $user->id,
        'ticket_id' => $ticketId,
        'ticket_user_id' => $ticket->user_id,
        'assigned_to' => $ticket->assigned_to,
        'is_owner' => $isOwner,
        'is_assigned' => $isAssigned,
        'is_admin' => $isAdmin,
        'result' => $isOwner || $isAssigned || $isAdmin,
    ]);

    return $isOwner || $isAssigned || $isAdmin;
});

// Escalation Ticket Channel (Admin <-> Super Admin)
Broadcast::channel('escalation-ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\EscalationTicket::find($ticketId);
    if (!$ticket) {
        \Log::warning('Broadcasting auth failed: Escalation ticket not found', ['ticket_id' => $ticketId]);
        return false;
    }

    // Allow admin who created it or assigned super admin
    $isAdmin = (int) $user->id === (int) $ticket->admin_id;
    $isSuperAdmin = (int) $user->id === (int) $ticket->superadmin_id;
    $hasRole = $user->hasRole(['admin', 'super-admin']);

    \Log::info('Escalation broadcasting auth check', [
        'user_id' => $user->id,
        'ticket_id' => $ticketId,
        'admin_id' => $ticket->admin_id,
        'superadmin_id' => $ticket->superadmin_id,
        'is_admin' => $isAdmin,
        'is_superadmin' => $isSuperAdmin,
        'has_role' => $hasRole,
        'result' => $isAdmin || $isSuperAdmin || $hasRole,
    ]);

    return $isAdmin || $isSuperAdmin || $hasRole;
});
