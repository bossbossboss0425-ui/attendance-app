<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    // スタッフ一覧画面を表示
    public function index()
    {
        // 管理者以外の全一般ユーザーを取得
        $users = User::where('admin_status', 0)->get();

        return view('admin.staff-list', compact('users'));
    }

    // スタッフ別勤怠一覧
    public function attendance(Request $request, $id)
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

        // 該当月の勤怠データを取得して日付キーの連想配列にする
        $attendanceRecords = AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(function ($record) {
                return Carbon::parse($record->date)->format('Y-m-d');
            });

        // 対象月の日付を1日〜末日までループ処理してデータを作成
        $formattedAttendanceRecords = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $record = $attendanceRecords->get($dateStr);

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null,
                'date' => $currentDate->format('m/d').'('.$currentDate->isoFormat('dd').')',
                'clock_in' => ($record && $record->clock_in) ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => ($record && $record->clock_out) ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $record ? $record->total_break_time : null,
                'total_time' => $record ? $record->total_time : null,
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
