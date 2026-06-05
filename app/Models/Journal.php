<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $fillable = [
        'schedule_id',
        'user_id',       // NIP Guru
        'is_inval',
        'material',
        'cleanliness',   // 'sudah_bersih' | 'kotor'
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'is_inval' => 'boolean',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id', 'nip');
    }

    public function studentNotes()
    {
        return $this->hasMany(StudentNote::class, 'journal_id');
    }
}
