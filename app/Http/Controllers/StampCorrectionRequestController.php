<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\AttendanceRecord;
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

        // Bladeの条件分岐 (@if ($application['approval_status'] === '承認待ち')) に合わせて整形
        $formattedApplications = $applications->map(function ($app) {

            // DBのstatus値（'pending', 'approved' や '承認待ち', '承認済み'）に応じて文字列を統一
            $statusStr = '承認待ち';
            if ($app->status === 'approved' || $app->status === '承認済み') {
                $statusStr = '承認済み';
            }

            return [
                'id' => $app->attendance_record_id, // Bladeの詳細リンク（/application/{id}）に渡すID
                'approval_status' => $statusStr,
                'date' => $app->attendanceRecord ? Carbon::parse($app->attendanceRecord->date)->format('Y/m/d') : '',
                'comment' => $app->comment,
                'application_date' => $app->created_at ? $app->created_at->format('Y/m/d') : '',
            ];
        });

        // viewのパスは Blade の配置場所（user.user-application-list）に指定
        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    // 申請詳細の表示
    public function show($id)
    {
        // /application/{id} に来たら、そのまま勤怠詳細画面 (/attendance/{id}) へ転送
        return redirect()->route('attendance.detail', ['id' => $id]);
    }
}