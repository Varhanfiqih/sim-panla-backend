<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyAttendanceResource\Pages\ListDailyAttendances;
use App\Models\DailyAttendance;
use App\Models\SchoolClass;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyAttendanceResource extends Resource
{
    protected static ?string $model = DailyAttendance::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Laporan Harian';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $pluralModelLabel = 'Rekapitulasi Presensi Harian';

    protected static ?int $navigationSort = 1;

    // Hanya Kepala Sekolah dan Super Admin yang bisa melihat Laporan Harian
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->isKepsek() || $user?->isSuperAdmin();
    }

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
                    MAX(CASE WHEN keterangan IN ("Pulang", "Ijin Pulang Awal") THEN created_at END) as jam_pulang,
                    MAX(CASE
                        WHEN keterangan LIKE "Ekstra_%"
                            OR keterangan LIKE "%Ekstrakurikuler%"
                            OR kegiatan LIKE "%Ekstrakurikuler%"
                            OR ekstra IS NOT NULL
                        THEN CASE
                            WHEN keterangan LIKE "Ekstra_%" THEN REPLACE(keterangan, "Ekstra_", "")
                            ELSE COALESCE(NULLIF(ekstra, ""), NULLIF(kegiatan, ""), keterangan)
                        END
                    END) as ekstrakurikuler_wajib,
                    MAX(CASE
                        WHEN keterangan LIKE "Ekstra_%"
                            OR keterangan LIKE "%Ekstrakurikuler%"
                            OR kegiatan LIKE "%Ekstrakurikuler%"
                            OR ekstra IS NOT NULL
                        THEN created_at
                    END) as jam_ekstrakurikuler
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
                    ->visible(fn (ListDailyAttendances $livewire): bool => $livewire->showsRegularColumns())
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'Masuk' => 'success',
                        'Terlambat' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('jam_masuk')
                    ->label('Jam Kedatangan')
                    ->visible(fn (ListDailyAttendances $livewire): bool => $livewire->showsRegularColumns())
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i:s') : '-'),
                Tables\Columns\TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->visible(fn (ListDailyAttendances $livewire): bool => $livewire->showsRegularColumns())
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('H:i:s') : '-'),
                Tables\Columns\TextColumn::make('ekstrakurikuler_wajib')
                    ->label('Ekstrakurikuler Wajib')
                    ->visible(fn (ListDailyAttendances $livewire): bool => $livewire->showsExtracurricularColumn())
                    ->formatStateUsing(function ($state, DailyAttendance $record): string {
                        if (blank($state)) {
                            return '-';
                        }

                        $time = $record->jam_ekstrakurikuler
                            ? Carbon::parse($record->jam_ekstrakurikuler)->format('H:i')
                            : null;

                        return $time ? "{$state} ({$time})" : $state;
                    })
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('rentang_tanggal')
                    ->label('Rentang Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal (Hari/Tahun)')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->minDate(fn (Forms\Get $get) => $get('dari_tanggal')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('kelas')
                    ->label('Filter Kelas')
                    ->options(fn (): array => SchoolClass::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->placeholder('Semua Kelas'),
            ])
            ->filtersFormColumns(1)
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
            'index' => ListDailyAttendances::route('/'),
        ];
    }
}
