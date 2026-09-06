<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function メールアドレスが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'email' => '',
            'password' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('login'), $loginData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /** @test */
    public function パスワードが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'email' => $user->email,
            'password' => '',
        ];

        // Act（実行）
        $response = $this->post(route('login'), $loginData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /** @test */
    public function 登録内容と一致しない場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $user = User::factory()->create([
            'email' => 'registered@example.com',
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'email' => 'wrong@example.com', // 登録と異なるメールアドレス
            'password' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('login'), $loginData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'email' => 'ログイン情報が登録されていません',
        ]);
    }
}
