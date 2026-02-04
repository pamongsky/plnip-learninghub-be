<?php

namespace App\Events;

use App\Models\EscalationTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EscalationStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public EscalationTicket $ticket;

    /**
     * Create a new event instance.
     */
    public function __construct(EscalationTicket $ticket)
    {
        $this->ticket = $ticket;
        \Log::info('EscalationStatusUpdated event created', [
            'ticket_id' => $ticket->id,
            'status' => $ticket->status,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channel = 'escalation-ticket.' . $this->ticket->id;
        \Log::info('Broadcasting status update on channel: ' . $channel);
        return [
            new PrivateChannel($channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->ticket->id,
            'status' => $this->ticket->status,
            'resolved_at' => $this->ticket->resolved_at?->toISOString(),
            'superadmin_id' => $this->ticket->superadmin_id,
            'updated_at' => $this->ticket->updated_at->toISOString(),
        ];
    }
}
