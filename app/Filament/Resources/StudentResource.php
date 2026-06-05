<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Models\Student;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Siswa';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?int $navigationSort = 2;
    protected static ?string $modelLabel = 'Siswa';
    protected static ?string $pluralModelLabel = 'Siswa';

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    /** Super Admin & Admin IT bisa melihat. Kepala Sekolah read-only. */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isStaff() || $user?->isKepsek();
    }

    /** Hanya Super Admin & Admin IT yang bisa membuat data baru. */
    public static function canCreate(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** Hanya Super Admin & Admin IT yang bisa edit. */
    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** Hanya Super Admin yang bisa hapus permanen. */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** Hanya Super Admin yang bisa bulk delete. */
    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {


        return $form->schema([
            Forms\Components\Section::make('Data Siswa')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('nisn')
                        ->label('NISN')
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('nis')
                        ->label('NIS')
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Lengkap')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Forms\Components\Select::make('class_id')
                        ->label('Kelas')
                        ->relationship('schoolClass', 'id')
                        ->required()
                        ->searchable(),
                    Forms\Components\Select::make('gender')
                        ->label('Jenis Kelamin')
                        ->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nisn')->label('NISN')->searchable(),
                Tables\Columns\TextColumn::make('nis')->label('NIS')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('class_id')->label('Kelas')->sortable(),
                Tables\Columns\BadgeColumn::make('gender')->label('JK')
                    ->colors(['primary' => 'L', 'pink' => 'P']),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('class_id')
                    ->relationship('schoolClass', 'id')
                    ->label('Filter Kelas'),
            ])
            ->defaultSort('class_id')
            ->actions([
                Tables\Actions\Action::make('qr_code')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('primary')
                    ->modalHeading(fn ($record) => 'QR Code - ' . $record->name)
                    ->modalContent(fn ($record) => new \Illuminate\Support\HtmlString('
                        <div class="flex flex-col items-center justify-center text-center pb-4">
                            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm inline-block min-w-[300px]">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($record->qr_code ?? $record->nisn) . '" alt="QR Code" class="mx-auto mb-3" style="width: 250px; height: 250px;">
                                <div class="font-bold text-lg text-black mt-1 uppercase">' . e($record->name) . '</div>
                                <div class="text-sm text-gray-500">NISN: ' . e($record->nisn) . '</div>
                            </div>
                        </div>
                    '))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(fn (\Filament\Actions\StaticAction $action) => $action->label('Tutup'))
                    ->extraModalFooterActions([
                        Tables\Actions\Action::make('download_pdf')
                            ->label('Download PDF')
                            ->color('warning')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->url(
                                fn (Student $record): string => route(
                                    'admin.students.qr.download',
                                    ['student' => $record],
                                    absolute: false,
                                ),
                            )
                            ->openUrlInNewTab()
                    ]),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('download_qr_pdf')
                        ->label('Download QR PDF (Terpilih)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('size')
                                ->label('Ukuran Per Halaman')
                                ->options([
                                    '20' => '20 / Halaman (Kecil)',
                                    '12' => '12 / Halaman (Sedang)',
                                    '6' => '6 / Halaman (Besar)',
                                ])
                                ->default('12')
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $size = $data['size'];
                            $pdf = app('dompdf.wrapper')->loadView('pdf.student-qr-bulk', [
                                'students' => $records,
                                'size' => $size,
                            ]);
                            return response()->streamDownload(
                                fn () => print($pdf->output()),
                                'QR_Code_Siswa_' . date('Ymd_His') . '.pdf'
                            );
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStudents::route('/'),
        ];
    }
}
