<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤務外の場合、勤怠ステータスが正しく表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        // Act（打刻画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証：「勤務外」が表示されていること）
        $response->assertStatus(200)
            ->assertSee('勤務外');
    }

    /** @test */
    public function 出勤中の場合、勤怠ステータスが正しく表示される(): void
    {
        // Arrange（準備：出勤中データを作成）
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '出勤中',
            'clock_in' => '09:00:00',
        ]);

        // Act（打刻画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証：「出勤中」が表示されていること）
        $response->assertStatus(200)
            ->assertSee('出勤中');
    }

    /** @test */
    public function 休憩中の場合、勤怠ステータスが正しく表示される(): void
    {
        // Arrange（準備：休憩中データを作成）
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '休憩中',
            'clock_in' => '09:00:00',
        ]);

        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        // Act（打刻画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証：「休憩中」が表示されていること）
        $response->assertStatus(200)
            ->assertSee('休憩中');
    }

    /** @test */
    public function 退勤済の場合、勤怠ステータスが正しく表示される(): void
    {
        // Arrange（準備：退勤済データを作成）
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Act（打刻画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証：「退勤済」が表示されていること）
        $response->assertStatus(200)
            ->assertSee('退勤済');
    }
}
