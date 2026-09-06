<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\BreakRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create(['name' => '一般太郎']);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '通常勤務',
        ]);

        BreakRecord::create([
            'attendance_record_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        // Act（管理者として勤怠詳細画面を取得）
        $response = $this->actingAs($admin)->get(route('admin.attendance.detail', ['id' => $attendance->id]));

        // Assert（検証：選択したデータが画面上に反映されていること）
        $response->assertStatus(200)
            ->assertSee('一般太郎')
            ->assertSee('09:00')
            ->assertSee('18:00')
            ->assertSee('12:00')
            ->assertSee('13:00')
            ->assertSee('通常勤務');
    }

    /** @test */
    public function 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正テスト',
        ]);

        // Act（出勤時間を退勤時間より後に設定して保存処理を実行）
        $response = $this->actingAs($admin)->post('/attendance/'.$attendance->id, [
            'name' => $user->name,
            'new_date' => '2026-09-04',
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'comment' => '修正テスト',
        ]);

        // Assert（検証：バリデーションエラーが発生し、該当のメッセージが表示されること）
        $response->assertSessionHasErrors(['new_clock_in']);

        $followUp = $this->actingAs($admin)->get(route('admin.attendance.detail', ['id' => $attendance->id]));
        $followUp->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /** @test */
    public function 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正テスト',
        ]);

        // Act（休憩開始時間を退勤時間より後に設定して保存処理を実行）
        $response = $this->actingAs($admin)->post('/attendance/'.$attendance->id, [
            'name' => $user->name,
            'new_date' => '2026-09-04',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['20:00'],
            'comment' => '修正テスト',
        ]);

        // Assert（検証：バリデーションエラーが発生し、該当のメッセージが表示されること）
        $response->assertSessionHasErrors(['new_break_in.0']);

        $followUp = $this->actingAs($admin)->get(route('admin.attendance.detail', ['id' => $attendance->id]));
        $followUp->assertSee('休憩時間が不適切な値です');
    }

    /** @test */
    public function 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '修正テスト',
        ]);

        // Act（休憩終了時間を退勤時間より後に設定して保存処理を実行）
        $response = $this->actingAs($admin)->post('/attendance/'.$attendance->id, [
            'name' => $user->name,
            'new_date' => '2026-09-04',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['17:00'],
            'new_break_out' => ['19:00'],
            'comment' => '修正テスト',
        ]);

        // Assert（検証：バリデーションエラーが発生し、該当のメッセージが表示されること）
        $response->assertSessionHasErrors(['new_break_out.0']);

        $followUp = $this->actingAs($admin)->get(route('admin.attendance.detail', ['id' => $attendance->id]));
        $followUp->assertSee('休憩時間もしくは退勤時間が不適切な値です');
    }

    /** @test */
    public function 備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);
        $user = User::factory()->create();

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-04',
            'status' => '退勤済',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '既存の備考',
        ]);

        // Act（備考欄を空にして保存処理を実行）
        $response = $this->actingAs($admin)->post('/attendance/'.$attendance->id, [
            'name' => $user->name,
            'new_date' => '2026-09-04',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '',
        ]);

        // Assert（検証：備考のバリデーションエラーが発生し、該当メッセージが表示されること）
        $response->assertSessionHasErrors(['comment']);

        $followUp = $this->actingAs($admin)->get(route('admin.attendance.detail', ['id' => $attendance->id]));
        $followUp->assertSee('備考を記入してください');
    }
}
