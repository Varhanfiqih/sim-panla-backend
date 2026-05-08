<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradePeriod extends Model
{
    protected $fillable = ['name', 'is_active', 'start_date', 'end_date'];

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }
}
