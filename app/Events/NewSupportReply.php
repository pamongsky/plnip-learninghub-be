<?php

namespace App\Events;

use App\Models\SupportReply;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSupportReply implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupportReply $reply;

    /**
     * Create a new event instance.
     */
    public function __construct(SupportReply $reply)
    {
        $this->reply = $reply;
        \Log::info('NewSupportReply event created', [
            'ticket_id' => $reply->ticket_id,
            'reply_id' => $reply->id,
            'user_id' => $reply->user_id,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channel = 'support-ticket.' . $this->reply->ticket_id;
        \Log::info('Broadcasting on channel: ' . $channel);
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
