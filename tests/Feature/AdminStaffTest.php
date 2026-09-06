<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        // 一般ユーザーを作成
        $user1 = User::factory()->create([
            'name' => '一般太郎',
            'email' => 'taro@example.com',
            'admin_status' => 0,
        ]);

        $user2 = User::factory()->create([
            'name' => '一般花子',
            'email' => 'hanako@example.com',
            'admin_status' => 0,
        ]);

        // 別の管理者ユーザーを作成（一覧に含まれないことを確認するため）
        $otherAdmin = User::factory()->create([
            'name' => '管理者二郎',
            'email' => 'admin2@example.com',
            'admin_status' => 1,
        ]);

        // Act（実行）
        $response = $this->actingAs($admin)
            ->get(route('admin.staff.index'));

        // Assert（検証）
        $response->assertStatus(200);
        $response->assertSee($user1->name);
        $response->assertSee($user1->email);
        $response->assertSee($user2->name);
        $response->assertSee($user2->email);

        // 管理者ユーザーは一覧に表示されていないこと
        $response->assertDontSee($otherAdmin->name);
        $response->assertDontSee($otherAdmin->email);
    }

    /** @test */
    public function ユーザーの勤怠情報が正しく表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $targetDate = Carbon::now()->firstOfMonth();
        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $targetDate->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'status' => '退勤',
        ]);

        // Act（実行）
        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.staff', ['id' => $user->id]));

        // Assert（検証）
        $response->assertStatus(200);
        $response->assertSee($user->name);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    /** @test */
    public function 前月を押下した時に表示月の前月の情報が表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        // 今月と前月のデータを作成
        $currentMonth = Carbon::now()->firstOfMonth();
        $previousMonth = $currentMonth->copy()->subMonth();

        // 前月の勤怠データ
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $previousMonth->format('Y-m-d'),
            'clock_in' => '08:30:00',
            'clock_out' => '17:30:00',
            'status' => '退勤',
        ]);

        // Act（実行: dateパラメータに前月を指定してアクセス）
        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.staff', [
                'id' => $user->id,
                'date' => $previousMonth->format('Y-m'),
            ]));

        // Assert（検証）
        $response->assertStatus(200);
        $response->assertSee('08:30');
        $response->assertSee('17:30');
    }

    /** @test */
    public function 翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        // 今月と翌月のデータを作成
        $currentMonth = Carbon::now()->firstOfMonth();
        $nextMonth = $currentMonth->copy()->addMonth();

        // 翌月の勤怠データ
        AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $nextMonth->format('Y-m-d'),
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
            'status' => '退勤',
        ]);

        // Act（実行: dateパラメータに翌月を指定してアクセス）
        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.staff', [
                'id' => $user->id,
                'date' => $nextMonth->format('Y-m'),
            ]));

        // Assert（検証）
        $response->assertStatus(200);
        $response->assertSee('10:00');
        $response->assertSee('19:00');
    }

    /** @test */
    public function 詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        // Arrange（準備）
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => Carbon::now()->format('Y-m-d'),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'status' => '退勤',
        ]);

        // Act（実行: 勤怠詳細画面へアクセス）
        $response = $this->actingAs($admin)
            ->get(route('admin.attendance.detail', ['id' => $attendance->id]));

        // Assert（検証）
        $response->assertStatus(200);
    }
}
