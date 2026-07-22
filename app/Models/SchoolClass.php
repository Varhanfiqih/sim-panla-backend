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

    /**
     * @return array<string, string>
     */
    public static function singleClassOptions(): array
    {
        return static::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (SchoolClass $schoolClass): bool => preg_match('/^[789][A-Z]$/', (string) $schoolClass->id) === 1)
            ->mapWithKeys(fn (SchoolClass $schoolClass): array => [
                (string) $schoolClass->id => (string) $schoolClass->id,
            ])
            ->toArray();
    }

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
