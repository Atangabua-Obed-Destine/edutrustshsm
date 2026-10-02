<?php

namespace App\Services;

use App\Models\ParentPaymentSubmission;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentPlan;
use App\Models\StudentCredit;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Undoing a payment that should never have been recorded.
 *
 * Recording a payment does several things at once — it allocates the money to
 * fees, advances any payment plan on them, turns any excess into a student
 * credit, posts each allocation to the ledger, and (once linked) credits a
 * payment account. Undoing it by hand in any one place leaves the others
 * disagreeing, which is how a ledger stops balancing.
 *
 * So a reversal is the exact mirror of PaymentRecorder::record(), performed in
 * one transaction — the same approach as the reference system's
 * PaymentReversalService, adapted to allocations rather than a single fee row:
 *
 *   1. the payment is marked reversed, with the reason and who did it;
 *   2. each fee it touched is recomputed from the payments that remain;
 *   3. payment plans on those fees are rewound;
 *   4. an over-payment credit it created is cancelled, or credit it drew on
 *      is given back;
 *   5. every ledger posting is reversed by an opposing entry, never deleted;
 *   6. a linked payment account is debited back;
 *   7. a parent-portal submission it came from is marked reversed.
 *
 * Nothing is deleted anywhere. A reversal is itself a record.
 */
class PaymentReversalService
{
    public const STATUS_REVERSED = 'reversed';

    public function __construct(
        private TransactionAutoMapService $mapper,
        private PaymentAccountService $accounts,
    ) {
    }

    /**
     * Why this payment cannot be reversed — empty when it can.
     *
     * @return array<int, string>
     */
    public function blockers(Payment $payment): array
    {
        if ($payment->verification_status === self::STATUS_REVERSED) {
            return [__('This payment has already been reversed.')];
        }

        if ($payment->verification_status !== 'verified') {
            return [__('Only a verified payment can be reversed. This one is :status.', [
                'status' => __(ucfirst((string) $payment->verification_status)),
            ])];
        }

        $blockers = [];

        // An over-payment credit that has since paid other fees cannot simply
        // be cancelled: the fees it settled would be left paid with money that
        // no longer exists. That application has to be reversed first.
        $spent = (float) StudentCredit::where('payment_id', $payment->id)->sum('used_amount');
        $refunded = (float) StudentCredit::where('payment_id', $payment->id)->sum('refunded_amount');

        if ($spent > 0.005) {
            $blockers[] = __('The over-payment credit from this receipt has already paid :amount of other fees. Reverse those receipts first.', [
                'amount' => number_format($spent, 0, '.', ' '),
            ]);
        }

        // Money already handed back to the family cannot be un-received.
        if ($refunded > 0.005) {
            $blockers[] = __(':amount of the over-payment credit from this receipt has already been refunded to the family, so the receipt can no longer be reversed.', [
                'amount' => number_format($refunded, 0, '.', ' '),
            ]);
        }

        return $blockers;
    }

