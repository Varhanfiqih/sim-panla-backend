<?php

namespace App\Filament\Resources\DailyAttendanceResource\Pages;

use App\Filament\Resources\DailyAttendanceResource;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListDailyAttendances extends ListRecords
{
    protected static string $resource = DailyAttendanceResource::class;

    public function showsRegularColumns(): bool
    {
        return in_array($this->activeTab, [null, 'semua', 'reguler'], true);
    }

    public function showsExtracurricularColumn(): bool
    {
        return in_array($this->activeTab, [null, 'semua', 'kegiatan'], true);
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Rekap Keseluruhan'),
            'reguler' => Tab::make('Hanya Absen Reguler (Pagi/Pulang)')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn(
                    'keterangan',
                    ['Masuk', 'Terlambat', 'Pulang', 'Ijin Pulang Awal'],
                )),
            'kegiatan' => Tab::make('Hanya Kegiatan Khusus')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                    $query
                        ->where('keterangan', 'like', 'Ekstra\_%')
                        ->orWhere('keterangan', 'like', '%Ekstrakurikuler%')
                        ->orWhere('kegiatan', 'like', '%Ekstrakurikuler%')
                        ->orWhereNotNull('ekstra');
                })),
        ];
    }
}
