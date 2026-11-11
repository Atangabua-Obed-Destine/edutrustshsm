<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\BudgetSheetExporter;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use App\Services\EdutrustPay\ReportingMonths;
use Carbon\Carbon;
use EdutrustPay\Contract\BudgetSheet;
use Illuminate\Console\Command;

/**
 * Sends each branch's budget sheet as it stood at the end of each month. A month
 * no budget covers is skipped and said so, rather than sent as an empty sheet.
 */
class EdutrustPayBudgetSheet extends Command
{
    protected $signature = 'edutrustpay:budget-sheet
        {period? : Calendar month YYYY-MM. Defaults to last month.}
        {--months= : The last N complete months, oldest first}
        {--branch= : Only this branch id}
        {--to-date : Also the month in progress, up to today, refiled as it changes}
        {--dry-run : Build the sheets without queueing anything}
        {--send : Attempt delivery immediately rather than waiting for the flush}';

    protected $description = 'Queue each branch\'s budget sheet at the end of each month for the EdutrustPay console.';

    public function handle(BudgetSheetExporter $exporter, OutboxService $outbox): int
    {
        $branches = Branch::query()
            ->whereIn('id', app(SettingsResolver::class)->configuredBranchIds())
            ->when($this->option('branch'), fn ($q) => $q->where('id', (int) $this->option('branch')))
            ->get();

        if ($branches->isEmpty()) {
            $this->error('No branch has EdutrustPay credentials configured.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($branches as $branch) {
            foreach ($this->periods() as $period) {
                try {
                    $latest = $outbox->latestBudgetSheetFor((int) $branch->id, $period);
                    $document = $exporter->document((int) $branch->id, $period, $latest?->sequence ?? 1);

                    if ($document === null) {
                        $rows[] = [$branch->name, $period, '—', '—', 'no budget covers this month'];

                        continue;
                    }

                    if ($latest?->status === EdutrustPayOutbox::DELIVERED) {
                        if (! BudgetSheet::changed($latest->payload, $document)) {
                            $rows[] = [$branch->name, $period, $document['budget']['title'], count($document['lines']), 'unchanged'];

                            continue;
                        }

                        $document = $exporter->document((int) $branch->id, $period, $latest->sequence + 1);
                    }

                    if ($errors = BudgetSheet::errors($document)) {
                        $this->error("  {$branch->name} $period is not valid: ".implode('; ', array_slice($errors, 0, 3)));
                        $failed++;

                        continue;
                    }
                } catch (\Throwable $e) {
                    $this->error("  {$branch->name} $period could not be built: ".$e->getMessage());
                    $failed++;

                    continue;
                }

                $queued = $this->option('dry-run') ? null : $outbox->enqueue((int) $branch->id, $document, 'budget_sheet');

                if ($queued && $this->option('send')) {
                    $outbox->deliver($queued);
                    $queued = $outbox->latestBudgetSheetFor((int) $branch->id, $period);
                }

                $rows[] = [$branch->name, $period, $document['budget']['title'], count($document['lines']), $queued?->status ?? 'not queued'];
            }
        }

        $this->table(['Branch', 'Period', 'Budget', 'Lines', 'Outbox'], $rows);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function periods(): array
    {
        if ($period = $this->argument('period')) {
            return [$period];
        }

        return ReportingMonths::recent((int) ($this->option('months') ?: 1), (bool) $this->option('to-date'));
    }
}
