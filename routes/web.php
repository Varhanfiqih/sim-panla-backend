<?php

use App\Http\Controllers\JournalAttachmentController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\StudentQrPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/journals/{journal}/attachment', [JournalAttachmentController::class, 'show'])
        ->name('admin.journals.attachment.show');
    Route::get('/admin/journals/{journal}/attachment/download', [JournalAttachmentController::class, 'download'])
        ->name('admin.journals.attachment.download');
    Route::get('/admin/reports/attendance.xlsx', [ReportExportController::class, 'attendance'])
        ->name('admin.reports.attendance');
    Route::get('/admin/reports/journal.xlsx', [ReportExportController::class, 'journal'])
        ->name('admin.reports.journal');
    Route::get('/admin/reports/grades.xlsx', [ReportExportController::class, 'grades'])
        ->name('admin.reports.grades');
    Route::get('/admin/students/{student}/qr.pdf', [StudentQrPdfController::class, 'download'])
        ->name('admin.students.qr.download');
});
