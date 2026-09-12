<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class User1AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first(); // user1 を取得
        if (! $user) {
            return;
        }

        // 基準日を「現在（9月）」とし、直近完了月を「8月」とする
        $baseDate = Carbon::create(2026, 9, 1);
        $startDate = $baseDate->copy()->subMonths(6)->startOfMonth(); // 3月1日
        $endDate = $baseDate->copy()->subMonth()->endOfMonth();       // 8月31日

        // AttendanceSeeder で作られた既存の3月〜8月データを一括削除
        AttendanceRecord::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->delete();

        // --- 1. 過去 5 ヶ月（3月〜7月）: 各月平日15日＝計75日の通常勤務 ---
        for ($i = 6; $i >= 2; $i--) {
            $monthStart = $baseDate->copy()->subMonths($i)->startOfMonth();
            $createdDays = 0;
            $currentDate = $monthStart->copy();

            while ($createdDays < 15) {
                if (! $currentDate->isWeekend()) {
                    $this->createRecordWithBreak(
                        $user->id,
                        $currentDate->toDateString(),
                        '09:00:00',
                        '18:00:00'
                    );
                    $createdDays++;
                }
                $currentDate->addDay();
            }
        }

        // --- 2. 直近完了月（8月 / 要件上の当月）17日分のパターン作成 ---
        $augustStart = Carbon::create(2026, 8, 1);
        $dateCursor = $augustStart->copy();

        // パターン定義（全17日分）
        $patterns = array_merge(
            array_fill(0, 10, ['09:00:00', '18:00:00']), // 通常 10日
            array_fill(0, 3, ['09:00:00', '20:00:00']), // 残業 3日 (9:00-20:00)
            array_fill(0, 2, ['09:30:00', '18:00:00']), // 遅刻 2日 (9:30-18:00)
            array_fill(0, 1, ['09:00:00', '17:00:00']), // 早退 1日 (9:00-17:00)
            array_fill(0, 1, ['08:00:00', '21:00:00'])  // 長時間 1日 (8:00-21:00)
        );

        foreach ($patterns as $times) {
            while ($dateCursor->isWeekend()) {
                $dateCursor->addDay();
            }

            $this->createRecordWithBreak(
                $user->id,
                $dateCursor->toDateString(),
                $times[0],
                $times[1]
            );

            $dateCursor->addDay();
        }
    }

    private function createRecordWithBreak(int $userId, string $date, string $clockIn, string $clockOut): void
    {
        $record = AttendanceRecord::create([
            'user_id' => $userId,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'status' => '退勤済',
        ]);

        BreakRecord::create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
