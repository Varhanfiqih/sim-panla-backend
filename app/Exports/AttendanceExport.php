<?php

namespace App\Exports;

use App\Models\Attendance;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected $date;
    protected $classId;

    public function __construct($date = null, $classId = null)
    {
        $this->date = $date;
        $this->classId = $classId;
    }

    public function collection()
    {
        $query = Attendance::with('student');

        if ($this->date) {
            $query->whereDate('created_at', $this->date);
        }

        if ($this->classId) {
            $query->whereHas('student', function ($q) {
                $q->where('class_id', $this->classId);
            });
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Siswa',
            'Kelas',
            'Status',
            'Catatan/Kegiatan',
        ];
    }

    public function map($attendance): array
    {
        return [
            $attendance->created_at->format('d/m/Y H:i:s'),
            $attendance->student->name ?? '-',
            $attendance->student->class_id ?? '-',
            $attendance->presensi,
            $attendance->kegiatan ?? '-',
        ];
    }
}
