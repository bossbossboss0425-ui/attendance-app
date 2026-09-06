<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceBreakTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 休憩ボタンが正しく機能する(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '出勤中',
            'clock_in' => '09:00:00',
        ]);

        // Act & Assert（画面確認 1）
        $response = $this->actingAs($user)->get(route('attendance.create'));
        $response->assertStatus(200)
            ->assertSee('休憩入');

        // Act（休憩入の処理実行）
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        // Assert（ステータス確認）
        $followResponse = $this->actingAs($user)->get(route('attendance.create'));
        $followResponse->assertStatus(200)
            ->assertSee('休憩中');
    }

    /** @test */
    public function 休憩は一日に何回でもできる(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '出勤中',
            'clock_in' => '09:00:00',
        ]);

        // 過去に1回休憩を取って復帰済み
        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // Act（実行）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証）
        $response->assertStatus(200)
            ->assertSee('休憩入');
    }

    /** @test */
    public function 休憩戻ボタンが正しく機能する(): void
    {
        // Arrange（準備）
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

        // Act & Assert（画面確認 1）
        $response = $this->actingAs($user)->get(route('attendance.create'));
        $response->assertStatus(200)
            ->assertSee('休憩戻');

        // Act（休憩戻の処理実行）
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // Assert（ステータス確認）
        $followResponse = $this->actingAs($user)->get(route('attendance.create'));
        $followResponse->assertStatus(200)
            ->assertSee('出勤中');
    }

    /** @test */
    public function 休憩戻は一日に何回でもできる(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '休憩中',
            'clock_in' => '09:00:00',
        ]);

        // 1回目の休憩（完了）
        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '12:15:00',
        ]);

        // 2回目の休憩（進行中）
        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '15:00:00',
            'break_out' => null,
        ]);

        // Act（実行）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証）
        $response->assertStatus(200)
            ->assertSee('休憩戻');
    }

    /** @test */
    public function 休憩時刻が勤怠一覧画面で確認できる(): void
    {
        // 1. テスト開始時に時刻を固定（2026-09-04 09:00:00）
        Carbon::setTestNow(Carbon::create(2026, 9, 4, 9, 0, 0));

        // Arrange（準備）
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => Carbon::now()->toDateString(), // 2026-09-04 になる
            'status' => '出勤中',
            'clock_in' => Carbon::now()->toTimeString(),
        ]);

        // Act（実行：12:00に休憩入 -> 13:00に休憩戻）
        Carbon::setTestNow(Carbon::create(2026, 9, 4, 12, 0, 0));
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'break_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 4, 13, 0, 0));
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'break_out',
        ]);

        // Act & Assert（一覧画面の確認）
        $response = $this->actingAs($user)->get(route('attendance.index'));
        $response->assertStatus(200)
            ->assertSee('1:00'); // 画面の表記形式に合わせて '01:00' や '1時間00分' になる場合は調整してください

        // テスト終了後に時刻固定を解除
        Carbon::setTestNow();
    }
}
