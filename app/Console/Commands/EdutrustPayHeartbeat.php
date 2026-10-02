<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use Illuminate\Console\Command;

/**
 * Says "still here" for each branch when there is nothing to report.
 *
 * Without this, a quiet month and a dead school office look identical from the
 * console — and only one of them needs somebody to drive out and look. This is
 * what makes silence mean something.
 */
class EdutrustPayHeartbeat extends Command
{
    protected $signature = 'edutrustpay:heartbeat {--send : Deliver immediately rather than queueing}';

    protected $description = 'Tell EdutrustPay each branch is alive.';

    public function handle(OutboxService $outbox): int
    {
        $configured = app(SettingsResolver::class)->configuredBranchIds();

        if ($configured === []) {
            $this->comment('No branch has EdutrustPay reporting switched on; nothing to do.');

            return self::SUCCESS;
        }

        foreach (Branch::whereIn('id', $configured)->get() as $branch) {
            $pending = EdutrustPayOutbox::where('branch_id', $branch->id)
                ->where('status', EdutrustPayOutbox::PENDING)->count();

            $failed = EdutrustPayOutbox::where('branch_id', $branch->id)
                ->where('status', EdutrustPayOutbox::FAILED)->count();

            $item = $outbox->enqueue((int) $branch->id, [
                // Reporting the outbox state lets the body tell the difference
                // between a school that has stopped and one that is trying and
                // failing to deliver.
                'note' => $failed > 0
                    ? 'Alive, but reports are failing to deliver from this end.'
                    : 'Alive.',
                'pending_reports' => $pending,
                'failed_reports' => $failed,
                'client_version' => \EdutrustPay\Contract\ContractVersion::CURRENT,
                'period' => null,
                'sequence' => 1,
            ], 'heartbeat');

            $outcome = $this->option('send') ? $outbox->deliver($item) : 'queued';

            $this->line("{$branch->name}: heartbeat $outcome.");
        }

        return self::SUCCESS;
    }
}
