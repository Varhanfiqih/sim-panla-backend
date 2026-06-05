<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGrade extends Model
{
    protected $fillable = [
        'student_nisn',
        'subject_id',
        'grade_category_id',
        'grade_period_id',
        'item_no',
        'score',
        'notes',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_nisn', 'nisn');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function gradeCategory()
    {
        return $this->belongsTo(GradeCategory::class);
    }

    public function gradePeriod()
    {
        return $this->belongsTo(GradePeriod::class);
    }
}
