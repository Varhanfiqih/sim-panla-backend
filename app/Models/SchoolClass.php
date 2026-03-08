<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id', 'name', 'homeroom_teacher_id'
    ];

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id', 'id');
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id', 'nip');
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'class_user', 'class_id', 'user_nip', 'id', 'nip');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'class_id', 'id');
    }
}
