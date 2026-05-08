<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeCategory extends Model
{
    protected $fillable = ['name', 'weight', 'description'];

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }
}
