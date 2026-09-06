<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 名前が未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $userData = [
            'name' => '',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'name' => 'お名前を入力してください',
        ]);
    }

    /** @test */
    public function メールアドレスが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $userData = [
            'name' => 'テストユーザー',
            'email' => '',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'email' => 'メールアドレスを入力してください',
        ]);
    }

    /** @test */
    public function パスワードが8文字未満の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'pass123', // 7文字
            'password_confirmation' => 'pass123',
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'password' => 'パスワードは8文字以上で入力してください',
        ]);
    }

    /** @test */
    public function パスワードが一致しない場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password', // 一致させない
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'password' => 'パスワードと一致しません',
        ]);
    }

    /** @test */
    public function パスワードが未入力の場合、バリデーションメッセージが表示される(): void
    {
        // Arrange（準備）
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => '',
            'password_confirmation' => '',
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        $response->assertSessionHasErrors([
            'password' => 'パスワードを入力してください',
        ]);
    }

    /** @test */
    public function フォームに内容が入力されていた場合、データが正常に保存される(): void
    {
        // Arrange（準備）
        $userData = [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        // Act（実行）
        $response = $this->post(route('register'), $userData);

        // Assert（検証）
        // データベースに指定の情報が保存されているか検証
        $this->assertDatabaseHas('users', [
            'name' => 'テストユーザー',
            'email' => 'test@example.com',
        ]);
    }
}
