<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
{
    /**
     * 配列データへの変換
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'date' => $this->date,
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'status' => $this->status,
            'total_time' => $this->formatted_total_time,
            'total_break_time' => $this->formatted_total_break_time,
            'comment' => $this->comment,

            // 休憩記録（show メソッドで load された場合に出力）
            'breaks' => $this->whenLoaded('breakRecords', function () {
                return $this->breakRecords->map(function ($break) {
                    return [
                        'id' => $break->id,
                        'break_in' => $break->break_in,
                        'break_out' => $break->break_out,
                    ];
                });
            }),

            // 打刻修正申請（show メソッドで load された場合に出力）
            'applications' => $this->whenLoaded('stampCorrectionRequests', function () {
                return $this->stampCorrectionRequests->map(function ($app) {
                    return [
                        'id' => $app->id,
                        'status' => $app->status ?? null,
                        'comment' => $app->comment ?? null,
                        'created_at' => $app->created_at?->toIso8601String(),
                    ];
                });
            }),
        ];
    }
}
