<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAttendance extends Model
{
    protected $table = 'attendances'; // Numpang baca dari tabel absen asli
    public $timestamps = false; // Kita cuma numpang nge-View

    public function student()
    {
        return $this->belongsTo(Student::class, 'nisn_student', 'nisn');
    }
}
