<?php

namespace App\Console\Commands;

use App\Actions\Employees\GenerateMonthlySalary;
use Illuminate\Console\Command;

/**
 * Runs the monthly salary credit. Safe to run more than once for a month.
 */
class GenerateSalaries extends Command
{
    protected $signature = 'salary:generate {month? : Any date in the month, or YYYY-MM. Defaults to last month.}';

    protected $description = 'Credit one month of salary to every monthly-paid worker';

    public function handle(GenerateMonthlySalary $action): int
    {
        // Defaults to last month: the schedule fires on the 1st, by which
        // point the month that just ended is the one to pay.
        $month = $this->argument('month') ?? now()->subMonthNoOverflow()->format('Y-m');

        $result = $action->handle($month);

        $this->components->info(sprintf(
            '%s: credited %d, skipped %d, total %s',
            $month,
            $result['credited'],
            $result['skipped'],
            $result['total'],
        ));

        return self::SUCCESS;
    }
}
