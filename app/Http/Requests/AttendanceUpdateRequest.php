<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('new_break_in')) {
            $this->merge([
                'new_break_in' => array_map(fn ($v) => $v === '' ? null : $v, $this->new_break_in),
            ]);
        }
        if ($this->has('new_break_out')) {
            $this->merge([
                'new_break_out' => array_map(fn ($v) => $v === '' ? null : $v, $this->new_break_out),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i', 'before:new_clock_out'],
            'new_clock_out' => ['required', 'date_format:H:i', 'after:new_clock_in'],
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before:new_clock_out'],
            'new_break_out.*' => ['nullable', 'date_format:H:i', 'after:new_break_in.*', 'before_or_equal:new_clock_out'],
            'comment' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間を入力してください',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.before' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_clock_out.required' => '退勤時間を入力してください',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です',

            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_in.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.before' => '休憩時間が不適切な値です',

            'new_break_out.*.date_format' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'new_break_out.*.after' => '休憩時間が不適切な値です',

            'comment.required' => '備考を記入してください',
        ];
    }
}
