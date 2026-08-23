<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 一般ユーザー1
        User::create([
            'name' => 'テストユーザー1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // 一般ユーザー2
        User::create([
            'name' => 'テストユーザー2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // 管理者ユーザー
        Admin::create([
            'name' => '管理者ユーザー',
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}