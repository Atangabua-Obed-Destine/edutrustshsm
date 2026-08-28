<?php

namespace App\Console\Commands;

use App\Services\RecurringEntryService;
use Illuminate\Console\Command;

class GenerateRecurringEntries extends Command
{
    protected $signature = 'accounting:recurring-entries {--as-of= : Treat this date as today (YYYY-MM-DD)}';

    protected $description = 'Generate journal entries from recurring templates that are due';

    public function handle(RecurringEntryService $service): int
    {
        $result = $service->runDue($this->option('as-of'));

        $this->components->info(sprintf(
            '%d entries generated, %d already existed.',
            $result['generated'],
            $result['skipped']
        ));

        foreach ($result['errors'] as $error) {
            $this->components->warn($error);
        }

        // Errors are reported but do not fail the run: one bad template must
        // not stop every other one from generating.
        return self::SUCCESS;
    }
}
