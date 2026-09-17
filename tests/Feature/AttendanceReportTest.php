<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function ゲストはレポートページにアクセスできない(): void
    {
        // 1. 未認証で GET /attendance/report を実行
        $response = $this->get('/attendance/report');

        // 期待挙動：/login にリダイレクトされる
        $response->assertRedirect('/login');
    }

    /** @test */
    public function 認証ユーザーの統計情報が正しく計算される(): void
    {
        // 1. メール認証済みのユーザーを作成してログイン
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 集計対象（前月）の日付を取得
        $lastMonthDate = Carbon::now()->subMonth()->format('Y-m-15');

        // 前月の勤怠データ（実働9時間：残業1時間）
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $lastMonthDate,
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'status' => '退勤',
        ]);

        // 2. GET /attendance/report を実行
        $response = $this->get('/attendance/report');

        // 期待挙動：HTTP 200 返却、正しく計算された各変数がビューに渡される
        $response->assertStatus(200);
        $response->assertViewHasAll([
            'summary',
            'monthlyTrend',
            'anomalies',
        ]);

        // 統計計算結果の検証
        $summary = $response->viewData('summary');
        $this->assertEquals(540, $summary['total_work_minutes']); // 9時間 = 540分
        $this->assertEquals(60, $summary['total_overtime_minutes']);  // 8時間超過分 = 60分
    }

    /** @test */
    public function 勤怠記録がないユーザーで安全に処理される(): void
    {
        // 1. 勤怠データのないメール認証済みユーザーで認証
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        // 2. GET /attendance/report を実行
        $response = $this->get('/attendance/report');

        // 期待挙動：エラーが発生せず HTTP 200 が返り、0 / 空データがセットされる
        $response->assertStatus(200);
        $response->assertViewHasAll([
            'summary',
            'monthlyTrend',
            'anomalies',
        ]);

        $summary = $response->viewData('summary');
        $this->assertEquals(0, $summary['total_work_minutes']);
        $this->assertEquals(0, $summary['total_overtime_minutes']);
        $this->assertEquals(0, $summary['avg_work_minutes']);
    }
}
