<?php

namespace App\Exports;

use App\Models\Journal;
use App\Models\StudentNote;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class JournalExport implements FromCollection, WithHeadings, WithMapping
{
    protected $date;

    public function __construct($date = null)
    {
        $this->date = $date;
    }

    public function collection()
    {
        $query = Journal::with(['teacher', 'schedule.subject', 'schedule.schoolClass', 'schedule.timeSlot', 'studentNotes.student']);

        if ($this->date) {
            $query->whereDate('created_at', $this->date);
        }

        // Kami akan mereturn baris berulang (satu baris per siswa absen, atau satu baris jurnal jika semua hadir)
        $rows = collect();
        foreach ($query->get() as $journal) {
            $absen = $journal->studentNotes;

            if ($absen->isEmpty()) {
                $rows->push((object)[
                    'journal' => $journal,
                    'student' => null,
                    'note'    => null,
                ]);
            } else {
                foreach ($absen as $note) {
                    $rows->push((object)[
                        'journal' => $journal,
                        'student' => $note->student,
                        'note'    => $note,
                    ]);
                }
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Guru Pengajar',
            'Mata Pelajaran',
            'Kelas & Jam',
            'Materi Jurnal',
            'Siswa Absen',
            'Status Siswa',
        ];
    }

    public function map($row): array
    {
        $j = $row->journal;
        return [
            $j->created_at->format('d/m/Y'),
            $j->teacher->name ?? '-',
            $j->schedule->subject->name ?? '-',
            ($j->schedule->schoolClass->id ?? '-') . ' (Jam Ke-' . ($j->schedule->timeSlot->id ?? '-') . ')',
            $j->materi_harian ?? '-',
            $row->student->name ?? '-',
            $row->note->note_type ?? '-',
        ];
    }
}
