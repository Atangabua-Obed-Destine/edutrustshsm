<?php

namespace App\Console\Commands;

use App\Models\EdutrustPayOutbox;
use App\Services\EdutrustPay\OutboxService;
use App\Services\EdutrustPay\SettingsResolver;
use Illuminate\Console\Command;

/**
 * Delivers whatever is waiting in the outbox.
 *
 * Runs often, does nothing when there is nothing to send, and is safe to run
 * while the network is down. This is the command that turns "a hotspot for
 * thirty seconds a day" into reliable monthly reporting.
 */
class EdutrustPayFlush extends Command
{
    protected $signature = 'edutrustpay:flush
        {--limit= : Deliver at most this many items}
        {--retry-failed : Put permanently-failed items back in the queue after the cause has been fixed}';

    protected $description = 'Deliver queued EdutrustPay reports and heartbeats.';

    public function handle(OutboxService $outbox): int
    {
        if (app(SettingsResolver::class)->configured()->isEmpty()) {
            $this->comment('No branch has EdutrustPay reporting switched on; nothing to do.');

            return self::SUCCESS;
        }

        if ($this->option('retry-failed')) {
            $count = EdutrustPayOutbox::where('status', EdutrustPayOutbox::FAILED)
                ->update(['status' => EdutrustPayOutbox::PENDING, 'attempts' => 0, 'next_attempt_at' => null]);

            $this->info("Requeued $count failed item(s).");
        }

        $result = $outbox->flush($this->option('limit') ? (int) $this->option('limit') : null);

        if ($result['attempted'] === 0) {
            $this->line('Nothing due.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d attempted: %d delivered, %d deferred, %d failed.',
            $result['attempted'], $result['delivered'], $result['deferred'], $result['failed']
        ));

        $stuck = EdutrustPayOutbox::where('status', EdutrustPayOutbox::FAILED)->count();

        if ($stuck > 0) {
            // A permanently-failed report is a month the body will never see,
            // and it looks from their end exactly like this institution going
            // quiet. It must not sit here unnoticed.
            $this->warn("$stuck item(s) have failed permanently and need attention:");

            foreach (EdutrustPayOutbox::where('status', EdutrustPayOutbox::FAILED)->limit(5)->get() as $item) {
                $this->line(sprintf(
                    '  branch %d · %s seq %d — HTTP %s — %s',
                    $item->branch_id,
                    $item->period ?? $item->kind,
                    $item->sequence,
                    $item->last_status_code ?? 'no response',
                    mb_substr((string) $item->last_response, 0, 140)
                ));
            }
        }

        return self::SUCCESS;
    }
}
