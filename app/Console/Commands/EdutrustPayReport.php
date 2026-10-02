<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use App\Services\EdutrustPay\PeriodReportBuilder;
use Carbon\Carbon;
use EdutrustPay\Contract\Capability;
use EdutrustPay\Contract\Money;
use EdutrustPay\Contract\Validator;
use Illuminate\Console\Command;

/**
 * Builds each branch's monthly report and queues it for delivery.
 *
 * One report per branch, because a branch is a separate school with its own
 * credentials on the platform. Reporting them together would combine separate
 * schools' money into a figure that describes neither.
 */
class EdutrustPayReport extends Command
{
    protected $signature = 'edutrustpay:report
        {period? : Calendar month YYYY-MM. Defaults to last month.}
        {--branch= : Only this branch id}
        {--months= : Build this many months back, oldest first}
        {--sequence=1 : Use 2+ to restate a month already filed}
        {--status= : Force provisional or final}
        {--dry-run : Build and show the figures without queueing}
        {--send : Attempt delivery immediately}';

    protected $description = 'Build the monthly EdutrustPay report for each branch and queue it.';

    public function handle(PeriodReportBuilder $builder, OutboxService $outbox, Validator $validator): int
    {
        if (! $this->option('dry-run') && app(SettingsResolver::class)->configured()->isEmpty()) {
            $this->error('No branch has EdutrustPay reporting switched on. Configure one under Settings → EdutrustPay Reporting.');

            return self::FAILURE;
        }

        $branches = $this->branches();

        if ($branches->isEmpty()) {
            $this->error('No branch has EdutrustPay credentials. Each branch is a separate institution — see config/edutrustpay.php.');

            return self::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($branches as $branch) {
            foreach ($this->periods() as $period) {
                try {
                    $payload = $builder->build(
                        (int) $branch->id,
                        $period,
                        (int) $this->option('sequence'),
                        $this->option('status') ?: null
                    );
                } catch (\Throwable $e) {
                    $this->error("  {$branch->name} $period — ".$e->getMessage());
                    $failed++;

                    continue;
                }

                // Validate before queueing: a malformed report found here is a
                // line on this screen, found at the far end it is a round trip
                // over a hotspot and a rejection nobody here sees.
                $result = $validator->validate($payload);

                if (! $result['valid']) {
                    $this->error("  {$branch->name} $period is not valid: ".implode('; ', $result['errors']));
                    $failed++;

                    continue;
                }

                $queued = $this->option('dry-run') ? null : $outbox->enqueue((int) $branch->id, $payload);

                if ($queued && $this->option('send')) {
                    $outbox->deliver($queued);
                    $queued->refresh();
                }

                $operational = $payload['operational'] ?? [];

                $rows[] = [
                    $branch->name,
                    $period,
                    $payload['status'],
                    $operational['enrolment'] ?? '—',
                    isset($operational['expected_fees']) ? $this->amount($operational['expected_fees']) : '—',
                    isset($operational['collected']) ? $this->amount($operational['collected']) : '—',
                    isset($operational['collection_rate']) ? round((float) $operational['collection_rate'] * 100).'%' : '—',
                    $queued?->status ?? 'not queued',
                ];
            }
        }

        $this->table(
            ['Branch', 'Period', 'Status', 'Students', 'Billed', 'Collected', 'Rate', 'Outbox'],
            $rows
        );

        $this->reportWhatIsMissing();

        if ($this->option('dry-run')) {
            $this->comment('Nothing was queued.');
        } elseif (! $this->option('send')) {
            $this->comment('Queued. Delivery happens on the next `edutrustpay:flush`.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Branch>
     */
    private function branches()
    {
        $configured = app(SettingsResolver::class)->configuredBranchIds();

        return Branch::query()
            ->whereIn('id', $configured)
            ->when($this->option('branch'), fn ($q) => $q->where('id', (int) $this->option('branch')))
            ->get();
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
        $cursor = Carbon::now()->startOfMonth()->subMonths($count);

        return collect(range(1, $count))->map(fn () => $cursor->addMonth()->format('Y-m'))->all();
    }

    private function amount(string $value): string
    {
        return number_format((float) Money::of($value)->value(), 0, '.', ' ');
    }

    /**
     * Say plainly what is not being reported and why.
     *
     * Somebody looking at the console will see "not yet reporting" against these
     * and should be able to find out why from this end without guessing.
     */
    private function reportWhatIsMissing(): void
    {
        $missing = array_diff(Capability::all(), (array) config('edutrustpay.capabilities', []));

        if ($missing === []) {
            return;
        }

        $this->newLine();
        $this->line('<comment>Not declared:</comment> '.implode(', ', $missing));
        $this->line('These show on the console as "not yet reporting" rather than as zero, which is the intent.');

        if (in_array('ledger', $missing, true)) {
            $this->line('  ledger, integrity — journal_entries is empty here. The chart of accounts is seeded');
            $this->line('    and the observers are wired, but nothing has been posted, so there is no ledger');
            $this->line('    to summarise. This is the largest gap and the one worth closing first.');
        }
    }
}
