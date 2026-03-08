<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'nip_guru', 'nisn_student', 'kelas', 'presensi', 'ekstra', 'kegiatan', 'keterangan'
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'nip_guru', 'nip');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'nisn_student', 'nisn');
    }
}
