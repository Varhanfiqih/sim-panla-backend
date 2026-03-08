<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $studentId;
    public $classId;
    public $newStatus;
    public $updatedBy; // 'BK' atau 'Guru'

    /**
     * Create a new event instance.
     */
    public function __construct($studentId, $classId, $newStatus, $updatedBy = 'System')
    {
        $this->studentId = $studentId;
        $this->classId = $classId;
        $this->newStatus = $newStatus;
        $this->updatedBy = $updatedBy;
    }

    /**
     * Get the channels the event should broadcast on.
     * Channel dinamis per Kelas agar HP Guru spesifik (misal kelas 7A) menerima update.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('Attendance.'.$this->classId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'student.status.updated';
    }
}
