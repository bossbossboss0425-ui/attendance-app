<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;

class StampCorrectionRequestController extends Controller
{
    // 申請一覧画面の表示
    public function index()
    {
        $user = Auth::user();

        // ログインユーザー自身の全申請を取得（勤怠レコードも一緒にロード）
        $applications = StampCorrectionRequest::with('attendanceRecord')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Bladeに合わせて整形
        $formattedApplications = $applications->map(function ($app) {
            return [
                'id' => $app->attendance_record_id, // 「詳細」ボタンで勤怠詳細画面へ渡すID
                'approval_status' => $app->status === 'approved' ? '承認済み' : '承認待ち',
                'date' => $app->attendanceRecord ? Carbon::parse($app->attendanceRecord->date)->format('Y/m/d') : '',
                'comment' => $app->comment,
                'application_date' => $app->created_at ? $app->created_at->format('Y/m/d') : '',
            ];
        });

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }
}