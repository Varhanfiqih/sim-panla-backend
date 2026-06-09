<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileNotification;
use App\Models\Schedule;
use App\Models\TeacherAttendance;
use App\Models\User;
use App\Services\MobileNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
                    'is_present' => $attendance ? ($attendance->status === 'hadir') : null,
                    'reason' => $attendance ? $attendance->reason : null,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('CheckInStatus Error: '.$e->getMessage());

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
                    'date' => $today,
                ],
                [
                    'status' => $request->status,
                    'reason' => $request->status === 'tidak_hadir' ? $request->reason : null,
                    'description' => $request->description,
                ]
            );

            app(MobileNotificationService::class)->send(
                $user,
                'teacher_checkin',
                $request->status === 'hadir' ? 'Check-in Berhasil' : 'Ketidakhadiran Tercatat',
                $request->status === 'hadir'
                    ? 'Kehadiran Anda hari ini berhasil dikonfirmasi.'
                    : 'Status tidak hadir Anda hari ini berhasil disimpan.',
                ['attendance_id' => $attendance->id],
            );

            if ($request->status === 'tidak_hadir') {
                $this->notifyAvailableInvalTeachers(
                    $user,
                    $today,
                    $request->reason,
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Konfirmasi kehadiran harian berhasil disimpan.',
                'data' => $attendance,
            ], 200);

        } catch (\Exception $e) {
            Log::error('StoreCheckIn Error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan konfirmasi kehadiran.',
            ], 500);
        }
    }

    private function notifyAvailableInvalTeachers(
        User $absentTeacher,
        string $date,
        ?string $reason,
    ): void {
        Carbon::setLocale('id');
        $day = strtoupper(Carbon::parse($date)->isoFormat('dddd'));

        $invalSchedules = Schedule::query()
            ->with(['schoolClass', 'subject', 'timeSlot'])
            ->where('teacher_id', $absentTeacher->nip)
            ->where('day_of_week', $day)
            ->orderBy('time_slot_id')
            ->get();

        if ($invalSchedules->isEmpty()) {
            return;
        }

        $absentUserIds = TeacherAttendance::query()
            ->whereDate('date', $date)
            ->where('status', 'tidak_hadir')
            ->pluck('user_id');

        $candidates = User::query()
            ->whereIn('role', [User::ROLE_GURU, User::ROLE_GURU_BK])
            ->whereKeyNot($absentTeacher->id)
            ->whereNotIn('id', $absentUserIds)
            ->get();

        $notificationService = app(MobileNotificationService::class);

        foreach ($candidates as $candidate) {
            $occupiedSlots = Schedule::query()
                ->where('teacher_id', $candidate->nip)
                ->where('day_of_week', $day)
                ->pluck('time_slot_id');

            $available = $invalSchedules
                ->whereNotIn('time_slot_id', $occupiedSlots)
                ->values();

            if ($available->isEmpty()) {
                continue;
            }

            $alreadyNotified = MobileNotification::query()
                ->where('user_id', $candidate->id)
                ->where('type', 'inval_available')
                ->whereDate('created_at', $date)
                ->where('data->absent_teacher_id', $absentTeacher->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            $first = $available->first();
            $classNames = $available
                ->map(fn (Schedule $schedule) => $schedule->schoolClass?->name ?? $schedule->class_id)
                ->unique()
                ->implode(', ');

            $notificationService->send(
                $candidate,
                'inval_available',
                'Jadwal Inval Tersedia',
                "{$absentTeacher->name} tidak hadir ({$reason}). Tersedia {$available->count()} sesi inval untuk kelas {$classNames}.",
                [
                    'absent_teacher_id' => $absentTeacher->id,
                    'schedule_ids' => $available->pluck('id')->all(),
                    'first_schedule_id' => $first->id,
                ],
            );
        }
    }
}
