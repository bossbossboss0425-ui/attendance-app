<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClockInTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 出勤ボタンが正しく機能する(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        // Act & Assert（画面確認 1：出勤ボタンの表示確認）
        $response = $this->actingAs($user)->get(route('attendance.create'));
        $response->assertStatus(200)
            ->assertSee('出勤');

        // Act（出勤処理の実行）
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'clock_in',
        ]);

        // Assert（ステータス確認：「出勤中」に変更されていること）
        $followResponse = $this->actingAs($user)->get(route('attendance.create'));
        $followResponse->assertStatus(200)
            ->assertSee('出勤中');
    }

    /** @test */
    public function 出勤は一日一回のみできる(): void
    {
        // Arrange（準備：すでに退勤済みのデータを作成）
        $user = User::factory()->create();

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Act（打刻画面を開く）
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（出勤ボタンが表示されないことを確認）
        $response->assertStatus(200)
            ->assertDontSee('出勤');
    }

    /** @test */
    public function 出勤時刻が勤怠一覧画面で確認できる(): void
    {
        // Arrange（準備：テスト時刻を09:00:00に固定）
        Carbon::setTestNow(Carbon::create(2026, 9, 4, 9, 0, 0));
        $user = User::factory()->create();

        // Act（出勤処理の実行）
        $this->actingAs($user)->post(route('attendance.store'), [
            'action' => 'clock_in',
        ]);

        // Act & Assert（一覧画面の確認：09:00が表示されていること）
        $response = $this->actingAs($user)->get(route('attendance.index'));
        $response->assertStatus(200)
            ->assertSee('09:00');

        Carbon::setTestNow();
    }
}
