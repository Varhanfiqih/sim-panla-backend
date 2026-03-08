<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvalAssignment extends Model
{
    protected $fillable = [
        'schedule_id',
        'replacement_teacher_id',
        'date',
        'status',
    ];

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    public function replacementTeacher()
    {
        return $this->belongsTo(User::class, 'replacement_teacher_id', 'nip');
    }
}
