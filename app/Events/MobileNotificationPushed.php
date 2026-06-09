<?php

namespace App\Events;

use App\Models\MobileNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MobileNotificationPushed implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public MobileNotification $notification) {}

    public function broadcastOn(): array
    {
        return [new Channel('notifications.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'notification.pushed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'user_id' => $this->notification->user_id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'data' => $this->notification->data,
            'is_read' => false,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
