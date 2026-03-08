<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'nip_guru',
        'student_id',
        'type',        // sakit | izin | keluarga
        'start_date',
        'end_date',
        'keterangan',
        'foto_path',
        'status',      // pending | approved | rejected
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'nip_guru', 'nip');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    // Hitung total hari izin
    public function getTotalHariAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Scope query untuk mencari izin yang aktif pada tanggal tertentu.
     */
    public function scopeActiveOnDate($query, $date)
    {
        return $query->where('status', 'approved')
                     ->whereDate('start_date', '<=', $date)
                     ->whereDate('end_date', '>=', $date);
    }
}
