<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\GradePeriod;
use App\Models\Journal;
use App\Models\StudentGrade;
use App\Services\ExcelReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    public function attendance(Request $request, ExcelReportService $excel): BinaryFileResponse
    {
        $this->authorizeAccess();
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'class_id' => ['nullable', 'exists:school_classes,id'],
        ]);

        $rows = Attendance::query()
            ->with('student.schoolClass')
            ->whereDate('created_at', $validated['date'])
            ->when(
                filled($validated['class_id'] ?? null),
                fn (Builder $query): Builder => $query->whereHas(
                    'student',
                    fn (Builder $studentQuery): Builder => $studentQuery->where('class_id', $validated['class_id']),
                ),
            )
            ->orderBy('created_at')
            ->get()
            ->map(fn (Attendance $attendance): array => [
                $attendance->created_at?->format('d/m/Y H:i:s') ?? '-',
                $attendance->student?->nis ?? '-',
                $attendance->student?->nisn ?? '-',
                $attendance->student?->name ?? '-',
                $attendance->student?->schoolClass?->name ?? $attendance->kelas ?? '-',
                ucfirst((string) $attendance->presensi),
                $attendance->kegiatan ?? '-',
                $attendance->keterangan ?? '-',
            ]);

        $path = $excel->create([
            'Waktu', 'NIS', 'NISN', 'Nama Siswa', 'Kelas',
            'Status', 'Kegiatan', 'Keterangan',
        ], $rows);

        $classSuffix = filled($validated['class_id'] ?? null) ? '_'.$validated['class_id'] : '';

        return response()
            ->download($path, 'Kehadiran_'.$validated['date'].$classSuffix.'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function journal(Request $request, ExcelReportService $excel): BinaryFileResponse
    {
        $this->authorizeAccess();
        $validated = $request->validate(['date' => ['required', 'date']]);
        $rows = collect();

        $journals = Journal::query()
            ->with([
                'teacher',
                'schedule.subject',
                'schedule.schoolClass',
                'schedule.timeSlot',
                'studentNotes.student',
            ])
            ->whereDate('created_at', $validated['date'])
            ->orderBy('created_at')
            ->get();

        foreach ($journals as $journal) {
            if ($journal->studentNotes->isEmpty()) {
                $rows->push($this->journalRow($journal));
                continue;
            }

            foreach ($journal->studentNotes as $note) {
                $rows->push($this->journalRow($journal, $note));
            }
        }

        $path = $excel->create([
            'Waktu', 'Guru Pengajar', 'Mata Pelajaran', 'Kelas', 'Jam',
            'Materi', 'Kebersihan', 'Inval', 'Siswa Absen', 'Status Siswa', 'Catatan',
        ], $rows);

        return response()
            ->download($path, 'Jurnal_'.$validated['date'].'.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function grades(Request $request, ExcelReportService $excel): BinaryFileResponse
    {
        $this->authorizeAccess();
        $validated = $request->validate([
            'period_id' => ['required', 'exists:grade_periods,id'],
            'class_id' => ['nullable', 'exists:school_classes,id'],
            'subject_id' => ['nullable', 'exists:subjects,id'],
            'category_id' => ['nullable', 'exists:grade_categories,id'],
        ]);

        $rows = StudentGrade::query()
            ->with(['student.schoolClass', 'subject', 'gradeCategory', 'gradePeriod'])
            ->where('grade_period_id', $validated['period_id'])
            ->when(
                filled($validated['class_id'] ?? null),
                fn (Builder $query): Builder => $query->whereHas(
                    'student',
                    fn (Builder $studentQuery): Builder => $studentQuery->where('class_id', $validated['class_id']),
                ),
            )
            ->when(
                filled($validated['subject_id'] ?? null),
                fn (Builder $query): Builder => $query->where('subject_id', $validated['subject_id']),
            )
            ->when(
                filled($validated['category_id'] ?? null),
                fn (Builder $query): Builder => $query->where('grade_category_id', $validated['category_id']),
            )
            ->orderBy('student_nisn')
            ->orderBy('subject_id')
            ->orderBy('grade_category_id')
            ->orderBy('item_no')
            ->get()
            ->map(fn (StudentGrade $grade): array => [
                $grade->student?->nis ?? '-',
                $grade->student_nisn,
                $grade->student?->name ?? '-',
                $grade->student?->schoolClass?->name ?? '-',
                $grade->subject?->name ?? '-',
                $grade->gradeCategory?->name ?? '-',
                $grade->item_no,
                $grade->gradePeriod?->name ?? '-',
                ucfirst((string) ($grade->gradePeriod?->semester ?? '-')),
                $grade->gradePeriod?->academic_year ?? '-',
                (float) $grade->score,
                $grade->notes ?? '-',
            ]);

        $path = $excel->create([
            'NIS', 'NISN', 'Nama Siswa', 'Kelas', 'Mata Pelajaran', 'Kategori',
            'Item Ke', 'Periode', 'Semester', 'Tahun Ajaran', 'Nilai', 'Catatan',
        ], $rows);

        $period = GradePeriod::find($validated['period_id']);
        $periodName = str_replace([' ', '/'], '_', $period?->name ?? 'periode');

        return response()
            ->download($path, 'Nilai_Siswa_'.$periodName.'.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isKepsek() || $user->isStaff()), 403);
    }

    private function journalRow(Journal $journal, $note = null): array
    {
        $timeSlot = $journal->schedule?->timeSlot;
        $time = $timeSlot
            ? substr((string) $timeSlot->start_time, 0, 5).' - '.substr((string) $timeSlot->end_time, 0, 5)
            : '-';

        return [
            $journal->created_at?->format('d/m/Y H:i:s') ?? '-',
            $journal->teacher?->name ?? '-',
            $journal->schedule?->subject?->name ?? ($journal->is_inval ? 'Inval' : '-'),
            $journal->schedule?->schoolClass?->name ?? '-',
            $time,
            $journal->material ?? '-',
            $journal->cleanliness === 'sudah_bersih' ? 'Bersih' : 'Kotor',
            $journal->is_inval ? 'Ya' : 'Tidak',
            $note?->student?->name ?? '-',
            $note?->note_type ?? '-',
            $note?->notes ?? '-',
        ];
    }
}
