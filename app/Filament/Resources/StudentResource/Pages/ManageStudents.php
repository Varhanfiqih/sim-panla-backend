<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Filament\Resources\StudentResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentExcelImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Storage;

class ManageStudents extends ManageRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_student')
                ->label('New Siswa')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->visible(fn (): bool => auth()->user()?->isStaff() ?? false)
                ->modalHeading('New Siswa')
                ->modalSubmitActionLabel('Simpan')
                ->form([
                    Toggle::make('use_excel_import')
                        ->label('Import dari Excel')
                        ->helperText('Aktifkan jika ingin membuat atau memperbarui banyak siswa dari file .xlsx.')
                        ->live(),
                    Section::make('File Import Siswa')
                        ->visible(fn (Get $get): bool => (bool) $get('use_excel_import'))
                        ->schema([
                            FileUpload::make('file')
                                ->label('File Siswa Excel')
                                ->helperText('Mendukung format presensi per sheet kelas, atau tabel dengan kolom NISN/NIS, Nama Siswa, L/P, Kelas.')
                                ->disk('local')
                                ->directory('student-imports')
                                ->acceptedFileTypes([
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                ])
                                ->required(fn (Get $get): bool => (bool) $get('use_excel_import')),
                        ]),
                    Section::make('Data Siswa')
                        ->columns(2)
                        ->hidden(fn (Get $get): bool => (bool) $get('use_excel_import'))
                        ->schema([
                            TextInput::make('nisn')
                                ->label('NISN')
                                ->maxLength(20),
                            TextInput::make('nis')
                                ->label('NIS')
                                ->maxLength(20),
                            TextInput::make('name')
                                ->label('Nama Lengkap')
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import'))
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('class_id')
                                ->label('Kelas')
                                ->options(fn (): array => SchoolClass::singleClassOptions())
                                ->searchable()
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import')),
                            Select::make('gender')
                                ->label('Jenis Kelamin')
                                ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                                ->required(fn (Get $get): bool => ! (bool) $get('use_excel_import')),
                        ]),
                ])
                ->action(function (array $data): void {
                    if (! ($data['use_excel_import'] ?? false)) {
                        $nisn = filled($data['nisn'] ?? null)
                            ? (string) $data['nisn']
                            : (string) ($data['nis'] ?? '');

                        Student::create([
                            'nisn' => $nisn,
                            'nis' => $data['nis'] ?? null,
                            'name' => $data['name'],
                            'class_id' => $data['class_id'],
                            'gender' => $data['gender'],
                            'qr_code' => null,
                        ]);

                        Notification::make()
                            ->title('Siswa berhasil dibuat')
                            ->success()
                            ->send();

                        return;
                    }

                    $storedPath = (string) $data['file'];
                    $summary = app(StudentExcelImportService::class)
                        ->import(Storage::disk('local')->path($storedPath));

                    Storage::disk('local')->delete($storedPath);

                    $errorPreview = collect($summary['errors'])
                        ->take(5)
                        ->implode("\n");

                    Notification::make()
                        ->title('Import siswa selesai')
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
