<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherAttendanceResource\Pages;
use App\Filament\Resources\TeacherAttendanceResource\RelationManagers;
use App\Models\TeacherAttendance;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TeacherAttendanceResource extends Resource
{
    protected static ?string $model = TeacherAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'Kehadiran & Izin Guru';
    protected static ?string $navigationGroup = 'Operasional';
    protected static ?string $pluralModelLabel = 'Daftar Izin/Kehadiran Guru';
    protected static ?int $navigationSort = 3;

    // ─── Otorisasi Resource ───────────────────────────────────────────────────

    /** Super Admin, Admin IT, dan Kepala Sekolah bisa melihat. */
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isStaff() || $user?->isKepsek();
    }

    /** Admin IT bisa mencatat kehadiran guru (operasional harian). */
    public static function canCreate(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** Hanya Super Admin yang bisa hapus data kehadiran. */
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
        return $form
            ->schema([
                Forms\Components\Section::make('Formulir Izin / Kehadiran Guru')
                    ->description('Gunakan form ini untuk menandai guru yang sakit, izin, atau melakukan rekapitulasi darurat.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Nama Guru')
                            ->relationship('teacher', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\DatePicker::make('date')
                            ->label('Tanggal')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status Kehadiran')
                            ->options([
                                'hadir' => 'Telah Hadir',
                                'tidak_hadir' => 'Berhalangan (Sakit/Izin)',
                            ])
                            ->default('hadir')
                            ->required()
                            ->reactive(),
                        Forms\Components\TextInput::make('reason')
                            ->label('Alasan (Sakit/Izin/Dinas)')
                            ->visible(fn (Forms\Get $get) => $get('status') === 'tidak_hadir')
                            ->required(fn (Forms\Get $get) => $get('status') === 'tidak_hadir'),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan Tambahan')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Nama Guru')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'hadir' ? 'Hadir' : 'Absen / Izin')
                    ->color(fn ($state) => $state === 'hadir' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date')->label('Filter Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'],
                            fn (Builder $query, $date): Builder => $query->whereDate('date', '=', $date),
                        );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherAttendances::route('/'),
            'create' => Pages\CreateTeacherAttendance::route('/create'),
            'edit' => Pages\EditTeacherAttendance::route('/{record}/edit'),
        ];
    }
}
