<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Scan QR Code Presensi
     * Endpoint: POST /api/v1/attendance/scan
     */
    public function scan(Request $request)
    {
        $request->validate([
            'qr_code'  => 'required|string',
            'type'     => 'required|string', // e.g 'Gate_Terlambat', 'Masuk', 'Keluar'
            'kegiatan' => 'nullable|string', // e.g 'Sholat Dhuha', 'Upacara', 'KBM'
        ]);

        try {
            $user = $request->user();
            $qrCode = $request->qr_code;
            $type = $request->type;
            $kegiatan = $request->kegiatan;

            // 1. Find Student by QR Code (or Fallback to NISN/NIS for testing)
            $student = Student::where('qr_code', $qrCode)
                ->orWhere('nisn', $qrCode)
                ->orWhere('nis', $qrCode)
                ->first();

            if (!$student) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'QR Code atau NISN siswa tidak ditemukan.',
                ], 404);
            }

            // --- Auto-Resolve Time-Based Attendance ---
            // Hanya berlaku jika Flutter memanggil Mode Reguler ('otomatis')
            if ($type === 'otomatis') {
                $now = Carbon::now();
                $time = $now->format('H:i:s');

                // Ditarik dari Database (Settings Table) via Filament Web Admin
                $jamBuka = \App\Models\AppSetting::getVal('jam_buka_gerbang', '05:00:00');
                $batasMasuk = \App\Models\AppSetting::getVal('jam_masuk', '07:00:00');
                $batasTerlambat = \App\Models\AppSetting::getVal('jam_terlambat_toleransi', '07:15:00');
                $jamPulang = \App\Models\AppSetting::getVal('jam_pulang', '15:00:00');
                $batasPulang = \App\Models\AppSetting::getVal('batas_jam_pulang', '16:30:00');

                if ($time >= $jamBuka && $time <= $batasMasuk) {
                    $type = 'Masuk';
                } elseif ($time > $batasMasuk && $time <= $batasTerlambat) {
                    $type = 'Terlambat';
                } elseif ($time >= $jamPulang && $time <= $batasPulang) {
                    $type = 'Pulang';
                } else {
                    return response()->json([
                        'status'  => 'error',
                        'message' => "Saat ini pukul {$time}. Bukan rentang penandaan Masuk, Terlambat, maupun Pulang.",
                    ], 400);
                }
            }
            // -------------------------------------------

            // 2. Cek apakah sudah pernah absen di grup waktu/kegiatan yang relevan hari ini
            $today = Carbon::today()->toDateString();
            $queryScan = Attendance::where('nisn_student', $student->nisn)
                ->whereDate('created_at', $today)
                ->where('kegiatan', $kegiatan);
            
            // Jika Masuk/Terlambat, pastikan tidak dobel di kloter pagi (keduanya dianggap 1 slot: Absen Kedatangan)
            if (in_array($type, ['Masuk', 'Terlambat'])) {
                $queryScan->whereIn('keterangan', ['Masuk', 'Terlambat']);
            } else {
                // Untuk presensi Pulang / Ekstra / Sholat Dhuha dlsb. dicek berdasarkan type eksaknya.
                $queryScan->where('keterangan', $type);
            }

            $existingScan = $queryScan->first();

            if ($existingScan) {
                $waktu = $existingScan->created_at->format('H:i');
                return response()->json([
                    'status'  => 'error',
                    'message' => "Siswa {$student->name} sudah valid tercatat {$existingScan->keterangan} pada pukul {$waktu}.",
                    'data'    => $student
                ], 400);
            }

            // 3. Determine 'Kelas' (If it's KBM, determine from teacher's schedule. Otherwise, use student's registered class)
            // Note: Siswa otomatis terkait dengan kelas di database (school_class.id)
            $kelasSiswa = $student->class_id ?? 'Unknown'; 
            
            // 4. Record Attendance
            $attendance = Attendance::create([
                'nip_guru'     => $user->nip,
                'nisn_student' => $student->nisn,
                'kelas'        => $kelasSiswa,
                'presensi'     => 'Hadir', // Base status is Hadir when scanned
                'keterangan'   => $type,   // Store the scan type
                'kegiatan'     => $kegiatan,
            ]);

            // 5. Trigger Realtime Event (Laravel Reverb / WebSockets)
            try {
                event(new \App\Events\StudentStatusUpdated(
                    $student->id,
                    $student->class_id ?? $kelasSiswa,
                    'Gate_'.$type, // Misal: Gate_Masuk, Gate_Terlambat
                    'GateScanner'
                ));
            } catch (\Exception $bcErr) {
                \Log::error('Broadcast error AttendanceScan: ' . $bcErr->getMessage());
            }

            return response()->json([
                'status'  => 'success',
                'message' => "Berhasil mencatat scan {$type} untuk {$student->name}.",
                'data'    => [
                    'student'    => $student,
                    'attendance' => $attendance
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Scan Attendance Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem saat memproses scan.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Attendance Categories
     * Endpoint: GET /api/v1/attendance/categories
     */
    public function categories(Request $request)
    {
        try {
            // Sinkronisasi dengan Pengaturan Presensi Terbaru (Filament AppSettings)
            $jenisPresensiRaw = \App\Models\AppSetting::getVal('tipe_presensi_custom', '[]');
            $jenisPresensi = json_decode($jenisPresensiRaw, true);
            
            // Ekstrakurikuler tetap diambil dari Category (Bisa dipindah ke Settings nanti jika diminta)
            $ekstra = \App\Models\Category::where('type', 'ekstra')->pluck('name');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'scan_types' => $jenisPresensi,
                    'kegiatan_list' => $ekstra,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Fetch Categories Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan sistem saat mengambil data kategori.',
            ], 500);
        }
    }
}
