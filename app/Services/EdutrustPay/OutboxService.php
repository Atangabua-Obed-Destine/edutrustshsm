<?php

namespace App\Services\EdutrustPay;

use App\Models\EdutrustPayOutbox;
use EdutrustPay\Contract\Canonical;
use EdutrustPay\Contract\Signer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Queues signed pushes locally and delivers them when the network allows.
 *
 * Two jobs, deliberately separated:
 *   enqueue()  serialise, sign, store. Never touches the network.
 *   flush()    deliver what is waiting. Never rebuilds a payload.
 *
 * That separation is the whole point of an outbox here. A bursary server loses
 * power and has a phone hotspot for a few minutes a day; if reporting a month
 * required the network to be up at the moment the month closed, months would
 * simply go missing — and on the console a missing month is indistinguishable
 * from an institution that has stopped reporting.
 *
 * A RETRY SENDS THE ORIGINAL BYTES. The signature covers exactly those bytes,
 * so rebuilding on retry would produce a different document; worse, if the
 * ledger moved in between, it would be a silent restatement of a month nobody
 * meant to restate.
 */
class OutboxService
{
    /**
     * Store a signed payload for delivery.
     *
     * @param  array<string, mixed>  $payload
     */
    public function enqueue(int $branchId, array $payload, string $kind = 'report'): EdutrustPayOutbox
    {
        $json = Canonical::encode($payload);

        return EdutrustPayOutbox::updateOrCreate(
            [
                // Branch is part of the key: two campuses reporting the same
                // month are two separate schools' reports, not a duplicate.
                'branch_id' => $branchId,
                'kind' => $kind,
                'period' => $payload['period'] ?? null,
                'sequence' => $payload['sequence'] ?? 1,
            ],
            [
                'payload' => $json,
                'payload_hash' => hash('sha256', $json),
                'status' => EdutrustPayOutbox::PENDING,
                'attempts' => 0,
                'next_attempt_at' => null,
                'last_status_code' => null,
                'last_response' => null,
            ]
        );
    }

    /**
     * Attempt delivery of everything that is due.
     *
     * @return array{attempted: int, delivered: int, deferred: int, failed: int}
     */
    public function flush(?int $limit = null): array
    {
        $result = ['attempted' => 0, 'delivered' => 0, 'deferred' => 0, 'failed' => 0];

        $due = EdutrustPayOutbox::due()->when($limit, fn ($q) => $q->limit($limit))->get();

        foreach ($due as $item) {
            $result['attempted']++;

            $outcome = $this->deliver($item);
            $result[$outcome]++;

            // One dead network means the rest will fail too. Stop rather than
            // burning every item's attempt count on the same outage.
            if ($outcome === 'deferred' && $item->last_status_code === null) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return 'delivered'|'deferred'|'failed'
     */
    public function deliver(EdutrustPayOutbox $item): string
    {
        $settings = app(SettingsResolver::class)->forBranch((int) $item->branch_id);

        if ($settings === null) {
            $item->forceFill([
                'status' => EdutrustPayOutbox::FAILED,
                'last_response' => 'No EdutrustPay credentials configured for branch '.$item->branch_id.'.',
            ])->save();

            return 'failed';
        }

        $endpoint = rtrim($settings['endpoint'], '/');
        $path = $item->kind === 'heartbeat' ? '/api/v1/heartbeat' : '/api/v1/reports';

        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature = Signer::signRaw($item->payload, $timestamp, $settings['secret']);

        $item->increment('attempts');

        try {
            $response = Http::withHeaders([
                'X-Edutrust-Key-Id' => $settings['key_id'],
                'X-Edutrust-Timestamp' => $timestamp,
                'X-Edutrust-Signature' => $signature,
                'Accept' => 'application/json',
            ])
                ->withBody($item->payload, 'application/json')
                ->timeout((int) config('edutrustpay.timeout', 30))
                ->post($endpoint.$path);
        } catch (\Throwable $e) {
            // No network at all. Not a failure of the report — try again later.
            return $this->defer($item, null, $e->getMessage());
        }

        $status = $response->status();
        $body = (string) $response->body();

        /*
         * 202 accepted, 200 already had it. Both mean the far end is holding
         * this month, which is all that matters — a duplicate is the expected
         * outcome of a retry that succeeded the first time and lost the reply.
         */
        if (in_array($status, [200, 202], true)) {
            $item->forceFill([
                'status' => EdutrustPayOutbox::DELIVERED,
                'delivered_at' => now(),
                'last_status_code' => $status,
                'last_response' => mb_substr($body, 0, 2000),
            ])->save();

            return 'delivered';
        }

        /*
         * 4xx means this payload will never be accepted, however many times it
         * is sent: a bad signature, an unsupported contract version, a sequence
         * conflict. Retrying is pointless and would bury the real problem under
         * attempt counts, so it stops and stays visible.
         *
         * 409 in particular is worth reading rather than retrying: it means this
         * month was already filed with DIFFERENT figures at the same sequence,
         * which is a restatement that has to be sent deliberately.
         */
        if ($status >= 400 && $status < 500) {
            $item->forceFill([
                'status' => EdutrustPayOutbox::FAILED,
                'last_status_code' => $status,
                'last_response' => mb_substr($body, 0, 2000),
            ])->save();

            Log::warning('EdutrustPay rejected a push and it will not be retried.', [
                'outbox_id' => $item->id,
                'period' => $item->period,
                'status' => $status,
                'response' => mb_substr($body, 0, 500),
            ]);

            return 'failed';
        }

        // 5xx or anything else: their problem, probably temporary.
        return $this->defer($item, $status, $body);
    }

    /**
     * Schedule another attempt, or give up once the backoff is exhausted.
     *
     * @return 'deferred'|'failed'
     */
    private function defer(EdutrustPayOutbox $item, ?int $status, string $message): string
    {
        $max = (int) config('edutrustpay.max_attempts', 12);

        if ($item->attempts >= $max) {
            $item->forceFill([
                'status' => EdutrustPayOutbox::FAILED,
                'last_status_code' => $status,
                'last_response' => mb_substr($message, 0, 2000),
            ])->save();

            Log::error('EdutrustPay push gave up after repeated failures.', [
                'outbox_id' => $item->id,
                'period' => $item->period,
                'attempts' => $item->attempts,
            ]);

            return 'failed';
        }

        $schedule = (array) config('edutrustpay.backoff_minutes', [1, 5, 15, 60, 240, 720]);
        $minutes = $schedule[min($item->attempts - 1, count($schedule) - 1)] ?? end($schedule);

        $item->forceFill([
            'next_attempt_at' => now()->addMinutes($minutes),
            'last_status_code' => $status,
            'last_response' => mb_substr($message, 0, 2000),
        ])->save();

        return 'deferred';
    }
}
