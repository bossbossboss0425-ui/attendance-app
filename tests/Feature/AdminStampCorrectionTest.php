<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStampCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 承認待ちの修正申請が全て表示されている(): void
    {
        // Arrange
        $admin = User::factory()->create(['admin_status' => 1]);
        $user = User::factory()->create(['admin_status' => 0]);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'status' => '退勤',
        ]);

        StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => '承認待ち',
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '電車遅延のため修正申請します',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.application.list', ['tab' => 'pending']));

        // Assert
        $response->assertStatus(200);
        $response->assertSee('電車遅延のため修正申請します');
    }

    /** @test */
    public function 承認済みの修正申請が全て表示されている(): void
    {
        // Arrange
        $admin = User::factory()->create(['admin_status' => 1]);
        $user = User::factory()->create(['admin_status' => 0]);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'status' => '退勤',
        ]);

        StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => '承認済み',
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '打刻忘れの修正完了',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(route('admin.application.list', ['tab' => 'approved']));

        // Assert
        $response->assertStatus(200);
        $response->assertSee('打刻忘れの修正完了');
    }

    /** @test */
    public function 修正申請の詳細内容が正しく表示されている(): void
    {
        // Arrange
        $admin = User::factory()->create(['admin_status' => 1]);
        $user = User::factory()->create([
            'name' => 'テスト太郎',
            'admin_status' => 0,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'status' => '退勤',
        ]);

        $application = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => '承認待ち',
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '出勤時間の誤りを修正',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->get(url('/stamp_correction_request/approve/'.$application->id));

        // Assert
        $response->assertStatus(200);
        $response->assertSee('テスト太郎');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('出勤時間の誤りを修正');
    }

    /** @test */
    public function 修正申請の承認処理が正しく行われる(): void
    {
        // Arrange
        $admin = User::factory()->create(['admin_status' => 1]);
        $user = User::factory()->create(['admin_status' => 0]);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-09-01',
            'clock_in' => '2026-09-01 10:00:00',
            'clock_out' => '2026-09-01 19:00:00',
            'status' => '退勤',
        ]);

        $application = StampCorrectionRequest::create([
            'attendance_record_id' => $attendance->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'new_clock_in' => '09:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '時間の修正',
        ]);

        // Act
        $response = $this->actingAs($admin)
            ->post(url('/stamp_correction_request/approve/'.$application->id));

        // Assert
        // 1. 申請テーブルのステータスが 'approved' に更新されたか
        $this->assertDatabaseHas('stamp_correction_requests', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        // 2. 勤怠レコード本体に日付＋時刻の形式（Y-m-d H:i:s）で反映されたか
        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'clock_in' => '2026-09-01 09:00:00',
            'clock_out' => '2026-09-01 18:00:00',
        ]);
    }
}
