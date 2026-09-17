<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordWriteTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function pos_tで勤怠が作成される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $postData = [
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'status' => '退勤',
            'comment' => '出勤テスト',
        ];

        // Act（実行）
        $response = $this->postJson('/api/v1/attendance-records', $postData);

        // Assert（検証）
        $response->assertStatus(201);

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'status' => '退勤',
            'comment' => '出勤テスト',
        ]);
    }

    /** @test */
    public function バリデーションエラー時に422と日本語エラーメッセージが返る(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // 必須項目（dateなど）が欠落した不正データ
        $invalidData = [
            'comment' => 'データ欠落',
        ];

        // Act（実行）
        $response = $this->postJson('/api/v1/attendance-records', $invalidData);

        // Assert（検証）
        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => ['date'],
            ]);

        // 日本語エラーメッセージが含まれることを検証
        $this->assertNotEmpty($response->json('errors.date.0'));
    }

    /** @test */
    public function pu_tで勤怠が更新される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => '退勤',
            'comment' => '変更前',
        ]);

        // date_format:H:i に合わせて秒を外した形式で送信
        $updateData = [
            'date' => '2026-05-01',
            'clock_in' => '10:00',
            'clock_out' => '19:00',
            'status' => '退勤',
            'comment' => '時間変更',
        ];

        // Act（実行）
        $response = $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", $updateData);

        // Assert（検証）
        $response->assertStatus(200);

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendanceRecord->id,
            'clock_in' => '10:00',
            'clock_out' => '19:00',
            'comment' => '時間変更',
        ]);
    }

    /** @test */
    public function 存在しない_i_dに対する_pu_tは404を返す(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $invalidId = 99999;
        $updateData = [
            'date' => '2026-05-01',
            'clock_in' => '10:00:00',
        ];

        // Act（実行）
        $response = $this->putJson("/api/v1/attendance-records/{$invalidId}", $updateData);

        // Assert（検証）
        $response->assertStatus(404)
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    /** @test */
    public function delet_eで勤怠が削除される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'status' => '退勤',
        ]);

        // Act（実行）
        $response = $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}");

        // Assert（検証）
        $response->assertStatus(204);

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    /** @test */
    public function 存在しない_i_dに対する_delet_eは404を返す(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $invalidId = 99999;

        // Act（実行）
        $response = $this->deleteJson("/api/v1/attendance-records/{$invalidId}");

        // Assert（検証）
        $response->assertStatus(404)
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }
}
