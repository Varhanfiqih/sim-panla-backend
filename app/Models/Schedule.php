<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'day_of_week', 'class_id', 'time_slot_id', 'subject_id', 'teacher_id', 'keterangan'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id', 'nip');
    }

    public function timeSlot()
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id', 'id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subject_id', 'id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id', 'id');
    }
}
