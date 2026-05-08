<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Journal;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = Carbon::today();

        $totalSiswa = Student::count();
        $totalGuru  = User::whereIn('role', [User::ROLE_GURU, User::ROLE_GURU_BK])->count();

        // Scan masuk hari ini
        $scanMasuk = Attendance::whereDate('created_at', $today)
            ->whereIn('presensi', ['Masuk', 'Terlambat'])
            ->count();

        $persen = $totalSiswa > 0 ? round($scanMasuk / $totalSiswa * 100) : 0;

        // Jurnal terisi hari ini
        $jurnalHariIni = Journal::whereDate('created_at', $today)->count();

        return [
            Stat::make('Total Siswa', $totalSiswa)
                ->description('Terdaftar aktif')
                ->color('primary')
                ->icon('heroicon-m-academic-cap'),

            Stat::make('Total Guru', $totalGuru)
                ->description('Staf pengajar')
                ->color('success')
                ->icon('heroicon-m-users'),

            Stat::make('Hadir Hari Ini', "$scanMasuk / $totalSiswa")
                ->description("$persen% kehadiran")
                ->color($persen > 80 ? 'success' : ($persen > 60 ? 'warning' : 'danger'))
                ->icon('heroicon-m-qr-code'),

            Stat::make('Jurnal Terisi', $jurnalHariIni)
                ->description(Carbon::today()->translatedFormat('l, d F Y'))
                ->color('info')
                ->icon('heroicon-m-book-open'),
        ];
    }
}
