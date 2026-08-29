<?php

use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Support\Facades\Route;



// 管理者用ログイン
Route::get('/admin/login', function () {
    return view('admin.admin-login');
})->middleware('guest')->name('admin.login');

Route::post('/admin/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('guest');


Route::middleware(['auth'])->group(function () {

    Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');

    // 勤怠詳細
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.detail');
    Route::post('/attendance/{id}', [AttendanceController::class, 'update'])->name('attendance.update');

    // 申請一覧
    Route::get('/application/{id}', [AttendanceController::class, 'show'])->name('attendance.show');

    // ユーザー申請一覧
    Route::get('/stamp_correction_request/list', [StampCorrectionRequestController::class, 'index'])->name('stamp_correction_request.list');
});

