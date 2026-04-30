<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('cache.headers')->name('login');
Route::post('/register', [AuthController::class, 'register'])->middleware('cache.headers');

Route::middleware(['auth:sanctum', 'cache.headers'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/attendance/personal-history', [AttendanceController::class, 'getPersonalHistory']);
    Route::get('/reports/individual/{user}', [ReportController::class, 'individualReport']);
    Route::get('/reports/export-csv', [ReportController::class, 'exportCsv']);
    Route::get('/qr/get/{user}', [QrCodeController::class, 'getQrCode']);
    Route::post('/qr/verify', [QrCodeController::class, 'verifyQrCode']);
    Route::get('/qr/download/{user}', [QrCodeController::class, 'downloadQrCode']);

    Route::middleware('role:super_admin,staff_teacher_supervisor')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance', [AttendanceController::class, 'store']);
        Route::get('/attendance/{record}', [AttendanceController::class, 'show']);
        Route::put('/attendance/{record}', [AttendanceController::class, 'update']);
        Route::patch('/attendance/{record}', [AttendanceController::class, 'update']);
        Route::delete('/attendance/{record}', [AttendanceController::class, 'destroy']);
        Route::post('/attendance/mark-by-qr', [AttendanceController::class, 'markByQrCode']);
        Route::get('/attendance/summary', [AttendanceController::class, 'getSummary']);

        Route::get('/reports/daily', [ReportController::class, 'dailyAttendance']);
        Route::get('/reports/weekly', [ReportController::class, 'weeklyAttendance']);
        Route::get('/reports/monthly', [ReportController::class, 'monthlyAttendance']);
        Route::get('/reports/classroom', [ReportController::class, 'classroomReport']);

        Route::get('/print/blank-sheet', [PrintController::class, 'printBlankSheet']);
        Route::get('/print/daily-attendance', [PrintController::class, 'printDailyAttendance']);
        Route::get('/print/classroom-report', [PrintController::class, 'printClassroomReport']);
        Route::get('/print/individual-report', [PrintController::class, 'printIndividualReport']);

        Route::post('/qr/scan', [QrCodeController::class, 'scanQrCode']);
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('/users', UserController::class);
        Route::post('/users/{user}/upload-profile-image', [UserController::class, 'uploadProfileImage']);
        Route::post('/users/{user}/generate-qr-code', [UserController::class, 'generateQrCode']);

        Route::apiResource('/classrooms', ClassroomController::class);
        Route::get('/classrooms/{classroom}/students', [ClassroomController::class, 'getStudents']);
        Route::post('/classrooms/{classroom}/assign-users', [ClassroomController::class, 'assignUsers']);

        Route::get('/imports/template/{type}', [ImportController::class, 'downloadTemplate']);
        Route::apiResource('/imports', ImportController::class)->only(['index', 'store', 'show']);

        Route::post('/qr/generate/{user}', [QrCodeController::class, 'generateUserQr']);
        Route::get('/qr/print-all', [QrCodeController::class, 'printAllQrCodes']);
    });
});
