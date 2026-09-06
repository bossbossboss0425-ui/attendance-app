<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function メールアドレスが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => 1, // 管理者ユーザーとして作成
        ]);

        $loginData = [
            'email' => '',
            'password' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('admin.login'), $loginData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /** @test */
    public function パスワードが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => 1,
        ]);

        $loginData = [
            'email' => $admin->email,
            'password' => '',
        ];

        // Act（実行）
        $response = $this->post(route('admin.login'), $loginData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /** @test */
    public function 登録内容と一致しない場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'admin_status' => 1,
        ]);

        $loginData = [
            'email' => 'wrong-admin@example.com', // 誤ったメールアドレス
            'password' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('admin.login'), $loginData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
