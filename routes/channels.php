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
        return false;
    }
    // Allow ticket owner, assigned admin, or any admin/super-admin
    return $user->id === $ticket->user_id
        || $user->id === $ticket->assigned_to
        || $user->hasRole(['admin', 'super-admin']);
});
