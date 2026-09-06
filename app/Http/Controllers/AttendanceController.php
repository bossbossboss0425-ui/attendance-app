<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceUpdateRequest;
use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    // 出勤登録画面（打刻画面）の表示

    public function create()
    {
        $user = Auth::user();
        $now = Carbon::now();

        // 曜日付きの日付表示（例: 2026年08月23日(日)）
        $week = ['日', '月', '火', '水', '木', '金', '土'];
        $formattedDate = $now->format('Y年m月d日').'('.$week[$now->dayOfWeek].')';
        $formattedTime = $now->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    // 打刻登録処理

    public function store(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->toTimeString();

        // 今日の勤怠レコードを取得（無ければインスタンス作成）
        $attendance = AttendanceRecord::firstOrNew([
            'user_id' => $user->id,
            'date' => $today,
        ]);

        switch ($request->action) {
            case 'clock_in': // 出勤
                if ($attendance->exists) {
                    return redirect()->back()->with('error', '本日は既に出勤しています。');
                }
                $attendance->clock_in = $currentTime;
                $attendance->status = '出勤中';
                $attendance->save();
                break;

            case 'break_in': // 休憩入
                if ($attendance->status === '出勤中') {
                    $attendance->status = '休憩中';
                    $attendance->save();

                    // 休憩レコードを新規作成
                    $attendance->breakRecords()->create([
                        'break_in' => $currentTime,
                    ]);
                }
                break;

            case 'break_out': // 休憩戻
                if ($attendance->status === '休憩中') {
                    $attendance->status = '出勤中';
                    $attendance->save();

                    // 最新の未完了な休憩レコード（break_out が null）を取得して刻印
                    $latestBreak = $attendance->breakRecords()
                        ->whereNull('break_out')
                        ->latest()
                        ->first();

                    if ($latestBreak) {
                        $latestBreak->update([
                            'break_out' => $currentTime,
                        ]);
                    }
                }
                break;

            case 'clock_out': // 退勤
                if ($attendance->status === '出勤中') {
                    $attendance->clock_out = $currentTime;
                    $attendance->status = '退勤済';
                    $attendance->save();
                }
                break;
        }

        return redirect()->route('attendance.create');
    }

    // 勤怠一覧画面の表示

    public function index(Request $request)
    {
        $user = Auth::user();

        // date=YYYY-MM を取得（無ければ今月）
        $dateParam = $request->query('date');
        try {
            $date = $dateParam ? Carbon::parse($dateParam)->firstOfMonth() : Carbon::now()->firstOfMonth();
        } catch (\Exception $e) {
            $date = Carbon::now()->firstOfMonth();
        }

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        // 対象月の打刻データを取得
        $attendanceRecords = AttendanceRecord::with('breakRecords')
            ->where('user_id', $user->id)
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->get()
            ->keyBy('date');

        // 対象月の日数分、一覧用データを作成（1日〜月末）
        $daysInMonth = $date->daysInMonth;
        $formattedAttendanceRecords = [];

        $week = ['日', '月', '火', '水', '木', '金', '土'];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $currentDate = $date->copy()->day($day);
            $dateStr = $currentDate->toDateString();
            $record = $attendanceRecords->get($dateStr);

            // Bladeに合わせて整形
            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null,
                'date' => $currentDate->format('m/d').'('.$week[$currentDate->dayOfWeek].')',
                'clock_in' => $record && $record->clock_in ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $record ? $record->formatted_total_break_time : '',
                'total_time' => $record ? $record->formatted_total_time : '',
            ];
        }

        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    // 勤怠詳細画面の表示
    public function show($id)
    {
        // 勤怠レコードと関連データを取得
        $attendanceRecord = AttendanceRecord::with(['breakRecords', 'stampCorrectionRequests'])
            ->findOrFail($id);

        // 本人以外のアクセス制限
        if ($attendanceRecord->user_id !== Auth::id()) {
            abort(403);
        }

        $user = Auth::user();

        // 該当の勤怠レコードに紐づく最新の修正申請を取得（proposalBreaksもEager Load）
        $application = StampCorrectionRequest::with('proposalBreaks')
            ->where('attendance_record_id', $id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $recordDate = Carbon::parse($attendanceRecord->date);

        // --- 1. 出退勤時間の判定 ---
        if ($application) {
            $clockIn = $application->new_clock_in ? Carbon::parse($application->new_clock_in)->format('H:i') : '';
            $clockOut = $application->new_clock_out ? Carbon::parse($application->new_clock_out)->format('H:i') : '';
        } else {
            $clockIn = $attendanceRecord->clock_in ? Carbon::parse($attendanceRecord->clock_in)->format('H:i') : '';
            $clockOut = $attendanceRecord->clock_out ? Carbon::parse($attendanceRecord->clock_out)->format('H:i') : '';
        }

        // --- 2. 休憩データの判定 ---
        $breaks = [];
        if ($application && $application->proposalBreaks->isNotEmpty()) {
            foreach ($application->proposalBreaks as $break) {
                $breaks[] = [
                    'break_in' => $break->new_break_in ? Carbon::parse($break->new_break_in)->format('H:i') : '',
                    'break_out' => $break->new_break_out ? Carbon::parse($break->new_break_out)->format('H:i') : '',
                ];
            }
        } else {
            foreach ($attendanceRecord->breakRecords as $break) {
                $breaks[] = [
                    'break_in' => $break->break_in ? Carbon::parse($break->break_in)->format('H:i') : '',
                    'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
                ];
            }
        }

        // --- 3. 備考（コメント）の判定 ---
        // 申請があれば申請理由（comment）、なければ元の勤怠のコメントを表示
        $comment = $application ? $application->comment : ($attendanceRecord->comment ?? '');

        $pendingApplication = ($application && $application->status === 'pending') ? $application : null;

        // --- 4. Bladeに渡す $data 配列の作成 ---
        $data = [
            'id' => $attendanceRecord->id,
            'application' => $pendingApplication,
            'year' => $recordDate->format('Y年'),
            'date' => $recordDate->format('m月d日'),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'breaks' => $breaks,
            'comment' => $comment,
        ];

        return view('user.user-detail', compact('data', 'user'));
    }

    // 修正申請の送信処理
    public function update(AttendanceUpdateRequest $request, $id)
    {
        $attendance = AttendanceRecord::findOrFail($id);

        // 二重申請チェック
        $existingRequest = StampCorrectionRequest::where('attendance_record_id', $attendance->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return back()->withErrors(['comment' => '承認待ちの申請があるため修正できません']);
        }

        // DBへの保存
        $correctionRequest = StampCorrectionRequest::create([
            'user_id' => Auth::id(),
            'attendance_record_id' => $attendance->id,
            'status' => 'pending',
            'new_clock_in' => $request->new_clock_in,
            'new_clock_out' => $request->new_clock_out,
            'comment' => $request->comment,
        ]);

        // 提案休憩データの保存
        if ($request->has('new_break_in') && $request->has('new_break_out')) {
            foreach ($request->new_break_in as $index => $breakIn) {
                $breakOut = $request->new_break_out[$index] ?? null;

                if (! empty($breakIn) && ! empty($breakOut)) {
                    $targetDate = $attendance->date;
                    $correctionRequest->proposalBreaks()->create([
                        'new_break_in' => $breakIn,
                        'new_break_out' => $breakOut,
                    ]);
                }
            }
        }

        return redirect()->route('stamp_correction_request.list');
    }
}
