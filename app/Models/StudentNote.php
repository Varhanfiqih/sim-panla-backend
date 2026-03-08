<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentNote extends Model
{
    protected $fillable = [
        'journal_id',
        'student_id',
        'note_type',   // KBM_Hadir | KBM_Sakit_atau_Izin | KBM_Alpa
        'notes',
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
