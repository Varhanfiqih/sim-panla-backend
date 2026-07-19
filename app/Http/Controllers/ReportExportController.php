<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\GradePeriod;
use App\Models\Journal;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\ExcelReportService;
use App\Services\PdfReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function attendancePdf(Request $request, PdfReportService $pdf): StreamedResponse
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
            ->values()
            ->map(fn (Attendance $attendance, int $index): array => [
                $index + 1,
                $attendance->created_at?->format('d/m/Y H:i:s') ?? '-',
                $attendance->student?->nis ?? '-',
                $attendance->student?->name ?? '-',
                $attendance->student?->schoolClass?->name ?? $attendance->kelas ?? '-',
                ucfirst((string) $attendance->presensi),
                $attendance->kegiatan ?? '-',
                $attendance->keterangan ?? '-',
            ]);

        $document = $pdf->create('LAPORAN KEHADIRAN PESERTA DIDIK', [
            ['label' => 'No', 'width' => '6%', 'align' => 'center'],
            ['label' => 'Waktu', 'width' => '17%'],
            ['label' => 'NIS', 'width' => '11%'],
            ['label' => 'Nama Siswa', 'width' => '20%'],
            ['label' => 'Kelas', 'width' => '9%', 'align' => 'center'],
            ['label' => 'Status', 'width' => '11%'],
            ['label' => 'Kegiatan', 'width' => '13%'],
            ['label' => 'Keterangan', 'width' => '13%'],
        ], $rows, [
            'subtitle' => 'Rekapitulasi | '.$this->dateText($validated['date']),
            'signatures' => $this->reportSignatures(),
        ]);

        $classSuffix = filled($validated['class_id'] ?? null) ? '_'.$validated['class_id'] : '';

        return $this->downloadPdf($document, 'Kehadiran_'.$validated['date'].$classSuffix.'.pdf');
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

    public function journalPdf(Request $request, PdfReportService $pdf): StreamedResponse
    {
        $this->authorizeAccess();
        $validated = $request->validate(['date' => ['required', 'date']]);

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

        $rows = $journals
            ->values()
            ->map(fn (Journal $journal, int $index): array => [
                $index + 1,
                $this->journalDateCell($journal),
                $journal->schedule?->schoolClass?->name ?? '-',
                [
                    'type' => 'subject',
                    'title' => $journal->schedule?->subject?->name ?? ($journal->is_inval ? 'Inval' : '-'),
                    'description' => $journal->material ?? '-',
                ],
                $this->journalAbsenceCell($journal),
            ]);

        $teacher = $journals->pluck('teacher')->filter()->unique('nip')->count() === 1
            ? $journals->first()?->teacher
            : null;

        $document = $pdf->create($teacher ? 'JURNAL GURU: '.$teacher->name : 'LAPORAN JURNAL MENGAJAR', [
            ['label' => 'No', 'width' => '6%', 'align' => 'center'],
            ['label' => "Hari,\nTanggal\nJam ke", 'width' => '18%'],
            ['label' => 'Kelas', 'width' => '9%', 'align' => 'center'],
            ['label' => "Mata Pelajaran\nKegiatan Pembelajaran", 'width' => '40%'],
            ['label' => "Ketidakhadiran\nPeserta Didik", 'width' => '27%'],
        ], $rows, [
            'subtitle' => $this->activePeriodSubtitle(),
            'signatures' => $this->reportSignatures(),
        ]);

        return $this->downloadPdf($document, 'Jurnal_'.$validated['date'].'.pdf');
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

    public function gradesPdf(Request $request, PdfReportService $pdf): StreamedResponse
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
            ->values()
            ->map(fn (StudentGrade $grade, int $index): array => [
                $index + 1,
                $grade->student?->nis ?? '-',
                $grade->student?->name ?? '-',
                $grade->student?->schoolClass?->name ?? '-',
                $grade->subject?->name ?? '-',
                $grade->gradeCategory?->name ?? '-',
                $grade->item_no,
                (float) $grade->score,
                $grade->notes ?? '-',
            ]);

        $period = GradePeriod::find($validated['period_id']);
        $periodName = str_replace([' ', '/'], '_', $period?->name ?? 'periode');

        $document = $pdf->create('LAPORAN NILAI SISWA', [
            ['label' => 'No', 'width' => '5%', 'align' => 'center'],
            ['label' => 'NIS', 'width' => '10%'],
            ['label' => 'Nama Siswa', 'width' => '21%'],
            ['label' => 'Kelas', 'width' => '8%', 'align' => 'center'],
            ['label' => 'Mata Pelajaran', 'width' => '17%'],
            ['label' => 'Kategori', 'width' => '13%'],
            ['label' => 'Item', 'width' => '7%', 'align' => 'center'],
            ['label' => 'Nilai', 'width' => '7%', 'align' => 'center'],
            ['label' => 'Catatan', 'width' => '12%'],
        ], $rows, [
            'subtitle' => trim(($period?->name ?? 'Periode').' | '.($period?->semester ? ucfirst((string) $period->semester) : '').' - Th. Ajaran '.($period?->academic_year ?? '-')),
            'signatures' => $this->reportSignatures(),
        ]);

        return $this->downloadPdf($document, 'Nilai_Siswa_'.$periodName.'.pdf');
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

    private function journalDateCell(Journal $journal): string
    {
        $date = $journal->created_at
            ? $journal->created_at->copy()->locale('id')->translatedFormat('l, j F Y')
            : '-';

        return $date."\n".$this->journalTime($journal);
    }

    private function journalTime(Journal $journal): string
    {
        $timeSlot = $journal->schedule?->timeSlot;

        if (! $timeSlot) {
            return 'Jam -';
        }

        return 'Jam '.substr((string) $timeSlot->start_time, 0, 5).' - '.substr((string) $timeSlot->end_time, 0, 5);
    }

    private function journalAbsenceCell(Journal $journal): string
    {
        $absences = $journal->studentNotes
            ->reject(fn ($note): bool => in_array($note->note_type, ['KBM_Hadir', 'Hadir'], true))
            ->map(fn ($note): string => trim(($note->student?->name ?? '-').' | '.$this->shortStatus($note->note_type)))
            ->filter()
            ->values();

        return $absences->isEmpty() ? 'NIHIL' : $absences->implode("\n");
    }

    private function shortStatus(?string $status): string
    {
        return match ($status) {
            'KBM_Sakit' => 'S',
            'KBM_Izin', 'KBM_Sakit_atau_Izin' => 'I',
            'KBM_Alpa', 'Alpa' => 'A',
            default => $status ? str_replace(['KBM_', '_'], ['', ' '], $status) : '-',
        };
    }

    private function activePeriodSubtitle(): string
    {
        $period = GradePeriod::query()
            ->where('is_active', true)
            ->first();

        if (! $period) {
            return 'Rekapitulasi Laporan Jurnal';
        }

        return trim('Rekapitulasi | Semester '.ucfirst((string) $period->semester).' - Th. Ajaran '.($period->academic_year ?? '-'));
    }

    private function reportSignatures(): array
    {
        return [
            'principal_name' => 'ARIF SYAIFURROHMAN, S.Pd',
            'principal_nip' => '198106202009041003',
            'place_date' => 'Kota Pasuruan, '.Carbon::today()->locale('id')->translatedFormat('j F Y'),
            'teacher_name' => 'LATIF ABDILLAH, S.Kom',
            'teacher_nip' => '198602012022211002',
        ];
    }

    private function dateText(string $date): string
    {
        return Carbon::parse($date)->locale('id')->translatedFormat('l, j F Y');
    }

    private function downloadPdf($pdf, string $filename): StreamedResponse
    {
        return response()->streamDownload(
            fn () => print($pdf->output()),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
