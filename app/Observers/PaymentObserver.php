<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\TransactionAutoMapService;

/**
 * Fee receipts. A Payment can be allocated across several fee categories, so we
 * post it against the catch-all `fee_payment` rule (category_id = null):
 * typically DR Cash/Bank (Class 5) → CR Student Fees Receivable / Revenue.
 * Per-category precision can be layered on later.
 */
class PaymentObserver
{
    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function created(Payment $payment): void
    {
        $this->mapper->autoMap('fee_payment', $payment->id, null, $this->data($payment));
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('amount')) {
            $this->mapper->remap('fee_payment', $payment->id, null, $this->data($payment));
        }
    }

    public function deleted(Payment $payment): void
    {
        $this->mapper->reverse('fee_payment', $payment->id);
    }

    private function data(Payment $payment): array
    {
        return [
            'amount' => $payment->amount,
            'date' => optional($payment->payment_date)->toDateString() ?? now()->toDateString(),
            'description' => 'Fee payment - ' . $payment->receipt_number,
        ];
    }
}
