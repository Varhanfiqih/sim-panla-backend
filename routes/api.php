<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\BkController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => $request->user()
            ]);
        });
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/bk', [DashboardController::class, 'bkDashboard']);
        
        // Fitur Kehadiran (Check-in) Guru
        Route::get('/teacher/check-in-status', [\App\Http\Controllers\Api\TeacherAttendanceController::class, 'checkInStatus']);
        Route::post('/teacher/check-in', [\App\Http\Controllers\Api\TeacherAttendanceController::class, 'storeCheckIn']);
        Route::get('/attendance/categories', [\App\Http\Controllers\Api\AttendanceController::class, 'categories']);
        Route::post('/attendance/scan', [\App\Http\Controllers\Api\AttendanceController::class, 'scan']);
        Route::get('/schedules', [\App\Http\Controllers\Api\ScheduleController::class, 'index']);
        Route::get('/journal/inval-classes', [\App\Http\Controllers\Api\JournalController::class, 'invalClasses']);
        Route::post('/journal/inval-claim', [\App\Http\Controllers\Api\JournalController::class, 'claimInvalClass']);
        Route::get('/journal/students/{schedule_id}', [\App\Http\Controllers\Api\JournalController::class, 'students']);
        Route::post('/journal/store', [\App\Http\Controllers\Api\JournalController::class, 'store']);
        Route::get('/journal/history/{journal_id}', [\App\Http\Controllers\Api\JournalController::class, 'history']);
        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions/store', [PermissionController::class, 'store']);
        Route::get('/permissions/students', [PermissionController::class, 'studentsByClass']);
        // Modul BK (Bimbingan Konseling)
        Route::get('/bk/absentees', [BkController::class, 'absentees']);
        Route::post('/bk/confirm', [BkController::class, 'confirm']);
        Route::get('/bk/history/{student_id}', [BkController::class, 'history']);
        
        // Follow-up Actions
        Route::get('/bk/students', [BkController::class, 'students']);
        Route::post('/bk/action', [BkController::class, 'storeAction']);
        Route::get('/bk/actions', [BkController::class, 'allActions']);
        Route::get('/bk/monitoring', [BkController::class, 'monitoring']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
