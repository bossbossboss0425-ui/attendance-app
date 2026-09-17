<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 会員登録後、認証メールが送信される(): void
    {
        // Arrange（準備）
        Notification::fake();

        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        $user = User::where('email', 'test@example.com')->first();

        // ユーザーに認証メール（VerifyEmail通知）が送信されたことを検証
        Notification::assertSentTo(
            [$user],
            VerifyEmail::class
        );
    }

    /** @test */
    public function メール認証誘導画面で認証ボタンを押下するとメール認証処理が実行される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'email_verified_at' => null, // 未認証ユーザー
        ]);

        // 署名付きの認証URLを作成
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Act（実行）
        $response = $this->actingAs($user)->get($verificationUrl);

        // Assert（検証）
        // web.phpの定義通り、認証完了後に勤怠登録画面（/attendance）へリダイレクトされることを確認
        $response->assertRedirect('/attendance');
    }

    /** @test */
    public function メール認証サイトのメール認証を完了すると勤怠登録画面に遷移する(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        // Act（実行）
        $this->actingAs($user)->get($verificationUrl);

        // Assert（検証）
        // DB上で email_verified_at が更新（認証完了）されたことを確認
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // 認証済み状態で勤怠登録画面（attendance.create）にアクセスできることを確認 (200 OK)
        $response = $this->actingAs($user->fresh())->get(route('attendance.create'));
        $response->assertStatus(200);
    }
}
