<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClockOutTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 退勤ボタンが正しく機能する(): void
    {
        // Arrange（準備：出勤中データを作成）
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '出勤中',
            'clock_in' => '09:00:00',
        ]);

        // Act & Assert（画面確認 1：退勤ボタンの表示確認）
        $response = $this->actingAs($user)->get(route('attendance.create'));
        $response->assertStatus(200)
            ->assertSee('退勤');

        // Act（退勤処理の実行）
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'clock_out',
        ]);

        // Assert（ステータス確認：「退勤済」に変更されていること）
        $followResponse = $this->actingAs($user)->get(route('attendance.create'));
        $followResponse->assertStatus(200)
            ->assertSee('退勤済');
    }

    /** @test */
    public function 退勤時刻が勤怠一覧画面で確認できる(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        // Act（実行：09:00に出勤 -> 18:00に退勤）
        Carbon::setTestNow(Carbon::create(2026, 9, 4, 9, 0, 0));
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'clock_in',
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 4, 18, 0, 0));
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'clock_out',
        ]);

        // Act & Assert（一覧画面の確認：18:00が表示されていること）
        $response = $this->actingAs($user)->get(route('attendance.index'));
        $response->assertStatus(200)
            ->assertSee('18:00');

        Carbon::setTestNow();
    }
}
