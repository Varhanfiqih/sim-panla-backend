<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $scheduleId;
    public $teacherName;
    public $className;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct($scheduleId, $teacherName, $className)
    {
        $this->scheduleId = $scheduleId;
        $this->teacherName = $teacherName;
        $this->className = $className;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('ClassMonitoring'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'journal.submitted';
    }
}
