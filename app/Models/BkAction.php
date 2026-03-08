<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkAction extends Model
{
    protected $fillable = [
        'student_id',
        'handled_by_user_id',
        'action_type',        // panggilan_ortu | home_visit | surat_peringatan | konseling | lainnya
        'notes',
        'attachment_url',
        'status_sebelum',     // KBM_Alpa
        'status_sesudah',     // KBM_Sakit | KBM_Izin | tetap_alpa
        'tanggal_kejadian',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kejadian' => 'date',
        ];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by_user_id', 'nip');
    }
}
