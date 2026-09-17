<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordReadTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 勤怠一覧が_jso_nで取得できる(): void
    {
        // Arrange（準備）
        $user = User::factory()->create();

        for ($i = 0; $i < 15; $i++) {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => now()->subDays($i)->format('Y-m-d'),
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'status' => '退勤',
            ]);
        }

        // Act（実行）
        $response = $this->getJson('/api/v1/attendance-records');

        // Assert（検証）
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'user_name',
                        'date',
                        'clock_in',
                        'clock_out',
                        'status',
                        'total_time',
                        'total_break_time',
                        'comment',
                    ],
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /** @test */
    public function 勤怠詳細が_jso_nで取得できる(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'name' => 'テスト太郎',
        ]);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'status' => '退勤',
        ]);

        // Act（実行）
        $response = $this->getJson("/api/v1/attendance-records/{$attendanceRecord->id}");

        // Assert（検証）
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'user_name',
                    'date',
                    'clock_in',
                    'clock_out',
                    'status',
                    'total_time',
                    'total_break_time',
                    'comment',
                    'breaks',
                    'applications',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $attendanceRecord->id,
                    'user_id' => $user->id,
                    'user_name' => 'テスト太郎',
                ],
            ]);
    }

    /** @test */
    public function 存在しない_i_dでは404とエラー_jso_nが返る(): void
    {
        // Arrange（準備）
        $invalidId = 99999;

        // Act（実行）
        $response = $this->getJson("/api/v1/attendance-records/{$invalidId}");

        // Assert（検証）
        $response->assertStatus(404)
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }
}
