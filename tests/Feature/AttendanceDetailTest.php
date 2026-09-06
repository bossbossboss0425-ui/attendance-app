<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細画面の「名前」がログインユーザーの氏名になっている(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'name' => 'テスト太郎',
        ]);

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Act（勤怠詳細画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.detail', $record->id));

        // Assert（検証：氏名が表示されていること）
        $response->assertStatus(200)
            ->assertSee('テスト太郎');
    }

    /** @test */
    public function 勤怠詳細画面の「日付」が選択した日付になっている(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Act（勤怠詳細画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.detail', $record->id));

        // Assert（検証：日付が表示されていること）
        $response->assertStatus(200)
            ->assertSee('2026')
            ->assertSee('9')
            ->assertSee('4');
    }

    /** @test */
    public function 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        // Act（勤怠詳細画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.detail', $record->id));

        // Assert（検証：出勤・退勤時間が表示されていること）
        $response->assertStatus(200)
            ->assertSee('09:00')
            ->assertSee('18:00');
    }

    /** @test */
    public function 「休憩」にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        if (class_exists(BreakRecord::class)) {
            BreakRecord::create([
                'attendance_record_id' => $record->id,
                'break_in' => '12:00:00',
                'break_out' => '13:00:00',
            ]);
        }

        // Act（勤怠詳細画面の取得）
        $response = $this->actingAs($user)->get(route('attendance.detail', $record->id));

        // Assert（検証：休憩開始・終了時間が表示されていること）
        $response->assertStatus(200)
            ->assertSee('12:00')
            ->assertSee('13:00');
    }
}