    /**
     * Reverse a payment everywhere it reached.
     *
     * @throws RuntimeException when the payment cannot be reversed
     */
    public function reverse(Payment $payment, string $reason): Payment
    {
        $reason = trim($reason);

        return DB::transaction(function () use ($payment, $reason) {
            // Re-read under a lock so a double-click cannot reverse it twice.
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($blockers = $this->blockers($payment)) {
                throw new RuntimeException(implode(' ', $blockers));
            }

            $payment->load('allocations');

            // 1. The payment itself. Kept, not deleted.
            $payment->update([
                'verification_status' => self::STATUS_REVERSED,
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            // 5 (per allocation). Each allocation was posted on its own, so each
            // gets its own opposing entry.
            $type = $payment->payment_method === 'student_credit' ? 'credit_applied' : 'fee_payment';

            foreach ($payment->allocations as $allocation) {
                $this->mapper->reverse($type, $allocation->id);
            }

            // 2 and 3. The fees and their plans.
            $byFee = $payment->allocations->groupBy('student_fee_id')
                ->map(fn ($rows) => (float) $rows->sum('amount'));

            foreach ($byFee as $feeId => $amount) {
                $fee = StudentFee::whereKey($feeId)->lockForUpdate()->first();

                if ($fee) {
                    $this->recomputeFee($fee);
                    $this->rewindPlan($fee, $payment, $amount);
                }
            }

            // 4. Credit, in whichever direction this payment touched it.
            $this->cancelOverpaymentCredit($payment, $reason);

            if ($payment->payment_method === 'student_credit') {
                $this->restoreCredit($payment);
            }

            // 6. The payment account, if the receipt was linked to one.
            $this->debitPaymentAccount($payment, $reason);

            // 7. The parent's submission, so the portal does not keep showing it
            //    as approved.
            ParentPaymentSubmission::where('payment_id', $payment->id)->get()
                ->each(fn (ParentPaymentSubmission $submission) => $submission->update([
                    'status' => self::STATUS_REVERSED,
                    'review_notes' => trim(($submission->review_notes ? $submission->review_notes."\n" : '')
                        .__('Reversed on :date: :reason', ['date' => now()->format('d/m/Y'), 'reason' => $reason])),
                ]));

            return $payment->fresh(['allocations', 'reversedBy']);
        });
    }

    /**
     * Set a fee's paid amount to what its remaining payments say it is.
     *
     * Recomputed from scratch on purpose. Subtracting the reversed amount looks
     * simpler but drifts the moment a fee has been touched by several receipts.
     */
    public function recomputeFee(StudentFee $fee): void
    {
        $paid = (float) $fee->allocations()
            ->whereHas('payment', fn ($q) => $q->where('verification_status', 'verified'))
            ->sum('amount');

        $fee->paid_amount = round($paid, 2);
        $fee->recalculate()->save();
    }

    /**
     * Rewind a plan's instalments by what this payment put into them.
     *
     * Instalments are filled strictly in order, so the state without this
     * payment is the same total — less its share — re-walked from the first
     * instalment. That holds however many receipts filled the plan.
     */
    private function rewindPlan(StudentFee $fee, Payment $payment, float $amount): void
    {
        $plan = PaymentPlan::where('student_fee_id', $fee->id)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        if (! $plan) {
            return;
        }

        $instalments = $plan->installments()
            ->where('status', '!=', 'cancelled')
            ->orderBy('installment_number')
            ->get();

        $remaining = max(0.0, round((float) $instalments->sum('paid_amount') - $amount, 2));

        foreach ($instalments as $instalment) {
            $share = min((float) $instalment->amount, $remaining);
            $remaining = round($remaining - $share, 2);

            $instalment->paid_amount = $share;

            if ($share >= (float) $instalment->amount - 0.01) {
                $instalment->status = 'paid';
            } else {
                $instalment->status = $share > 0 ? 'partial' : ($instalment->due_date?->isPast() ? 'overdue' : 'pending');
                $instalment->paid_date = null;
            }

            if ((int) $instalment->payment_id === (int) $payment->id) {
                $instalment->payment_id = null;
            }

            $instalment->save();
        }

        $allPaid = $instalments->isNotEmpty() && $instalments->every(fn ($i) => $i->status === 'paid');

        if ($plan->status === 'completed' && ! $allPaid) {
            $plan->update(['status' => 'active']);
        }
    }

    /** An over-payment credit that was never spent simply stops existing. */
    private function cancelOverpaymentCredit(Payment $payment, string $reason): void
    {
        foreach (StudentCredit::where('payment_id', $payment->id)->get() as $credit) {
            $this->mapper->reverse('student_credit', $credit->id);

            $credit->update([
                'balance' => 0,
                'note' => trim(($credit->note ? $credit->note.' — ' : '')
                    .__('Cancelled: receipt :r reversed (:reason)', ['r' => $payment->receipt_number, 'reason' => $reason])),
            ]);
        }
    }

    /**
     * Give back credit this payment drew on.
     *
     * Credit is drawn oldest-first, so it is returned newest-first — the
     * mirror of how it was taken.
     */
    private function restoreCredit(Payment $payment): void
    {
        $remaining = (float) $payment->amount;

        $credits = StudentCredit::where('student_enrollment_id', $payment->student_enrollment_id)
            ->where('used_amount', '>', 0)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        foreach ($credits as $credit) {
            if ($remaining < 0.01) {
                break;
            }

            $back = min((float) $credit->used_amount, $remaining);

            $credit->used_amount = round((float) $credit->used_amount - $back, 2);
            $credit->recomputeBalance()->save();

            $remaining = round($remaining - $back, 2);
        }
    }

    /**
     * Take the money back out of the account it was paid into.
     *
     * A new debit, not a deletion of the original credit: the account book has
     * to show the receipt arrived and was then reversed. Not guarded against an
     * overdraft — the cash has already been counted into this account, so
     * refusing would leave the balance overstated instead.
     */
    private function debitPaymentAccount(Payment $payment, string $reason): void
    {
        if (! $payment->payment_account_id) {
            return;
        }

        $account = PaymentAccount::whereKey($payment->payment_account_id)->lockForUpdate()->first();

        if (! $account) {
            return;
        }

        $this->accounts->debit($account, $payment->amount, [
            'transaction_date' => now()->toDateString(),
            'title' => __('Reversal of receipt :r', ['r' => $payment->receipt_number]),
            'description' => $reason,
            'reference_type' => PaymentAccountTransaction::REF_FEE_PAYMENT,
            'reference_id' => $payment->id,
            'payment_method' => $payment->payment_method,
        ], guard: false);
    }
}
