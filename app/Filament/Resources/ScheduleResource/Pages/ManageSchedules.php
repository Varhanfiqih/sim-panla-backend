<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ScheduleExcelImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManageSchedules extends ManageRecords
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('grid')
                ->label('Lihat Jadwal Grid')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->url(ScheduleResource::getUrl('calendar')),
            Actions\Action::make('new_schedule')
                ->label('New Jadwal')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->isStaff() ?? false)
                ->modalHeading('New Jadwal')
                ->modalSubmitActionLabel('Simpan')
                ->form([
                    Toggle::make('use_excel_import')
                        ->label('Import dari Excel')
                        ->helperText('Aktifkan jika ingin membuat atau memperbarui banyak jadwal dari file .xlsx.')
                        ->live(),
                    Section::make('File Import Jadwal')
                        ->visible(fn (Get $get): bool => (bool) $get('use_excel_import'))
                        ->schema([
                            FileUpload::make('file')
                                ->label('File Jadwal Excel')
                                ->helperText('Gunakan kolom: Hari, Kelas, Jam Mulai, Jam Selesai, Mata Pelajaran, NIP Guru, Keterangan.')
                                ->disk('local')
                                ->directory('schedule-imports')
                                ->acceptedFileTypes([
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->required(fn (Get $get): bool => (bool) $get('use_excel_import')),
                        ]),
                    Section::make('Detail Jadwal')
                        ->columns(2)
                        ->hidden(fn (Get $get): bool => (bool) $get('use_excel_import'))
                        ->schema([
                            Select::make('teacher_id')
                                ->label('Guru Pengajar')
                                ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'nip')->toArray())
                                ->searchable()
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import')),
                            Select::make('class_id')
                                ->label('Kelas')
                                ->options(fn (): array => SchoolClass::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import')),
                            Select::make('subject_id')
                                ->label('Mata Pelajaran')
                                ->options(fn (): array => Subject::query()->orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import')),
                            Select::make('day_of_week')
                                ->label('Hari')
                                ->options([
                                    'SENIN' => 'Senin',
                                    'SELASA' => 'Selasa',
                                    'RABU' => 'Rabu',
                                    'KAMIS' => 'Kamis',
                                    'JUMAT' => 'Jumat',
                                    'SABTU' => 'Sabtu',
                                ])
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import')),
                            Select::make('time_slot_id')
                                ->label('Jam Pelajaran Ke- / Waktu')
                                ->options(fn (): array => TimeSlot::query()
                                    ->orderBy('start_time')
                                    ->get()
                                    ->mapWithKeys(fn (TimeSlot $slot): array => [
                                        $slot->id => "Jam Ke-{$slot->id} (".substr((string) $slot->start_time, 0, 5).' - '.substr((string) $slot->end_time, 0, 5).')',
                                    ])
                                    ->toArray())
                                ->searchable()
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import'))
                                ->createOptionForm([
                                    TimePicker::make('start_time')->label('Jam Mulai')->seconds(false)->required(),
                                    TimePicker::make('end_time')->label('Jam Selesai')->seconds(false)->required(),
                                    Select::make('type')->label('Jenis Kegiatan')
                                        ->options(['KBM' => 'KBM (Mengajar)', 'Istirahat' => 'Istirahat'])
                                        ->default('KBM')
                                        ->required(),
                                ])
                                ->createOptionUsing(fn (array $data): int => TimeSlot::create($data)->id),
                            TextInput::make('keterangan')
                                ->label('Keterangan / Catatan Tambahan')
                                ->maxLength(255),
                        ]),
                ])
                ->action(function (array $data): void {
                    if (! ($data['use_excel_import'] ?? false)) {
                        Schedule::create([
                            'teacher_id' => $data['teacher_id'],
                            'class_id' => $data['class_id'],
                            'subject_id' => $data['subject_id'],
                            'day_of_week' => $data['day_of_week'],
                            'time_slot_id' => $data['time_slot_id'],
                            'keterangan' => $data['keterangan'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Jadwal berhasil dibuat')
                            ->success()
                            ->send();

                        return;
                    }

                    $storedPath = (string) $data['file'];
                    $summary = app(ScheduleExcelImportService::class)
                        ->import(Storage::disk('local')->path($storedPath));

                    Storage::disk('local')->delete($storedPath);

                    $errorPreview = collect($summary['errors'])
                        ->take(5)
                        ->implode("\n");

                    Notification::make()
                        ->title('Import jadwal selesai')
                        ->body(
                            "Dibuat: {$summary['created']}\n".
                            "Diperbarui: {$summary['updated']}\n".
                            "Dilewati: {$summary['skipped']}".
                            ($errorPreview ? "\n\n{$errorPreview}" : '')
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}
