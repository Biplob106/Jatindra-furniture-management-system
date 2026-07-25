<?php

namespace App\Console\Commands;

use App\Actions\Expenses\GenerateRecurringExpenses;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Posts monthly shop rent. Safe to run more than once for a month.
 */
class GenerateRent extends Command
{
    protected $signature = 'rent:generate {month? : Any date in the month, or YYYY-MM. Defaults to this month.}';

    protected $description = 'Post the monthly rent expense for every active shop';

    public function handle(GenerateRecurringExpenses $action): int
    {
        $month = $this->argument('month') ?? now()->format('Y-m');

        try {
            $result = $action->handle($month);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%s: posted %d, skipped %d, total %s',
            $month,
            $result['posted'],
            $result['skipped'],
            $result['total'],
        ));

        return self::SUCCESS;
    }
}
