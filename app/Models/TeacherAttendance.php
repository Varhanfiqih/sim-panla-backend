<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAttendance extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'status',
        'reason',
        'description',
        'attachment',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
