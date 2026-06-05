<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeCategory extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_repeatable',
        'max_item',
        'max_score',
        'weight',
        'sort_order',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_repeatable' => 'boolean',
            'max_item' => 'integer',
            'max_score' => 'decimal:2',
            'weight' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class);
    }
}
