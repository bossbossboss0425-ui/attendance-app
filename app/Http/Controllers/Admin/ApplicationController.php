<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApplicationController extends Controller
{
    /**
     * 申請一覧画面
     */
    public function index()
    {
        $applications = StampCorrectionRequest::with(['user', 'attendanceRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        $applications->transform(function ($application) {
            $application->approval_status = ($application->status === 'approved' || $application->status === '承認済み') ? '承認済み' : '承認待ち';
            $application->application_date = $application->created_at;

            return $application;
        });

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 修正申請詳細画面（承認画面）
     */
    public function show($attendance_correct_request_id)
    {
        $application = StampCorrectionRequest::with(['user', 'attendanceRecord', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        $application->approval_status = ($application->status === 'approved' || $application->status === '承認済み') ? '承認済み' : '承認待ち';

        $targetDate = optional($application->attendanceRecord)->date ?? now()->toDateString();
        $application->new_date = Carbon::parse($targetDate);

        // DB の new_clock_in / new_clock_out を取得して H:i に整形
        $application->new_clock_in = $application->new_clock_in ? Carbon::parse($application->new_clock_in)->format('H:i') : '';
        $application->new_clock_out = $application->new_clock_out ? Carbon::parse($application->new_clock_out)->format('H:i') : '';

        if ($application->proposalBreaks) {
            foreach ($application->proposalBreaks as $break) {
                // DB上の new_break_in / new_break_out を取得
                $rawIn = $break->new_break_in ?? $break->break_in;
                $rawOut = $break->new_break_out ?? $break->break_out;

                // Blade 内の \Carbon\Carbon::parse($break->break_in) でエラーにならないよう、値を持たせる
                $break->break_in = $rawIn ? Carbon::parse($rawIn)->format('Y-m-d H:i:s') : null;
                $break->break_out = $rawOut ? Carbon::parse($rawOut)->format('Y-m-d H:i:s') : null;
            }
        }

        $user = $application->user;

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    /**
     * 承認処理実行
     */
    public function approve($attendance_correct_request_id)
    {
        DB::transaction(function () use ($attendance_correct_request_id) {
            $application = StampCorrectionRequest::with('proposalBreaks')->findOrFail($attendance_correct_request_id);

            // 1. 申請ステータスを承認済みに変更
            $application->status = 'approved';
            $application->approved_at = now();
            $application->save();

            // 2. 元の勤怠レコード（AttendanceRecord）を更新
            $attendance = AttendanceRecord::findOrFail($application->attendance_record_id);

            // 日付と組み合わせた完全な日時フォーマットを作成して保存
            $targetDate = $attendance->date;

            if ($application->new_clock_in) {
                $attendance->clock_in = Carbon::parse("{$targetDate} {$application->new_clock_in}");
            }
            if ($application->new_clock_out) {
                $attendance->clock_out = Carbon::parse("{$targetDate} {$application->new_clock_out}");
            }
            if ($application->comment) {
                $attendance->comment = $application->comment;
            }
            $attendance->save();

            // 3. 休憩レコードの差し替え
            if ($application->proposalBreaks && $application->proposalBreaks->count() > 0) {
                BreakRecord::where('attendance_record_id', $attendance->id)->delete();

                foreach ($application->proposalBreaks as $pBreak) {
                    BreakRecord::create([
                        'attendance_record_id' => $attendance->id,
                        'break_in' => $pBreak->new_break_in,
                        'break_out' => $pBreak->new_break_out,
                    ]);
                }
            }
        });

        return redirect()->route('admin.application.list');
    }
}
