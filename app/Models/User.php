<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    // 当日の勤怠ステータスを取得するアクセサ ($user->attendance_status)
    public function getAttendanceStatusAttribute(): string
    {
        $today = Carbon::today()->toDateString();

        // 今日の勤怠レコードを取得
        $todayRecord = $this->attendanceRecords()
            ->where('date', $today)
            ->first();

        // 当日のレコードがなければ「勤務外」
        if (! $todayRecord) {
            return '勤務外';
        }

        // DBのstatus（勤務外 / 出勤中 / 休憩中 / 退勤済）を返す
        return $todayRecord->status;
    }
}
