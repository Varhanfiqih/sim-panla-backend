<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\BkController;
use App\Http\Controllers\Api\GradeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Models\User;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        // ─── Endpoint Umum (Semua Role) ───────────────────────────────────────
        Route::get('/user', function (Request $request) {
            return response()->json([
                'success' => true,
                'data'    => $request->user()
            ]);
        });

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::post('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto']);
        Route::post('/profile/change-password', [ProfileController::class, 'changePassword']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
        Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/{notification}/action', [NotificationController::class, 'action']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // Dashboard utama (response berbeda-beda berdasarkan role, logic di controller)
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // ─── Endpoint Guru BK ─────────────────────────────────────────────────
        Route::middleware('role:' . User::ROLE_GURU_BK)->group(function () {
            Route::get('/dashboard/bk', [DashboardController::class, 'bkDashboard']);
            Route::get('/bk/absentees', [BkController::class, 'absentees']);
            Route::post('/bk/confirm', [BkController::class, 'confirm']);
            Route::get('/bk/history/{student_id}', [BkController::class, 'history']);
            Route::get('/bk/students', [BkController::class, 'students']);
            Route::post('/bk/action', [BkController::class, 'storeAction']);
            Route::get('/bk/actions', [BkController::class, 'allActions']);
            Route::get('/bk/monitoring', [BkController::class, 'monitoring']);
            Route::post('/bk/permissions/{permission}/approve', [PermissionController::class, 'approveByBk']);
            Route::post('/bk/permissions/{permission}/reject', [PermissionController::class, 'rejectByBk']);
        });

        // ─── Endpoint Guru & Wali Kelas (Operasional KBM) ────────────────────
        // Role: Guru — akses scan, jurnal, absensi, perizinan
        Route::middleware('role:' . User::ROLE_GURU . ',' . User::ROLE_GURU_BK)->group(function () {
            Route::get('/teacher/check-in-status', [\App\Http\Controllers\Api\TeacherAttendanceController::class, 'checkInStatus']);
            Route::post('/teacher/check-in', [\App\Http\Controllers\Api\TeacherAttendanceController::class, 'storeCheckIn']);
            Route::get('/attendance/categories', [\App\Http\Controllers\Api\AttendanceController::class, 'categories']);
            Route::post('/attendance/scan', [\App\Http\Controllers\Api\AttendanceController::class, 'scan']);
            Route::get('/schedules', [\App\Http\Controllers\Api\ScheduleController::class, 'index']);
            Route::get('/journal/inval-classes', [\App\Http\Controllers\Api\JournalController::class, 'invalClasses']);
            Route::get('/journal/inval-history', [\App\Http\Controllers\Api\JournalController::class, 'invalHistory']);
            Route::post('/journal/inval-claim', [\App\Http\Controllers\Api\JournalController::class, 'claimInvalClass']);
            Route::get('/journal/students/{schedule_id}', [\App\Http\Controllers\Api\JournalController::class, 'students']);
            Route::post('/journal/store', [\App\Http\Controllers\Api\JournalController::class, 'store']);
            Route::get('/journal/history/{journal_id}', [\App\Http\Controllers\Api\JournalController::class, 'history']);

            // Perizinan — hanya Wali Kelas (guard assertWaliKelas ada di controller)
            Route::get('/permissions', [PermissionController::class, 'index']);
            Route::post('/permissions/store', [PermissionController::class, 'store']);
            Route::get('/permissions/students', [PermissionController::class, 'studentsByClass']);

            Route::get('/grades/meta', [GradeController::class, 'meta']);
            Route::get('/grades/students', [GradeController::class, 'students']);
            Route::get('/grades/scores', [GradeController::class, 'scores']);
            Route::get('/grades/summary', [GradeController::class, 'summary']);
            Route::post('/grades/scores/bulk-upsert', [GradeController::class, 'bulkUpsert']);
            Route::post('/grades/finish-lock', [GradeController::class, 'finishLock']);
        });

        // ─── Endpoint Kepala Sekolah (Read-Only Monitoring) ───────────────────
        // Kepala Sekolah hanya punya akses GET/read — tidak ada POST/PUT/DELETE
        Route::middleware('role:' . User::ROLE_KEPALA_SEKOLAH)->group(function () {
            // Dashboard sudah di-handle di /dashboard umum di atas
            // Laporan monitoring tambahan bisa ditambahkan di sini
            Route::get('/monitoring/attendance', [\App\Http\Controllers\Api\AttendanceController::class, 'categories']);
        });
    });
});
