<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{
    /**
     * 勤怠一覧の表示
     */
    public function index(Request $request): View
    {
        // リクエストされた日付を取得（指定がない場合は当日）
        $dateInput = $request->query('date');
        $date = $dateInput ? Carbon::parse($dateInput) : Carbon::today();

        // 前日・翌日の日付文字列（Y-m-d形式）
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        // ユーザー一覧を取得
        $users = User::all();

        // 勤怠レコードと休憩レコードを取得
        $rawAttendanceRecords = AttendanceRecord::with(['user', 'breakRecords'])
            ->whereDate('date', $date->format('Y-m-d'))
            ->get();

        $attendanceRecords = $rawAttendanceRecords->map(function (AttendanceRecord $record): AttendanceRecord {
            // --- 休憩合計時間の計算 ---
            $totalBreakMinutes = 0;
            foreach ($record->breakRecords as $break) {
                if ($break->break_in && $break->break_out) {
                    $totalBreakMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
                }
            }

            // Carbon::parse() がパースできるように "HH:mm:ss" 形式で文字列を作る
            $totalBreakTime = null;
            if ($totalBreakMinutes > 0) {
                $hours = floor($totalBreakMinutes / 60);
                $minutes = $totalBreakMinutes % 60;
                $totalBreakTime = sprintf('%02d:%02d:00', $hours, $minutes);
            }

            // --- 勤務合計時間の計算 ---
            $totalTime = null;
            if ($record->clock_in && $record->clock_out) {
                $workMinutes = Carbon::parse($record->clock_in)->diffInMinutes(Carbon::parse($record->clock_out)) - $totalBreakMinutes;
                if ($workMinutes > 0) {
                    $hours = floor($workMinutes / 60);
                    $minutes = $workMinutes % 60;
                    $totalTime = sprintf('%02d:%02d:00', $hours, $minutes);
                }
            }

            // プロパティにセット
            $record->total_break_time = $totalBreakTime;
            $record->total_time = $totalTime;

            return $record;
        });

        return view('admin.admin-attendance-list', compact(
            'date',
            'previousDay',
            'nextDay',
            'users',
            'attendanceRecords'
        ));
    }

    /**
     * 管理者用勤怠詳細の表示
     */
    public function show(int $id): View
    {
        $record = AttendanceRecord::with(['user', 'breakRecords'])->findOrFail($id);

        $attendanceRecord = [
            'id' => $record->id,
            'year' => Carbon::parse($record->date)->format('Y年'),
            'date' => Carbon::parse($record->date)->format('m月d日'),
            'clock_in' => $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
            'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
            'comment' => $record->comment ?? '',
            'breaks' => $record->breakRecords->map(function (BreakRecord $break): array {
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

    /**
     * 管理者用勤怠修正処理
     */
    public function update(AdminAttendanceUpdateRequest $request, int $id): RedirectResponse
    {
        // バリデーション通過後のデータを取得
        $validated = $request->validated();

        $record = AttendanceRecord::findOrFail($id);
        $dateStr = Carbon::parse($record->date)->format('Y-m-d');

        // 出勤・退勤時刻の更新
        $record->clock_in = ! empty($validated['new_clock_in']) ? $dateStr.' '.$validated['new_clock_in'] : null;
        $record->clock_out = ! empty($validated['new_clock_out']) ? $dateStr.' '.$validated['new_clock_out'] : null;
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
                        'break_in' => $breakIn ? $dateStr.' '.$breakIn : null,
                        'break_out' => $breakOut ? $dateStr.' '.$breakOut : null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.attendance.index');
    }

    /**
     * CSV出力
     */
    public function export(Request $request): StreamedResponse
    {
        $userId = $request->input('user_id');
        $yearMonth = $request->input('year_month'); // "YYYY-MM" 形式

        // 該当ユーザーに対象年月の勤怠データを取得（日付昇順）
        $records = AttendanceRecord::with('breakRecords')
            ->where('user_id', $userId)
            ->where('date', 'like', $yearMonth.'%')
            ->orderBy('date', 'asc')
            ->get();

        // ダウンロード時のファイル名指定（例: attendance_2026-09.csv）
        $fileName = 'attendance_'.$yearMonth.'.csv';

        $response = new StreamedResponse(function () use ($records): void {
            $stream = fopen('php://output', 'w');

            // 文字化け（Excel等）対策用 BOM の追加
            fwrite($stream, "\xEF\xBB\xBF");

            // CSVヘッダー行
            fputcsv($stream, ['日付', '出勤', '退勤', '休憩時間', '合計時間']);

            // データ行の書き込み
            foreach ($records as $record) {
                $clockIn = $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '';
                $clockOut = $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '';

                // 休憩時間の計算
                $totalBreakMinutes = 0;
                foreach ($record->breakRecords as $break) {
                    if ($break->break_in && $break->break_out) {
                        $totalBreakMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
                    }
                }
                $breakFormatted = $totalBreakMinutes > 0
                    ? sprintf('%d:%02d', floor($totalBreakMinutes / 60), $totalBreakMinutes % 60)
                    : '';

                // 総勤務時間の計算
                $totalWorkFormatted = '';
                if ($record->clock_in && $record->clock_out) {
                    $totalMinutes = Carbon::parse($record->clock_in)->diffInMinutes(Carbon::parse($record->clock_out)) - $totalBreakMinutes;
                    if ($totalMinutes > 0) {
                        $totalWorkFormatted = sprintf('%d:%02d', floor($totalMinutes / 60), $totalMinutes % 60);
                    }
                }

                fputcsv($stream, [
                    $record->date,
                    $clockIn,
                    $clockOut,
                    $breakFormatted,
                    $totalWorkFormatted,
                ]);
            }

            fclose($stream);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$fileName.'"');

        return $response;
    }
}
