<?php

namespace App\Events;

use App\Models\DirectMessage;
use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDirectMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DirectMessage $message;
    public Conversation $conversation;

    /**
     * Create a new event instance.
     */
    public function __construct(DirectMessage $message, Conversation $conversation)
    {
        $this->message = $message;
        $this->conversation = $conversation;
    }

    /**
     * Get the channels the event should broadcast on.
     * 
     * Broadcasts to both participants' personal channels
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // Broadcast to conversation channel
            new PrivateChannel('conversation.' . $this->conversation->id),
            
            // Also broadcast to each participant's inbox channel
            new PrivateChannel('user.' . $this->conversation->user_one_id . '.messages'),
            new PrivateChannel('user.' . $this->conversation->user_two_id . '.messages'),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->load('sender:id,name,avatar,role');

        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender' => [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                    'avatar' => $this->message->sender->avatar,
                    'role' => $this->message->sender->role,
                ],
                'message' => $this->message->message,
                'attachment_path' => $this->message->attachment_path,
                'attachment_name' => $this->message->attachment_name,
                'attachment_type' => $this->message->attachment_type,
                'created_at' => $this->message->created_at->toISOString(),
            ],
            'conversation' => [
                'id' => $this->conversation->id,
                'type' => $this->conversation->type,
                'last_message' => $this->conversation->last_message,
                'last_message_at' => $this->conversation->last_message_at?->toISOString(),
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'new.direct.message';
    }
}
