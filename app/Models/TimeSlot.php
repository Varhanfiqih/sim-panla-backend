<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeSlot extends Model
{
    protected $fillable = [
        'start_time', 'end_time', 'type'
    ];

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'time_slot_id', 'id');
    }
}
