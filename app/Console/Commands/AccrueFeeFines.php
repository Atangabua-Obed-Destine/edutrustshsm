<?php

namespace App\Console\Commands;

use App\Services\FeeFineService;
use Illuminate\Console\Command;

class AccrueFeeFines extends Command
{
    protected $signature = 'fees:accrue-fines
                            {--as-of= : Accrue as at this date instead of today (YYYY-MM-DD)}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Apply late-payment penalties to overdue student fees';

    public function handle(FeeFineService $fines): int
    {
        $asOf = $this->option('as-of');

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run — nothing will be written.');

            return self::SUCCESS;
        }

        $result = $fines->accrueAll($asOf);

        $this->components->info(sprintf(
            '%d overdue fees examined, %d carrying a penalty, %s total.',
            $result['examined'],
            $result['charged'],
            number_format($result['total'], 2)
        ));

        return self::SUCCESS;
    }
}
