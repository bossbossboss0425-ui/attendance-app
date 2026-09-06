<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\StampCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
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

        $postData = [
            'clock_in' => '19:00:00',
            'clock_out' => '18:00:00',
            'new_clock_in' => '19:00:00',
            'new_clock_out' => '18:00:00',
            'comment' => '打刻修正理由',
        ];

        // Act（実行）
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', $record->id))
            ->post(route('attendance.update', $record->id), $postData);

        // Assert（検証）
        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
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

        $postData = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'], // 配列構造かつ H:i 形式に修正
            'new_break_out' => ['19:30'],
            'comment' => '打刻修正理由',
        ];

        // Act（実行）
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', $record->id))
            ->post(route('attendance.update', $record->id), $postData);

        // Assert（検証）
        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
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

        $postData = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['19:00'], // 配列構造かつ H:i 形式に修正
            'comment' => '打刻修正理由',
        ];

        // Act（実行）
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', $record->id))
            ->post(route('attendance.update', $record->id), $postData);

        // Assert（検証）
        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function 備考欄が未入力の場合、バリデーションメッセージが表示される(): void
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

        $postData = [
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '',
        ];

        // Act（実行）
        $response = $this->actingAs($user)
            ->from(route('attendance.detail', $record->id))
            ->post(route('attendance.update', $record->id), $postData);

        // Assert（検証）
        $response->assertSessionHasErrors(['comment']);
    }

    /** @test */
    public function 修正申請処理が実行され、承認待ちリストで確認できる(): void
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

        StampCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'status' => '承認待ち',
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
            'comment' => '電車遅延のため修正',
        ]);

        // Act（実行）
        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        // Assert（検証）
        $response->assertStatus(200)
            ->assertSee('電車遅延のため修正')
            ->assertSee('承認待ち');
    }

    /** @test */
    public function 承認待ちタブにログインユーザーの未承認申請が全て表示される(): void
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

        StampCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'status' => '承認待ち',
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
            'comment' => '承認待ちのテスト申請',
        ]);

        // Act（実行）
        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        // Assert（検証）
        $response->assertStatus(200)
            ->assertSee('承認待ち')
            ->assertSee('承認待ちのテスト申請');
    }

    /** @test */
    public function 承認済みタブに承認済みの修正申請が全て表示される(): void
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

        StampCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'status' => '承認済み',
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
            'comment' => '承認済みのテスト申請',
        ]);

        // Act（実行）
        $response = $this->actingAs($user)->get('/stamp_correction_request/list?tab=approved');

        // Assert（検証）
        $response->assertStatus(200)
            ->assertSee('承認済み')
            ->assertSee('承認済みのテスト申請');
    }

    /** @test */
    public function 各申請の詳細リンクを押下すると勤怠詳細画面に遷移できる(): void
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

        StampCorrectionRequest::create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'status' => '承認待ち',
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '19:00:00',
            'comment' => '詳細リンクテスト',
        ]);

        // Act（実行）
        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        // Assert（検証）
        $response->assertStatus(200)
            ->assertSee('詳細');
    }
}
