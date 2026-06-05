<?php

namespace App\Filament\Pages;

use App\Models\Attendance;
use App\Models\GradeCategory;
use App\Models\GradePeriod;
use App\Models\Journal;
use App\Models\SchoolClass;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Services\ExcelReportService;
use Carbon\Carbon;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Ekspor Laporan';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $title = 'Ekspor Rekapan';
    protected static string $view = 'filament.pages.report-export';

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    /** Super Admin, Admin IT, dan Kepala Sekolah bisa melakukan export laporan */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->isKepsek() || $user?->isStaff();
    }

    public $attendance_date;
    public $attendance_class;
    public $journal_date;
    public $classes;
    public $grade_period;
    public $grade_class;
    public $grade_subject;
    public $grade_category;
    public $gradePeriods;
    public $subjects;
    public $gradeCategories;

    public function mount()
    {
        $this->attendance_date = Carbon::today()->toDateString();
        $this->journal_date = Carbon::today()->toDateString();
        $this->classes = SchoolClass::orderBy('name')->get();
        $this->gradePeriods = GradePeriod::orderByDesc('is_active')->orderByDesc('start_date')->get();
        $this->subjects = Subject::orderBy('name')->get();
        $this->gradeCategories = GradeCategory::orderBy('sort_order')->orderBy('name')->get();
        $this->grade_period = $this->gradePeriods->firstWhere('is_active', true)?->id
            ?? $this->gradePeriods->first()?->id;
    }

    protected function getActions(): array
    {
        return [];
    }

    public function exportAttendance(): BinaryFileResponse
    {
        $excel = app(ExcelReportService::class);

        $this->validate([
            'attendance_date' => ['required', 'date'],
            'attendance_class' => ['nullable', 'exists:school_classes,id'],
        ]);

        $attendances = Attendance::query()
            ->with(['student.schoolClass'])
            ->whereDate('created_at', $this->attendance_date)
            ->when(
                filled($this->attendance_class),
                fn ($query) => $query->whereHas(
                    'student',
                    fn ($studentQuery) => $studentQuery->where('class_id', $this->attendance_class),
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
            'Waktu',
            'NIS',
            'NISN',
            'Nama Siswa',
            'Kelas',
            'Status',
            'Kegiatan',
            'Keterangan',
        ], $attendances);

        $filename = 'Kehadiran_' . $this->attendance_date . ($this->attendance_class ? '_' . $this->attendance_class : '') . '.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function exportJournal(): BinaryFileResponse
    {
        $excel = app(ExcelReportService::class);

        $this->validate([
            'journal_date' => ['required', 'date'],
        ]);

        $rows = collect();
        $journals = Journal::query()
            ->with([
                'teacher',
                'schedule.subject',
                'schedule.schoolClass',
                'schedule.timeSlot',
                'studentNotes.student',
            ])
            ->whereDate('created_at', $this->journal_date)
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
            'Waktu',
            'Guru Pengajar',
            'Mata Pelajaran',
            'Kelas',
            'Jam',
            'Materi',
            'Kebersihan',
            'Inval',
            'Siswa Absen',
            'Status Siswa',
            'Catatan',
        ], $rows);

        $filename = 'Jurnal_' . $this->journal_date . '.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    public function exportGrades(): BinaryFileResponse
    {
        $excel = app(ExcelReportService::class);

        $this->validate([
            'grade_period' => ['required', 'exists:grade_periods,id'],
            'grade_class' => ['nullable', 'exists:school_classes,id'],
            'grade_subject' => ['nullable', 'exists:subjects,id'],
            'grade_category' => ['nullable', 'exists:grade_categories,id'],
        ]);

        $grades = StudentGrade::query()
            ->with(['student.schoolClass', 'subject', 'gradeCategory', 'gradePeriod'])
            ->where('grade_period_id', $this->grade_period)
            ->when(
                filled($this->grade_class),
                fn ($query) => $query->whereHas(
                    'student',
                    fn ($studentQuery) => $studentQuery->where('class_id', $this->grade_class),
                ),
            )
            ->when(filled($this->grade_subject), fn ($query) => $query->where('subject_id', $this->grade_subject))
            ->when(filled($this->grade_category), fn ($query) => $query->where('grade_category_id', $this->grade_category))
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
            'NIS',
            'NISN',
            'Nama Siswa',
            'Kelas',
            'Mata Pelajaran',
            'Kategori',
            'Item Ke',
            'Periode',
            'Semester',
            'Tahun Ajaran',
            'Nilai',
            'Catatan',
        ], $grades);

        $period = GradePeriod::find($this->grade_period);
        $filename = 'Nilai_Siswa_'.str_replace([' ', '/'], '_', $period?->name ?? 'periode').'.xlsx';

        return response()->download($path, $filename)->deleteFileAfterSend(true);
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
