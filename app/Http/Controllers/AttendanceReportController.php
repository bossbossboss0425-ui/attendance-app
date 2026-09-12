<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AttendanceReportController extends Controller
{
    /**
     * マイ勤怠レポート画面を表示
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // 基準日付（今月）
        $now = Carbon::now();

        // 集計対象期間：前月〜6ヶ月前
        $startDate = $now->copy()->subMonths(6)->startOfMonth();
        $endDate = $now->copy()->subMonth()->endOfMonth();

        // 対象期間の勤怠データ（休憩レコードも一括Eager Loading）
        $records = AttendanceRecord::with('breakRecords')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // 1. 月次推移データ（前月以前の6ヶ月間を昇順で作成）
        $monthlyTrend = [];
        $totalWorkMinutes = 0;
        $totalOvertimeMinutes = 0;
        $workDaysCount = 0; // 平均計算用の実働日数

        for ($i = 6; $i >= 1; $i--) {
            $targetMonth = $now->copy()->subMonths($i);
            $monthKey = $targetMonth->format('Y-m');
            $monthLabel = $targetMonth->format('Y年m月');

            // 該当月のレコードを抽出
            $monthRecords = $records->filter(function (AttendanceRecord $record) use ($monthKey): bool {
                return Carbon::parse($record->date)->format('Y-m') === $monthKey;
            });

            $monthWorkMinutes = 0;
            $monthOvertimeMinutes = 0;

            foreach ($monthRecords as $record) {
                $workSec = $this->calculateWorkSeconds($record);
                if ($workSec > 0) {
                    $workMin = (int) floor($workSec / 60);
                    $monthWorkMinutes += $workMin;

                    // 1日8時間（480分）を超える分を残業時間として算出
                    if ($workMin > 480) {
                        $monthOvertimeMinutes += ($workMin - 480);
                    }

                    $workDaysCount++;
                }
            }

            $totalWorkMinutes += $monthWorkMinutes;
            $totalOvertimeMinutes += $monthOvertimeMinutes;

            $monthlyTrend[] = [
                'month' => $monthLabel,
                'work_minutes' => $monthWorkMinutes,
                'overtime_minutes' => $monthOvertimeMinutes,
            ];
        }

        // 基本サマリー
        $avgWorkMinutes = $workDaysCount > 0 ? (int) round($totalWorkMinutes / $workDaysCount) : 0;

        $summary = [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $totalOvertimeMinutes,
            'avg_work_minutes' => $avgWorkMinutes,
        ];

        // 2. 異常検知（直近月＝前月のデータで集計）
        $lastMonthKey = $now->copy()->subMonth()->format('Y-m');
        $lastMonthRecords = $records->filter(function (AttendanceRecord $record) use ($lastMonthKey): bool {
            return Carbon::parse($record->date)->format('Y-m') === $lastMonthKey;
        });

        $lateCount = 0;
        $earlyLeaveCount = 0;
        $longWorkCount = 0;

        foreach ($lastMonthRecords as $record) {
            if (! $record->clock_in || ! $record->clock_out) {
                continue;
            }

            $clockInTime = Carbon::parse($record->clock_in)->format('H:i');
            $clockOutTime = Carbon::parse($record->clock_out)->format('H:i');

            // 遅刻：出勤時刻 > 09:00
            if ($clockInTime > '09:00') {
                $lateCount++;
            }

            // 早退：退勤時刻 < 18:00
            if ($clockOutTime < '18:00') {
                $earlyLeaveCount++;
            }

            // 長時間労働：実働 10時間（600分）超
            $workSec = $this->calculateWorkSeconds($record);
            if ($workSec > 600 * 60) {
                $longWorkCount++;
            }
        }

        $anomalies = [
            'late_count' => $lateCount,
            'early_leave_count' => $earlyLeaveCount,
            'long_work_count' => $longWorkCount,
        ];

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }

    /**
     * 1日あたりの実労働時間（秒）を算出
     */
    private function calculateWorkSeconds(AttendanceRecord $record): int
    {
        if (! $record->clock_in || ! $record->clock_out) {
            return 0;
        }

        $in = Carbon::parse($record->clock_in);
        $out = Carbon::parse($record->clock_out);
        $workSeconds = $out->diffInSeconds($in) - $record->total_break_seconds;

        return $workSeconds > 0 ? $workSeconds : 0;
    }
}
