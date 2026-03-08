<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use App\Models\Student;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\AttendanceExport;
use App\Exports\JournalExport;

class ReportExport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationLabel = 'Ekspor Laporan';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $title = 'Ekspor Rekapan';
    protected static string $view = 'filament.pages.report-export';

    public $attendance_date;
    public $attendance_class;
    public $journal_date;
    public $classes;

    public function mount()
    {
        $this->attendance_date = Carbon::today()->toDateString();
        $this->journal_date = Carbon::today()->toDateString();
        $this->classes = \App\Models\SchoolClass::orderBy('id')->get();
    }

    protected function getActions(): array
    {
        return [];
    }

    public function exportAttendance()
    {
        $filename = 'Kehadiran_' . $this->attendance_date . ($this->attendance_class ? '_' . $this->attendance_class : '') . '.xlsx';
        return Excel::download(new AttendanceExport($this->attendance_date, $this->attendance_class), $filename);
    }

    public function exportJournal()
    {
        $filename = 'Jurnal_' . $this->journal_date . '.xlsx';
        return Excel::download(new JournalExport($this->journal_date), $filename);
    }
}
