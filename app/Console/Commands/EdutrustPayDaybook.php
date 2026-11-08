<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\DaybookExporter;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use Carbon\Carbon;
use EdutrustPay\Contract\Daybook;
use Illuminate\Console\Command;

/**
 * Sends each branch's daybook to the console, separately from the monthly report
 * so that a large daybook failing can never hold the report back.
 *
 * Unchanged months are skipped, and a month that has changed is filed again at the
 * next sequence, where the console keeps both versions.
 */
class EdutrustPayDaybook extends Command
{
    protected $signature = 'edutrustpay:daybook
        {period? : Calendar month YYYY-MM. Defaults to last month.}
        {--months= : The last N complete months, oldest first}
        {--branch= : Only this branch id}
        {--dry-run : Build and count the lines without queueing anything}
        {--send : Attempt delivery immediately rather than waiting for the flush}';

    protected $description = 'Queue each branch\'s daybook for the EdutrustPay console. These lines name people.';

    public function handle(DaybookExporter $exporter, OutboxService $outbox): int
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
                    $latest = $outbox->latestDaybookFor((int) $branch->id, $period);
                    $document = $exporter->document((int) $branch->id, $period, $latest?->sequence ?? 1);

                    if ($latest?->status === EdutrustPayOutbox::DELIVERED) {
                        if (! Daybook::changed($latest->payload, $document)) {
                            $rows[] = [$branch->name, $period, count($document['lines']), $document['money_in'], $document['money_out'], 'unchanged'];

                            continue;
                        }

                        $document = $exporter->document((int) $branch->id, $period, $latest->sequence + 1);
                    }

                    if ($errors = Daybook::errors($document)) {
                        $this->error("  {$branch->name} $period is not valid: ".implode('; ', array_slice($errors, 0, 3)));
                        $failed++;

                        continue;
                    }
                } catch (\Throwable $e) {
                    $this->error("  {$branch->name} $period could not be built: ".$e->getMessage());
                    $failed++;

                    continue;
                }

                $queued = $this->option('dry-run') ? null : $outbox->enqueue((int) $branch->id, $document, 'daybook');

                if ($queued && $this->option('send')) {
                    $outbox->deliver($queued);
                    $queued = $outbox->latestDaybookFor((int) $branch->id, $period);
                }

                $rows[] = [$branch->name, $period, count($document['lines']), $document['money_in'], $document['money_out'], $queued?->status ?? 'not queued'];
            }
        }

        $this->table(['Branch', 'Period', 'Lines', 'Money in', 'Money out', 'Outbox'], $rows);

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

        $count = max(1, (int) ($this->option('months') ?: 1));
        // Ends at the last COMPLETE month, as the report does.
        $cursor = Carbon::now()->startOfMonth()->subMonths($count + 1);

        return collect(range(1, $count))
            ->map(fn () => $cursor->addMonth()->format('Y-m'))
            ->all();
    }
}
