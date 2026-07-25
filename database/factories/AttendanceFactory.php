<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shop_id' => null,
            'work_date' => '2026-07-01',
            'status' => AttendanceStatus::Present,
            'in_time' => '08:00:00',
            'out_time' => '18:00:00',
            'overtime_hours' => 0,
            'overtime_rate' => 0,
            'note' => null,
            'marked_by' => null,
        ];
    }
}
