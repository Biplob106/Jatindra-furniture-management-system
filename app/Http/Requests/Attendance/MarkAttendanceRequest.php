<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.mark') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Attendance is never marked ahead of time; the day has to have
            // happened before anyone can say who worked it.
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'rows.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'rows.*.in_time' => ['nullable', 'date_format:H:i'],
            'rows.*.out_time' => ['nullable', 'date_format:H:i'],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'rows.*.overtime_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'work_date.before_or_equal' => 'ভবিষ্যতের তারিখে হাজিরা দেওয়া যাবে না।',
            'rows.required' => 'কোনো কর্মী পাওয়া যায়নি।',
        ];
    }
}
