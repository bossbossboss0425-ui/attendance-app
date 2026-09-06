<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user1 = User::factory()->create(['name' => 'テストユーザー1']);
        $user2 = User::factory()->create(['name' => 'テストユーザー2']);

        $today = now()->toDateString();

        AttendanceRecord::create([
            'user_id' => $user1->id,
            'date' => $today,
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'total_break_time' => '01:00:00',
            'total_time' => '08:00:00',
        ]);

        AttendanceRecord::create([
            'user_id' => $user2->id,
            'date' => $today,
            'status' => '退勤済',
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'total_break_time' => '01:00:00',
            'total_time' => '08:00:00',
        ]);

        // Act（管理者として一覧画面を取得）
        $response = $this->actingAs($admin)->get(route('admin.attendance.index'));

        // Assert（検証：全ユーザーの情報が正確に表示されていること）
        $response->assertStatus(200)
            ->assertSee('テストユーザー1')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('テストユーザー2')
            ->assertSee('09:30')
            ->assertSee('18:30');
    }

    /** @test */
    public function 遷移した際に現在の日付が表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        Carbon::setTestNow(Carbon::create(2026, 9, 4));

        // Act（管理者として一覧画面を取得）
        $response = $this->actingAs($admin)->get(route('admin.attendance.index'));

        // Assert（検証：画面上に現在の日付が表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026年09月04日の勤怠')
            ->assertSee('2026/09/04');

        Carbon::setTestNow();
    }

    /** @test */
    public function 前日を押下した時に前の日の勤怠情報が表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create(['name' => '前日テストユーザー']);

        Carbon::setTestNow(Carbon::create(2026, 9, 4));

        // 前日（2026-09-03）の勤怠データ作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-03',
            'status' => '退勤済',
            'clock_in' => '08:45:00',
            'clock_out' => '17:45:00',
            'total_break_time' => '01:00:00',
            'total_time' => '08:00:00',
        ]);

        // Act（前日パラメータ付きで一覧画面を取得）
        $response = $this->actingAs($admin)->get(route('admin.attendance.index', ['date' => '2026-09-03']));

        // Assert（検証：前日の日付とデータが表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026年09月03日の勤怠')
            ->assertSee('2026/09/03')
            ->assertSee('前日テストユーザー')
            ->assertSee('08:45');

        Carbon::setTestNow();
    }

    /** @test */
    public function 翌日を押下した時に次の日の勤怠情報が表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create(['name' => '翌日テストユーザー']);

        Carbon::setTestNow(Carbon::create(2026, 9, 4));

        // 翌日（2026-09-05）の勤怠データ作成
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-05',
            'status' => '退勤済',
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'total_break_time' => '01:00:00',
            'total_time' => '08:00:00',
        ]);

        // Act（翌日パラメータ付きで一覧画面を取得）
        $response = $this->actingAs($admin)->get(route('admin.attendance.index', ['date' => '2026-09-05']));

        // Assert（検証：翌日の日付とデータが表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026年09月05日の勤怠')
            ->assertSee('2026/09/05')
            ->assertSee('翌日テストユーザー')
            ->assertSee('10:00');

        Carbon::setTestNow();
    }
}
