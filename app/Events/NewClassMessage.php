<?php

namespace App\Events;

use App\Models\ClassMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewClassMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ClassMessage $message;

    /**
     * Create a new event instance.
     */
    public function __construct(ClassMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('class-chat.' . $this->message->class_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $data = [
            'id' => $this->message->id,
            'class_id' => $this->message->class_id,
            'user_id' => $this->message->user_id,
            'message' => $this->message->message,
            'message_type' => $this->message->message_type,
            'is_answered' => $this->message->is_answered,
            'reply_to' => $this->message->reply_to,
            'mentioned_user_id' => $this->message->mentioned_user_id,
            'image_path' => $this->message->image_path,
            'created_at' => $this->message->created_at->toISOString(),
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar' => $this->message->user->avatar,
            ],
        ];

        // Add reply message if exists and loaded
        if ($this->message->relationLoaded('replyToMessage') && $this->message->replyToMessage) {
            $data['replyToMessage'] = [
                'id' => $this->message->replyToMessage->id,
                'message' => $this->message->replyToMessage->message,
                'user' => $this->message->replyToMessage->user ? [
                    'id' => $this->message->replyToMessage->user->id,
                    'name' => $this->message->replyToMessage->user->name,
                ] : null,
            ];
        }

        // Add mentioned user if exists and loaded
        if ($this->message->relationLoaded('mentionedUser') && $this->message->mentionedUser) {
            $data['mentionedUser'] = [
                'id' => $this->message->mentionedUser->id,
                'name' => $this->message->mentionedUser->name,
            ];
        }

        return $data;
    }
}
