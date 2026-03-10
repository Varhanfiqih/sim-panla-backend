<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Student;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            // Tentukan hari ini (Indonesia)
        Carbon::setLocale('id');
        $hariIniStr = strtoupper(Carbon::now()->isoFormat('dddd')); // e.g., 'SENIN', 'SABTU'
        $periode = 'Tahun Ajaran 2025/2026 Genap'; // Bisa dinamis dari DB Pengaturan kelak

        // Cek status Check-in Kehadiran Guru hari ini
        $hasCheckedIn = \App\Models\TeacherAttendance::where('user_id', $user->id)
            ->whereDate('date', Carbon::today()->toDateString())
            ->exists();

        // Base Response
        $response = [
            'status' => 'success',
            'data' => [
                'user' => [
                    'nip' => $user->nip,
                    'name' => $user->name,
                    'role' => $user->role,
                    'wali_kelas' => $user->wali_kelas,
                ],
                'periode' => $periode,
                'hari_ini' => $hariIniStr,
                'has_checked_in' => $hasCheckedIn,
                'metrics' => [],
                'jadwal_hari_ini' => []
            ]
        ];

        // Custom Logic by Role
        if ($user->role === 'Admin') {
            // Metrics Kepsek / Superadmin (Global)
            $response['data']['metrics'] = [
                'total_guru' => User::whereIn('role', ['Guru', 'Guru BK'])->count(),
                'total_siswa' => Student::count(),
                'kelas_kosong' => 0, // TODO: filter hariIniStr dari schedule yang belum ada jurnal
                'persentase_kehadiran' => 98 // Dummy %
            ];
            
            // Opsional: kepsek juga lihat jadwal global hr ini
            $response['data']['jadwal_hari_ini'] = Schedule::with('guru:nip,name')
                ->where('hari', $hariIniStr)
                ->orderBy('jam_ke', 'asc')
                ->get();
                
        } else {
            // Metrics Guru (Personal)
            $totalJadwalPekanIni = Schedule::where('teacher_id', $user->nip)->count();
            
            $jumlahSiswaWali = 0;
            $homeroomClass = $user->homeroomClass;
            if ($homeroomClass) {
                $jumlahSiswaWali = $homeroomClass->students()->count();
            }

            $totalInval = \App\Models\Journal::where('user_id', $user->nip)
                ->where('is_inval', true)
                ->count();

            // Hitung jurnal KBM yang sudah SELESAI diisi HARI INI
            $doneJournalsToday = \App\Models\Journal::where('user_id', $user->nip)
                ->whereDate('created_at', Carbon::today()->toDateString())
                ->count();

            $response['data']['metrics'] = [
                'total_mengajar' => $totalJadwalPekanIni,
                'siswa_wali' => $jumlahSiswaWali,
                'total_inval' => $totalInval,
                'done_journals_today' => $doneJournalsToday,
                'persentase_kehadiran' => 100 // Dummy persen kehadiran kelas dia
            ];

            // Jadwal ngajar guru log in
            $response['data']['jadwal_hari_ini'] = Schedule::with(['timeSlot', 'subject', 'schoolClass'])
                ->where('teacher_id', $user->nip)
                ->where('day_of_week', strtoupper($hariIniStr))
                ->orderBy('time_slot_id', 'asc')
                ->get();
        }

        \Log::info('Dashboard API Response:', ['jadwal_hari' => $response['data']['jadwal_hari_ini']]);
        \Log::info('FULL DASHBOARD JSON PAYLOAD GURU:', $response);
        return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
    public function bkDashboard(Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || $user->role !== 'Guru BK') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        \Carbon\Carbon::setLocale('id');
        $hariIniStr = strtoupper(\Carbon\Carbon::now()->isoFormat('dddd'));

        $today = \Carbon\Carbon::today()->toDateString();
        $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->toDateString();
        
        // Data Absensi KBM sebenarnya dicatat di StudentNote as per JournalController
        $totalAlpa = \App\Models\StudentNote::where('note_type', 'KBM_Alpa')
            ->whereDate('created_at', $today)->count();
            
        // Siswa yang terlambat (saat ini belum ada tabel gate)
        $totalTerlambat = 0; 
            
        $totalIzinSakit = \App\Models\StudentNote::whereIn('note_type', ['KBM_Sakit', 'KBM_Izin', 'KBM_Sakit_atau_Izin'])
            ->whereDate('created_at', $today)->count();
            
        // Siswa Melampaui Batas Toleransi Alpa (Tindak Lanjut / Pending Action)
        // Kita limit >= 2 kali alpa di bulan ini
        $violatorsId = \App\Models\StudentNote::whereIn('note_type', ['KBM_Alpa', 'Alpa'])
            ->whereDate('created_at', '>=', $startOfMonth)
            ->select('student_id', \Illuminate\Support\Facades\DB::raw('count(id) as total_alpa'))
            ->groupBy('student_id')
            ->havingRaw('total_alpa >= 2')
            ->get()->pluck('student_id');

        $pendingActionCount = count($violatorsId);

        // Mengambil Top 5 Siswa Pelanggar (Paling Banyak Alpa di Bulan ini)
        $topViolatorsDB = \App\Models\StudentNote::with('student')
            ->whereIn('note_type', ['KBM_Alpa', 'Alpa'])
            ->whereDate('created_at', '>=', $startOfMonth)
            ->select('student_id', \Illuminate\Support\Facades\DB::raw('count(id) as total_alpa'))
            ->groupBy('student_id')
            ->orderByDesc('total_alpa')
            ->take(5)
            ->get();

        $topViolators = $topViolatorsDB->map(function($v) {
            return [
                'name' => $v->student ? $v->student->name : 'Siswa Unknown',
                'class_id' => $v->student ? (string) $v->student->class_id : '?',
                'reason' => $v->total_alpa . 'x Alpa',
            ];
        });

        $response = [
            'status' => 'success',
            'data' => [
                'user' => [
                    'nip' => $user->nip,
                    'name' => $user->name,
                    'role' => $user->role,
                ],
                'hari_ini' => $hariIniStr,
                'total_alpa' => $totalAlpa,
                'metrics' => [
                    'alpa' => $totalAlpa,
                    'terlambat' => $totalTerlambat,
                    'izin' => $totalIzinSakit,
                    'pending_action' => $pendingActionCount,
                ],
                'top_violators' => $topViolators
            ]
        ];

        return response()->json($response);
    }
}
