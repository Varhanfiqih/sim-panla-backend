<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentNoteResource\Pages;
use App\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentNoteResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Izin Siswa';

    protected static ?string $modelLabel = 'Izin Siswa';

    protected static ?string $pluralModelLabel = 'Izin Siswa';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->isStaff() || $user?->isKepsek();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('student_name')
                ->label('Siswa')
                ->content(fn (Permission $record): string => $record->student?->name ?? '-'),
            Forms\Components\Placeholder::make('class_name')
                ->label('Kelas')
                ->content(fn (Permission $record): string => $record->student?->schoolClass?->name ?? '-'),
            Forms\Components\Placeholder::make('type_label')
                ->label('Jenis')
                ->content(fn (Permission $record): string => match ($record->type) {
                    'sakit' => 'Sakit',
                    'keluarga' => 'Izin Keluarga',
                    default => 'Izin',
                }),
            Forms\Components\Placeholder::make('date_range')
                ->label('Tanggal Izin')
                ->content(fn (Permission $record): string => sprintf(
                    '%s - %s (%d hari)',
                    $record->start_date?->format('d M Y'),
                    $record->end_date?->format('d M Y'),
                    $record->total_hari,
                )),
            Forms\Components\Placeholder::make('requester')
                ->label('Diajukan Oleh')
                ->content(fn (Permission $record): string => $record->guru?->name ?? '-'),
            Forms\Components\Placeholder::make('status_label')
                ->label('Status BK')
                ->content(fn (Permission $record): string => match ($record->status) {
                    'approved' => 'Disetujui BK',
                    'rejected' => 'Ditolak BK',
                    default => 'Menunggu Persetujuan BK',
                }),
            Forms\Components\Placeholder::make('notes')
                ->label('Keterangan')
                ->content(fn (Permission $record): string => $record->keterangan ?: '-')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description('Riwayat pengajuan izin siswa dari wali kelas dan hasil verifikasi BK.')
            ->columns([
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Siswa')
                    ->description(fn (Permission $record): string => 'NISN: '.($record->student?->nisn ?: '-'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.schoolClass.name')
                    ->label('Kelas')
                    ->badge()
                    ->placeholder('-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sakit' => 'Sakit',
                        'keluarga' => 'Izin Keluarga',
                        default => 'Izin',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sakit' => 'danger',
                        'keluarga' => 'info',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_hari')
                    ->label('Durasi')
                    ->state(fn (Permission $record): string => $record->total_hari.' hari'),
                Tables\Columns\TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->placeholder('-')
                    ->limit(45)
                    ->tooltip(fn (Permission $record): ?string => $record->keterangan),
                Tables\Columns\TextColumn::make('guru.name')
                    ->label('Diajukan Oleh')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status BK')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'approved' => 'Disetujui BK',
                        'rejected' => 'Ditolak BK',
                        default => 'Pending BK',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status BK')
                    ->options([
                        'pending' => 'Pending BK',
                        'approved' => 'Disetujui BK',
                        'rejected' => 'Ditolak BK',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options([
                        'sakit' => 'Sakit',
                        'izin' => 'Izin',
                        'keluarga' => 'Izin Keluarga',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail'),
                Tables\Actions\EditAction::make()
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('Jenis')
                            ->options([
                                'sakit' => 'Sakit',
                                'izin' => 'Izin',
                                'keluarga' => 'Izin Keluarga',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Tanggal Mulai')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Tanggal Selesai')
                            ->afterOrEqual('start_date')
                            ->required(),
                        Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->maxLength(500)
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada riwayat izin siswa')
            ->emptyStateDescription('Pengajuan dari wali kelas akan muncul di sini dan diperbarui setelah diproses BK.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student.schoolClass', 'guru']);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentNotes::route('/'),
        ];
    }
}
