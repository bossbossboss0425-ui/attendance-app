<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 自分が行った勤怠情報が全て表示されている(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        // 当月内に2件の勤怠データを登録
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->startOfMonth()->toDateString(),
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->startOfMonth()->addDays(1)->toDateString(),
            'status' => '退勤済',
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
        ]);

        // Act（一覧画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // Assert（検証：すべての勤怠データが表示されていること）
        $response->assertStatus(200)
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('09:30')
            ->assertSee('18:30');
    }

    /** @test */
    public function 勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Carbon::setTestNow(Carbon::create(2026, 9, 4));

        // Act（一覧画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.index'));

        // Assert（検証：現在の月が表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026/09');

        Carbon::setTestNow();
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Carbon::setTestNow(Carbon::create(2026, 9, 4));

        // 8月の勤怠データ作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-08-15',
            'status' => '退勤済',
            'clock_in' => '08:45:00',
            'clock_out' => '17:45:00',
        ]);

        // Act（前月パラメータ付きで一覧画面を取得）
        $response = $this->actingAs($user)->get(route('attendance.index', ['date' => '2026-08']));

        // Assert（検証：前月のデータが表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026/08')
            ->assertSee('08:45');

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Carbon::setTestNow(Carbon::create(2026, 9, 4));

        // 10月の勤怠データ作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-10-10',
            'status' => '退勤済',
            'clock_in' => '09:15:00',
            'clock_out' => '18:15:00',
        ]);

        // Act（翌月パラメータ付きで一覧画面を取得）
        $response = $this->actingAs($user)->get(route('attendance.index', ['date' => '2026-10']));

        // Assert（検証：翌月のデータが表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026/10')
            ->assertSee('09:15');

        Carbon::setTestNow();
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Act & Assert（一覧画面で詳細リンクが存在することを確認）
        $indexResponse = $this->actingAs($user)->get(route('attendance.index'));
        $indexResponse->assertStatus(200)
            ->assertSee(route('attendance.detail', $record->id));

        // Act & Assert（詳細画面へアクセスして正常表示されることを確認）
        $detailResponse = $this->actingAs($user)->get(route('attendance.detail', $record->id));
        $detailResponse->assertStatus(200);
    }
}
