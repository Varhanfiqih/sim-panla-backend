<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyAttendanceResource\Pages;
use App\Filament\Resources\DailyAttendanceResource\RelationManagers;
use App\Models\DailyAttendance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DailyAttendanceResource extends Resource
{
    protected static ?string $model = DailyAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Laporan Harian';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $pluralModelLabel = 'Rekapitulasi Presensi Harian';
    protected static ?int $navigationSort = 1;

    // Menonaktifkan tombol tambah data karena ini tabel rekap (Read-Only)
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Sulap Pivot: Kelompokkan baris berdasarkan hari & siswa
                $query->selectRaw('
                    CONCAT(DATE(created_at), "_", nisn_student) as id,
                    DATE(created_at) as tgl_absen,
                    nisn_student, 
                    MAX(CASE WHEN keterangan IN ("Masuk", "Terlambat") THEN created_at END) as jam_masuk,
                    MAX(CASE WHEN keterangan IN ("Masuk", "Terlambat") THEN keterangan END) as status_pagi,
                    MAX(CASE WHEN keterangan = "Sholat Dhuha" THEN created_at END) as jam_dhuha,
                    MAX(CASE WHEN keterangan = "Sholat Dzuhur" THEN created_at END) as jam_dzuhur,
                    MAX(CASE WHEN keterangan = "Pulang" THEN created_at END) as jam_pulang
                ')
                ->groupBy('tgl_absen', 'nisn_student')
                ->orderBy('tgl_absen', 'desc');
            })
            ->columns([
                Tables\Columns\TextColumn::make('tgl_absen')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.name')
                    ->label('Nama Siswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status_pagi')
                    ->label('Kedatangan Pagi')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'Masuk' => 'success',
                        'Terlambat' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('jam_masuk')
                    ->label('Jam Kedatangan')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : '-'),
                Tables\Columns\TextColumn::make('jam_dhuha')
                    ->label('Sholat Dhuha')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-'),
                Tables\Columns\TextColumn::make('jam_dzuhur')
                    ->label('Sholat Dzuhur')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i') : '-'),
                Tables\Columns\TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('H:i:s') : '-'),
            ])
            ->filters([
                //
            ])
            ->actions([])
            ->bulkActions([]);
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
            'index' => Pages\ListDailyAttendances::route('/'),
        ];
    }
}
