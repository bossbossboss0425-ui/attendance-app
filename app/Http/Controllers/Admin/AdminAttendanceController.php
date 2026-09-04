<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Models\BreakRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminAttendanceController extends Controller
{
    // 勤怠一覧の表示
    public function index(Request $request)
    {
        // リクエストされた日付を取得（指定がない場合は当日）
        $dateInput = $request->query('date');
        $date = $dateInput ? Carbon::parse($dateInput) : Carbon::today();

        // 前日・翌日の日付文字列（Y-m-d形式）
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // ユーザー一覧を取得
        $users = User::all();

        // 対象日付の全ユーザーの勤怠レコードを取得
        $attendanceRecords = AttendanceRecord::whereDate('date', $date->format('Y-m-d'))->get();

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }


    // 管理者用勤怠一覧の表示
    public function show($id)
    {
        $record = AttendanceRecord::with(['user', 'breakRecords'])->findOrFail($id);

        $attendanceRecord = [
            'id' => $record->id,
            'year' => Carbon::parse($record->date)->format('Y年'),
            'date' => Carbon::parse($record->date)->format('m月d日'),
            'clock_in' => $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
            'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
            'comment' => $record->comment ?? '',
            'breaks' => $record->breakRecords->map(function ($break) {
                return [
                    'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                    'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
                ];
            })->toArray(),
        ];

        return view('admin.admin-detail', [
            'user' => $record->user,
            'attendanceRecord' => $attendanceRecord,
        ]);
    }

    // 管理者用勤怠修正処理
    public function update(AdminAttendanceUpdateRequest $request, $id)
    {
        // バリデーション通過後のデータを取得
        $validated = $request->validated();

        $record = AttendanceRecord::findOrFail($id);
        $dateStr = Carbon::parse($record->date)->format('Y-m-d');

        // 出勤・退勤時刻の更新
        $record->clock_in = !empty($validated['new_clock_in']) ? $dateStr . ' ' . $validated['new_clock_in'] : null;
        $record->clock_out = !empty($validated['new_clock_out']) ? $dateStr . ' ' . $validated['new_clock_out'] : null;
        $record->comment = $validated['comment'];
        $record->save();

        // 休憩時間の更新（既存の休憩を一度削除して再登録）
        $record->breakRecords()->delete();

        if ($request->has('new_break_in')) {
            foreach ($request->new_break_in as $index => $breakIn) {
                $breakOut = $request->new_break_out[$index] ?? null;

                if ($breakIn || $breakOut) {
                    BreakRecord::create([
                        'attendance_record_id' => $record->id,
                        'break_in' => $breakIn ? $dateStr . ' ' . $breakIn : null,
                        'break_out' => $breakOut ? $dateStr . ' ' . $breakOut : null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.attendance.index')->with('success', '勤怠情報を更新しました');
    }

}