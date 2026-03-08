<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'nisn', 'nis', 'class_id', 'name', 'gender', 'qr_code'
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'nisn_student', 'nisn');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id', 'id');
    }
}
