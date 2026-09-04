<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $startDate = Carbon::today()->subDays(30);
        $endDate = Carbon::yesterday();

        foreach ($users as $user) {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // 土日はスキップ
                if ($date->isWeekend()) {
                    continue;
                }

                $isOvertime = rand(0, 1) === 1;
                $clockIn = $isOvertime ? '08:30' : '09:00';
                $clockOut = $isOvertime ? '19:30' : '18:00';

                // 勤怠データ作成
                $attendance = AttendanceRecord::create([
                    'user_id' => $user->id,
                    'date' => $date->format('Y-m-d'),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'status' => '退勤済',
                    'comment' => null,
                ]);

                // 休憩データ1（昼休憩）
                BreakRecord::create([
                    'attendance_record_id' => $attendance->id,
                    'break_in' => '12:00',
                    'break_out' => '13:00',
                ]);

                // 残業時のみ休憩データ2（夕方休憩）
                if ($isOvertime) {
                    BreakRecord::create([
                        'attendance_record_id' => $attendance->id,
                        'break_in' => '17:30',
                        'break_out' => '18:00',
                    ]);
                }
            }
        }
    }
}
