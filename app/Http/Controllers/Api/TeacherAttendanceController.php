<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TeacherAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TeacherAttendanceController extends Controller
{
    /**
     * Mengecek apakah guru sudah memberikan konfirmasi kehadiran hari ini
     * GET /api/v1/teacher/check-in-status
     */
    public function checkInStatus(Request $request)
    {
        try {
            $user = $request->user();
            
            $today = Carbon::today()->toDateString();

            // Cari record kehadiran hari ini
            $attendance = TeacherAttendance::where('user_id', $user->id)
                ->whereDate('date', $today)
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'has_checked_in' => $attendance ? true : false,
                    'is_present'     => $attendance ? ($attendance->status === 'hadir') : null,
                    'reason'         => $attendance ? $attendance->reason : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('CheckInStatus Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengecek status kehadiran.',
            ], 500);
        }
    }

    /**
     * Menyimpan konfirmasi kehadiran guru (Hadir / Tidak Hadir)
     * POST /api/v1/teacher/check-in
     */
    public function storeCheckIn(Request $request)
    {
        $request->validate([
            'status' => 'required|in:hadir,tidak_hadir',
            'reason' => 'required_if:status,tidak_hadir|nullable|string',
            'description' => 'nullable|string',
        ]);

        try {
            $user = $request->user();
            
            $today = Carbon::today()->toDateString();

            // Insert atau Update konfirmasi hari ini (Mencegah double submit)
            $attendance = TeacherAttendance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'date'    => $today,
                ],
                [
                    'status'      => $request->status,
                    'reason'      => $request->status === 'tidak_hadir' ? $request->reason : null,
                    'description' => $request->description,
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Konfirmasi kehadiran harian berhasil disimpan.',
                'data' => $attendance,
            ], 200);

        } catch (\Exception $e) {
            Log::error('StoreCheckIn Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan konfirmasi kehadiran.',
            ], 500);
        }
    }
}
