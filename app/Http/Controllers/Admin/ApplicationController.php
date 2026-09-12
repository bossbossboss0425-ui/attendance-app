<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    /**
     * 申請一覧画面（管理者用）
     */
    public function index(): View
    {
        $applications = StampCorrectionRequest::with(['user', 'attendanceRecord'])
            ->orderBy('created_at', 'desc')
            ->get();

        $applications->transform(function (StampCorrectionRequest $application): StampCorrectionRequest {
            $application->approval_status = ($application->status === 'approved' || $application->status === '承認済み')
                ? '承認済み'
                : '承認待ち';
            $application->application_date = $application->created_at;
            $application->AttendanceRecord = $application->attendanceRecord;

            return $application;
        });

        return view('admin.admin-application-list', compact('applications'));
    }

    /**
     * 修正申請詳細画面（承認画面）
     */
    public function show(int $attendance_correct_request_id): View
    {
        $application = StampCorrectionRequest::with(['user', 'attendanceRecord', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        $application->approval_status = ($application->status === 'approved' || $application->status === '承認済み') ? '承認済み' : '承認待ち';

        $targetDate = optional($application->attendanceRecord)->date ?? now()->toDateString();
        $application->new_date = Carbon::parse($targetDate);

        $application->new_clock_in = $application->new_clock_in ? Carbon::parse($application->new_clock_in)->format('H:i') : '';
        $application->new_clock_out = $application->new_clock_out ? Carbon::parse($application->new_clock_out)->format('H:i') : '';

        if ($application->proposalBreaks) {
            foreach ($application->proposalBreaks as $break) {
                $rawIn = $break->new_break_in ?? $break->break_in;
                $rawOut = $break->new_break_out ?? $break->break_out;

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
    public function approve(int $attendance_correct_request_id): RedirectResponse
    {
        DB::transaction(function () use ($attendance_correct_request_id): void {
            $application = StampCorrectionRequest::with('proposalBreaks')->findOrFail($attendance_correct_request_id);

            $application->status = 'approved';
            $application->approved_at = now();
            $application->save();

            $attendance = AttendanceRecord::findOrFail($application->attendance_record_id);
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

        return redirect()->route('stamp_correction_request.list');
    }
}
