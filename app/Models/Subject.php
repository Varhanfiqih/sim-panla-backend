<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $fillable = [
        'name'
    ];

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'subject_user', 'subject_id', 'user_nip', 'id', 'nip');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'subject_id', 'id');
    }
}
