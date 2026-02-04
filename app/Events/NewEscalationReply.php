<?php

namespace App\Events;

use App\Models\EscalationReply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewEscalationReply implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public EscalationReply $reply;

    /**
     * Create a new event instance.
     */
    public function __construct(EscalationReply $reply)
    {
        $this->reply = $reply;
        \Log::info('NewEscalationReply event created', [
            'escalation_ticket_id' => $reply->escalation_ticket_id,
            'reply_id' => $reply->id,
            'user_id' => $reply->user_id,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channel = 'escalation-ticket.' . $this->reply->escalation_ticket_id;
        \Log::info('Broadcasting on escalation channel: ' . $channel);
        return [
            new PrivateChannel($channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'reply.new';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->reply->id,
            'escalation_ticket_id' => $this->reply->escalation_ticket_id,
            'user_id' => $this->reply->user_id,
            'message' => $this->reply->message,
            'attachments' => $this->reply->attachments,
            'is_internal' => $this->reply->is_internal,
            'created_at' => $this->reply->created_at->toISOString(),
            'user' => [
                'id' => $this->reply->user->id,
                'name' => $this->reply->user->name,
                'email' => $this->reply->user->email,
                'role' => $this->reply->user->roles->first()?->name ?? 'user',
            ],
        ];
    }
}
