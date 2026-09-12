<?php

use App\Http\Controllers\Admin\AdminAttendanceController;
use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampCorrectionRequestController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

// logo
Route::get('/', function () {
    return redirect('/attendance');
});

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

    // CSV出力
    Route::post('/export', [AdminAttendanceController::class, 'export'])->name('attendance.export');

    // ----------------------------------------------------
    // メール認証関連ルート
    // ----------------------------------------------------
    // メール認証誘導画面
    Route::get('/email/verify', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    // メール内のリンク押下時の検証処理
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect('/attendance'); // 認証完了後に勤怠登録画面へ遷移
    })->middleware(['signed'])->name('verification.verify');

    // 認証メール再送処理
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();

        return back()->with('message', '認証メールを再送しました。');
    })->middleware(['throttle:6,1'])->name('verification.send');

    // ----------------------------------------------------
    // 申請一覧
    // ----------------------------------------------------
    Route::get('/stamp_correction_request/list', function () {
        // 管理者の場合
        if (auth()->check() && auth()->user()->admin_status === 1) {
            return app(AdminApplicationController::class)->index();
        }

        // 一般ユーザーの場合
        return app(StampCorrectionRequestController::class)->index();
    })->name('stamp_correction_request.list');

    // ----------------------------------------------------
    // 管理者：修正申請の承認画面・承認処理
    // ----------------------------------------------------
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminApplicationController::class, 'show'])->name('admin.application.show');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminApplicationController::class, 'approve'])->name('admin.application.approve');

    // 申請詳細（一般ユーザー向け）
    Route::get('/application/{id}', [AttendanceController::class, 'show'])->name('application.show');

    // ----------------------------------------------------
    // 管理者用 (/admin/...)
    // ----------------------------------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendance.index');

        Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])->name('attendance.detail');
        Route::post('/attendance/{id}', [AdminAttendanceController::class, 'update'])->name('attendance.update');

        Route::get('/attendance/staff/{id}', [StaffController::class, 'attendance'])->name('attendance.staff');
        Route::get('/staff/list', [StaffController::class, 'index'])->name('staff.index');

    });

    // ----------------------------------------------------
    // 一般ユーザー用（★ verified ミドルウェアでメール認証必須化）
    // ----------------------------------------------------
    Route::middleware(['verified'])->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'create'])->name('attendance.create');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/list', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.detail');
        Route::post('/attendance/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
    });

});
