<?php

namespace App\Events;

use App\Models\SupportReply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSupportReply implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportReply $reply;

    /**
     * Create a new event instance.
     */
    public function __construct(SupportReply $reply)
    {
        $this->reply = $reply;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('support-ticket.' . $this->reply->ticket_id),
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
            'ticket_id' => $this->reply->ticket_id,
            'user_id' => $this->reply->user_id,
            'message' => $this->reply->message,
            'is_admin_reply' => $this->reply->is_admin_reply,
            'created_at' => $this->reply->created_at->toISOString(),
            'user' => [
                'id' => $this->reply->user->id,
                'name' => $this->reply->user->name,
                'avatar' => $this->reply->user->avatar,
            ],
        ];
    }
}
