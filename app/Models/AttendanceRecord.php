<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'status',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breakRecords(): HasMany
    {
        return $this->hasMany(BreakRecord::class);
    }

    public function stampCorrectionRequests(): HasMany
    {
        return $this->hasMany(StampCorrectionRequest::class);
    }

    // 休憩時間の合計（秒）を取得

    public function getTotalBreakSecondsAttribute(): int
    {
        return $this->breakRecords
            ->filter(fn (BreakRecord $break): bool => (bool) ($break->break_in && $break->break_out))
            ->sum(function (BreakRecord $break): int {
                $in = Carbon::parse($break->break_in);
                $out = Carbon::parse($break->break_out);

                return $out->diffInSeconds($in);
            });
    }

    // 休憩合計時間を H:i 形式で取得

    public function getFormattedTotalBreakTimeAttribute(): ?string
    {
        $seconds = $this->total_break_seconds;
        if ($seconds === 0 && $this->breakRecords->isEmpty()) {
            return null;
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%01d:%02d', $hours, $minutes);
    }

    // 実働合計時間（出退勤の差分 - 休憩時間）を H:i 形式で取得

    public function getFormattedTotalTimeAttribute(): ?string
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return null;
        }

        $in = Carbon::parse($this->clock_in);
        $out = Carbon::parse($this->clock_out);
        $workSeconds = $out->diffInSeconds($in) - $this->total_break_seconds;

        if ($workSeconds < 0) {
            $workSeconds = 0;
        }

        $hours = floor($workSeconds / 3600);
        $minutes = floor(($workSeconds % 3600) / 60);

        return sprintf('%01d:%02d', $hours, $minutes);
    }
}
