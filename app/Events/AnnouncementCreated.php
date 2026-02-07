<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnnouncementCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $announcement;

    public function __construct(Announcement $announcement)
    {
        $this->announcement = $announcement->load('creator:id,name,department,position');
    }

    public function broadcastOn(): Channel
    {
        return new Channel('announcements');
    }

    public function broadcastAs(): string
    {
        return 'announcement.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'content' => $this->announcement->content,
            'priority' => $this->announcement->priority,
            'created_by' => $this->announcement->creator?->name ?? 'Unknown',
            'creator_role' => $this->announcement->creator?->roles?->first()?->display_name ?? 'N/A',
            'created_at' => $this->announcement->created_at,
            'published_at' => $this->announcement->published_at,
        ];
    }
}
