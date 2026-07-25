<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Salaries for the month that just ended. The command is safe to run twice,
// so a missed night or a manual re-run costs nothing.
Schedule::command('salary:generate')
    ->monthlyOn(1, '01:00')
    ->timezone('Asia/Dhaka');

// Shop rent for the month now starting. Also safe to run twice.
Schedule::command('rent:generate')
    ->monthlyOn(1, '01:30')
    ->timezone('Asia/Dhaka');
