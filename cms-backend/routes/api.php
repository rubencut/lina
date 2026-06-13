<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceSessionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassroomController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PrintController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('cache.headers')->name('login');
Route::post('/register', [AuthController::class, 'register'])->middleware('cache.headers');
Route::post('/verify-code', [AuthController::class, 'verifyCode'])->middleware('cache.headers');

Route::middleware(['auth:sanctum', 'cache.headers'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    Route::get('/attendance/personal-history', [AttendanceController::class, 'getPersonalHistory']);
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/individual/{user}', [ReportController::class, 'individualReport']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::get('/reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
    Route::get('/qr/users', [QrCodeController::class, 'index']);
    Route::post('/qr/generate/{user}', [QrCodeController::class, 'generateUserQr']);
    Route::get('/qr/get/{user}', [QrCodeController::class, 'getQrCode']);
    Route::get('/qr/download/{user}', [QrCodeController::class, 'downloadQrCode']);
    Route::get('/qr/print-all', [QrCodeController::class, 'printAllQrCodes']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::get('/classrooms', [ClassroomController::class, 'index']);
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show']);
    Route::get('/classrooms/{classroom}/students', [ClassroomController::class, 'getStudents']);
    Route::get('/classrooms/{classroom}/attendance', [ClassroomController::class, 'attendance']);

    Route::middleware('role:super_admin,staff_teacher_supervisor')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index']);
        Route::post('/attendance', [AttendanceController::class, 'store']);
        Route::post('/attendance/mark-by-qr', [AttendanceController::class, 'markByQr'])->middleware('role:staff_teacher_supervisor');
        Route::get('/attendance/summary', [AttendanceController::class, 'getSummary']);
        Route::get('/attendance/{record}', [AttendanceController::class, 'show']);
        Route::put('/attendance/{record}', [AttendanceController::class, 'update']);
        Route::patch('/attendance/{record}', [AttendanceController::class, 'update']);
        Route::delete('/attendance/{record}', [AttendanceController::class, 'destroy']);
        Route::apiResource('/attendance-sessions', AttendanceSessionController::class)->except(['show']);

        Route::get('/reports/weekly', [ReportController::class, 'weeklyAttendance']);
        Route::get('/reports/monthly', [ReportController::class, 'monthlyAttendance']);
        Route::get('/reports/classroom', [ReportController::class, 'classroomReport']);

        Route::get('/print/blank-sheet', [PrintController::class, 'printBlankSheet']);
        Route::get('/print/classroom-report', [PrintController::class, 'printClassroomReport']);
        Route::get('/print/individual-report', [PrintController::class, 'printIndividualReport']);

        Route::post('/classrooms', [ClassroomController::class, 'store']);
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update']);
        Route::patch('/classrooms/{classroom}', [ClassroomController::class, 'update']);
        Route::post('/classrooms/{classroom}/assign-users', [ClassroomController::class, 'assignUsers']);
        Route::post('/classrooms/{classroom}/attendance/submit', [ClassroomController::class, 'submitAttendance']);

        Route::get('/imports/template/{type}', [ImportController::class, 'downloadTemplate']);
        Route::apiResource('/imports', ImportController::class)->only(['index', 'store', 'show']);
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('/users', UserController::class);
        Route::post('/users/{user}/upload-profile-image', [UserController::class, 'uploadProfileImage']);
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy']);

        Route::post('/notifications', [NotificationController::class, 'store']);
        Route::post('/notifications/send-pending', [NotificationController::class, 'sendPending']);

    });
});
