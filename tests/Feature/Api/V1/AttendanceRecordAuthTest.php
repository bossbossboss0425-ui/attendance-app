<?php

namespace Tests\Feature\Api\V1;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 未認証時に書き込み系_ap_iで401が返る(): void
    {
        $user = User::factory()->create();
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => '退勤',
        ]);

        // 1. 未認証で POST を実行
        $postResponse = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-01',
            'clock_in' => '09:00',
        ]);
        $postResponse->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 2. 未認証で PUT を実行
        $putResponse = $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", [
            'date' => '2026-05-01',
            'clock_in' => '10:00',
        ]);
        $putResponse->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);

        // 3. 未認証で DELETE を実行
        $deleteResponse = $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}");
        $deleteResponse->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /** @test */
    public function 認証済みユーザーは自分の勤怠を更新・削除できる(): void
    {
        // 1. Sanctum::actingAs($user) で認証
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => '退勤',
        ]);

        // 2. 自分の勤怠に対して PUT を実行
        $putResponse = $this->putJson("/api/v1/attendance-records/{$attendanceRecord->id}", [
            'date' => '2026-05-01',
            'clock_in' => '10:00',
            'clock_out' => '19:00',
            'status' => '退勤',
        ]);
        $putResponse->assertStatus(200);

        // 3. 自分の勤怠に対して DELETE を実行
        $deleteResponse = $this->deleteJson("/api/v1/attendance-records/{$attendanceRecord->id}");
        $deleteResponse->assertStatus(204);

        // DBから削除されていることを検証
        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendanceRecord->id,
        ]);
    }

    /** @test */
    public function 他ユーザーの勤怠を更新・削除しようとすると403が返る(): void
    {
        // 1. 自分と他ユーザーを用意し、自分として認証
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($user);

        // 他ユーザーの勤怠を作成
        $otherRecord = AttendanceRecord::create([
            'user_id' => $otherUser->id,
            'date' => '2026-05-01',
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => '退勤',
        ]);

        // 2. 他ユーザーの勤怠に対して PUT を実行
        $putResponse = $this->putJson("/api/v1/attendance-records/{$otherRecord->id}", [
            'date' => '2026-05-01',
            'clock_in' => '10:00',
        ]);
        $putResponse->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);

        // 3. 他ユーザーの勤怠に対して DELETE を実行
        $deleteResponse = $this->deleteJson("/api/v1/attendance-records/{$otherRecord->id}");
        $deleteResponse->assertStatus(403)
            ->assertJson(['error' => 'この操作を実行する権限がありません。']);
    }
}
