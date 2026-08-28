<?php

namespace App\Observers;

use App\Models\PaymentAllocation;
use App\Services\TransactionAutoMapService;

/**
 * Posts fee revenue to the ledger one ALLOCATION at a time.
 *
 * A Payment can be split across several fee categories, so posting the payment
 * as a whole (the previous behaviour) could only ever use one catch-all rule —
 * the ledger could not tell tuition from a PTA levy or boarding, which makes
 * per-category revenue and any receivables ageing impossible.
 *
 * Each allocation carries exactly one fee category, so each maps cleanly:
 * DR Cash/Bank → CR that category's revenue account, falling back to the
 * catch-all rule when a category has none configured.
 *
 * Note this posts only what was ALLOCATED. If a payment exceeds everything the
 * student owes, the remainder is not revenue and is deliberately left unposted
 * until it can be recorded as a student credit.
 */
class PaymentAllocationObserver
{
    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function created(PaymentAllocation $allocation): void
    {
        $this->mapper->autoMap(
            'fee_payment',
            $allocation->id,
            $allocation->studentFee?->fee_category_id,
            $this->data($allocation)
        );
    }

    public function updated(PaymentAllocation $allocation): void
    {
        if ($allocation->wasChanged('amount')) {
            $this->mapper->remap(
                'fee_payment',
                $allocation->id,
                $allocation->studentFee?->fee_category_id,
                $this->data($allocation)
            );
        }
    }

    public function deleted(PaymentAllocation $allocation): void
    {
        $this->mapper->reverse('fee_payment', $allocation->id);
    }

    /** @return array{amount: mixed, date: string, description: string} */
    private function data(PaymentAllocation $allocation): array
    {
        $payment = $allocation->payment;
        $category = $allocation->studentFee?->feeCategory?->name;

        return [
            'amount' => $allocation->amount,
            'date' => optional($payment?->payment_date)->toDateString() ?? now()->toDateString(),
            'description' => trim(sprintf(
                '%s - %s',
                $category ? __('Fee payment').' ('.$category.')' : __('Fee payment'),
                $payment?->receipt_number ?? ''
            ), ' -'),
        ];
    }
}
