<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 現在の日時情報が_u_iと同じ形式で出力されている(): void
    {
        // Arrange（準備）
        // 1. テスト内の現在日時を固定
        $now = Carbon::create(2026, 9, 4, 9, 0, 0);
        $this->travelTo($now);

        // 2. 一般ユーザーの作成とログイン
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        // 3. UI表示用のフォーマット文字列を作成（※画面の表記に合わせて調整してください）
        $formattedDate = Carbon::now()->isoFormat('YYYY年MM月DD日(ddd)');

        // Act（実行）
        // 勤怠打刻画面（/attendance）を開く
        $response = $this->actingAs($user)->get(route('attendance.create'));

        // Assert（検証）
        $response->assertStatus(200);
        // 画面上に固定した現在日時の文字列が含まれているかを検証
        $response->assertSee($formattedDate);
    }
}
