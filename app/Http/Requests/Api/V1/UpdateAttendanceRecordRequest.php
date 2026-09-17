<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        // ルートパラメータから更新対象の AttendanceRecord モデルを取得
        $attendanceRecord = $this->route('attendanceRecord');

        return [
            'date' => [
                'sometimes',
                'required',
                'date',
                Rule::unique('attendance_records')->ignore($attendanceRecord?->id)->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i', 'after:clock_in'],
            'status' => ['nullable', 'string'],
        ];
    }
}
