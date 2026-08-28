<?php

namespace App\Observers;

use App\Models\StudentCredit;
use App\Services\TransactionAutoMapService;

/**
 * An over-payment is cash the school holds but has not earned.
 *
 * Posts DR Cash/Bank → CR Student Advances (OHADA 419), so the money appears on
 * the balance sheet as a liability rather than being invisible. It becomes
 * revenue only when the credit is applied to a fee.
 */
class StudentCreditObserver
{
    public function __construct(private TransactionAutoMapService $mapper)
    {
    }

    public function created(StudentCredit $credit): void
    {
        $this->mapper->autoMap('student_credit', $credit->id, null, [
            'amount' => $credit->amount,
            'date' => optional($credit->payment?->payment_date)->toDateString() ?? now()->toDateString(),
            'description' => __('Student credit').' - '.($credit->payment?->receipt_number ?? $credit->id),
        ]);
    }

    public function deleted(StudentCredit $credit): void
    {
        $this->mapper->reverse('student_credit', $credit->id);
    }
}
