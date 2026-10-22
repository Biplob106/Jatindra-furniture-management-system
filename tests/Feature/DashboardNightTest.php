<?php

use App\Actions\Attendance\MarkDailyAttendance;
use App\Enums\WageType;
use App\Models\Employee;
use App\Queries\DashboardToday;
use Carbon\CarbonImmutable;

it('reports today night count and attendance earnings', function () {
    $today = CarbonImmutable::today()->toDateString();

    $nightWorker = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);
    $dayWorker = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 500]);

    app(MarkDailyAttendance::class)->handle($today, [
        ['employee_id' => $nightWorker->id, 'status' => 'full_day_night'], // 700 wage + 350 night
        ['employee_id' => $dayWorker->id, 'status' => 'present'],          // 500 wage
    ]);

    $attendance = app(DashboardToday::class)->attendance($today);

    expect($attendance['marked'])->toBe(2)
        ->and($attendance['night'])->toBe(1)
        ->and($attendance['earned'])->toBe('1550.00'); // 700 + 350 + 500
});
