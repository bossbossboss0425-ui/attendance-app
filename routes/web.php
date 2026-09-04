<?php

use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// ----------------------------------------------------
// 未認証（ゲスト）向けルート
// ----------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', function () {
        return view('admin.admin-login');
    })->name('admin.login');

    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);
});

// ----------------------------------------------------
// ログイン済みユーザー向けルート
// ----------------------------------------------------
Route::middleware(['auth'])->group(function () {

    // 申請一覧
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])->name('stamp_correction_request.list');

    // --- 管理者専用：修正申請の承認画面・承認処理 ---
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApplicationController::class, 'show'])->name('admin.application.show');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApplicationController::class, 'approve'])->name('admin.application.approve');

    // --- 一般ユーザー用 ---
    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.detail');
    Route::post('/attendance/{id}', [AttendanceController::class, 'update'])->name('attendance.update');

    // --- 管理者用 (/admin/...) ---
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('attendance.detail');
        Route::get('/attendance/staff/{id}', [StaffController::class, 'attendance'])->name('attendance.staff');

        Route::get('/staff/list', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/stamp_correction_request/list', [ApplicationController::class, 'index'])->name('application.list');
    });
});
