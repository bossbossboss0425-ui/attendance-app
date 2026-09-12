<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    /**
     * スタッフ一覧画面を表示
     */
    public function index(): View
    {
        // 管理者以外の全一般ユーザーを取得
        $users = User::where('admin_status', 0)->get();

        return view('admin.staff-list', compact('users'));
    }

    /**
     * スタッフ別勤怠一覧
     */
    public function attendance(Request $request, int $id): View
    {
        // 対象スタッフを取得（一般ユーザー以外なら404）
        $user = User::where('admin_status', 0)->findOrFail($id);

        // 表示対象年月を取得（指定がなければ当月）
        $queryDate = $request->input('date');
        $date = $queryDate ? Carbon::parse($queryDate)->firstOfMonth() : Carbon::now()->firstOfMonth();

        // 前月・翌月のパラメータ文字列を作成（YYYY-MM）
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 月の開始日と終了日を取得
        $startDate = $date->copy()->startOfMonth();
        $endDate = $date->copy()->endOfMonth();

        // 該当月の勤怠データを休憩レコードと一緒に取得して日付キーの連想配列にする
        $attendanceRecords = AttendanceRecord::with('breakRecords')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(function (AttendanceRecord $record): string {
                return Carbon::parse($record->date)->format('Y-m-d');
            });

        // 対象月の日付を1日〜末日までループ処理してデータを作成
        $formattedAttendanceRecords = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $record = $attendanceRecords->get($dateStr);

            $totalBreakTime = null;
            $totalTime = null;

            if ($record) {
                // --- 休憩合計時間の計算（分換算） ---
                $totalBreakMinutes = 0;
                foreach ($record->breakRecords as $break) {
                    if ($break->break_in && $break->break_out) {
                        $totalBreakMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
                    }
                }

                // Bladeの Carbon::parse() が解釈できる "HH:mm:ss" 形式で文字列作成
                if ($totalBreakMinutes > 0) {
                    $hours = floor($totalBreakMinutes / 60);
                    $minutes = $totalBreakMinutes % 60;
                    $totalBreakTime = sprintf('%02d:%02d:00', $hours, $minutes);
                }

                // --- 勤務合計時間の計算（退勤 - 出勤 - 休憩） ---
                if ($record->clock_in && $record->clock_out) {
                    $workMinutes = Carbon::parse($record->clock_in)->diffInMinutes(Carbon::parse($record->clock_out)) - $totalBreakMinutes;
                    if ($workMinutes > 0) {
                        $hours = floor($workMinutes / 60);
                        $minutes = $workMinutes % 60;
                        $totalTime = sprintf('%02d:%02d:00', $hours, $minutes);
                    }
                }
            }

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null,
                'date' => $currentDate->format('m/d').'('.$currentDate->isoFormat('dd').')',
                'clock_in' => ($record && $record->clock_in) ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => ($record && $record->clock_out) ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $totalBreakTime,
                'total_time' => $totalTime,
            ];

            $currentDate->addDay();
        }

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }
}
