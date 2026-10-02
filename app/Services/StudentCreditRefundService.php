<?php

namespace App\Services;

use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\StudentCredit;
use App\Models\StudentCreditRefund;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Paying back money held on a student's account.
 *
 * Follows the reference system's lifecycle — request, then approve or reject,
 * then process — with one difference that matters in the books: processing a
 * refund here actually moves the money. The reference only wrote a transaction
 * row against the student; this clears the advances liability against cash in
 * the ledger and, when an account is named, takes the money out of it.
 */
class StudentCreditRefundService
{
    public function __construct(
        private TransactionAutoMapService $mapper,
        private PaymentAccountService $accounts,
    ) {
    }

    /**
     * How much of a credit can still be asked for.
     *
     * Open requests reserve their amount, so two people cannot each request the
     * whole balance and both be approved.
     */
    public function refundable(StudentCredit $credit): float
    {
        $open = (float) $credit->refunds()
            ->whereIn('status', [StudentCreditRefund::STATUS_REQUESTED, StudentCreditRefund::STATUS_APPROVED])
            ->sum('amount');

        return max(0.0, round((float) $credit->balance - $open, 2));
    }

    public function request(StudentCredit $credit, float $amount, string $reason): StudentCreditRefund
    {
        return DB::transaction(function () use ($credit, $amount, $reason) {
            $credit = StudentCredit::whereKey($credit->getKey())->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);
            $refundable = $this->refundable($credit);

            if ($amount < 0.01) {
                throw new RuntimeException(__('Enter an amount to refund.'));
            }

            if ($amount > $refundable + 0.005) {
                throw new RuntimeException(__('Only :amount of this credit can still be refunded.', [
                    'amount' => number_format($refundable, 0, '.', ' '),
                ]));
            }

            return $credit->refunds()->create([
                'amount' => $amount,
                'reason' => trim($reason),
                'status' => StudentCreditRefund::STATUS_REQUESTED,
                'requested_by' => auth()->id(),
                'requested_at' => now(),
            ]);
        });
    }

    public function approve(StudentCreditRefund $refund): StudentCreditRefund
    {
        return DB::transaction(function () use ($refund) {
            $refund = $this->lock($refund);

            if ($refund->status !== StudentCreditRefund::STATUS_REQUESTED) {
                throw new RuntimeException(__('Only a requested refund can be approved. This one is :status.', [
                    'status' => __(ucfirst($refund->status)),
                ]));
            }

            $refund->update([
                'status' => StudentCreditRefund::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $refund;
        });
    }

    public function reject(StudentCreditRefund $refund, string $reason): StudentCreditRefund
    {
        return DB::transaction(function () use ($refund, $reason) {
            $refund = $this->lock($refund);

            if (! $refund->isOpen()) {
                throw new RuntimeException(__('This refund is already :status.', ['status' => __(ucfirst($refund->status))]));
            }

            // Rejecting releases the reserved amount; the credit itself was never
            // touched, so there is nothing else to put back.
            $refund->update([
                'status' => StudentCreditRefund::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
            ]);

            return $refund;
        });
    }

    /**
     * Pay an approved refund out.
     *
     * @return array{refund: StudentCreditRefund, posted: bool}
     *
     * @throws RuntimeException when the refund cannot be paid
     */
    public function process(
        StudentCreditRefund $refund,
        string $method,
        ?string $reference = null,
        ?int $paymentAccountId = null,
        ?string $note = null,
    ): array {
        return DB::transaction(function () use ($refund, $method, $reference, $paymentAccountId, $note) {
            $refund = $this->lock($refund);

            if ($refund->status !== StudentCreditRefund::STATUS_APPROVED) {
                throw new RuntimeException(__('A refund has to be approved before it is paid. This one is :status.', [
                    'status' => __(ucfirst($refund->status)),
                ]));
            }

            if (! array_key_exists($method, StudentCreditRefund::METHODS)) {
                throw new RuntimeException(__('Unknown refund method.'));
            }

            $credit = StudentCredit::whereKey($refund->student_credit_id)->lockForUpdate()->firstOrFail();
            $amount = (float) $refund->amount;

            // Approval does not freeze the credit: it may have been applied to a
            // fee in the meantime. Paying out more than is left would refund
            // money the school has already earned.
            if ((float) $credit->balance < $amount - 0.005) {
                throw new RuntimeException(__('Only :amount of this credit is left — it has been applied to fees since the refund was approved. Reject this refund and request a new one.', [
                    'amount' => number_format((float) $credit->balance, 0, '.', ' '),
                ]));
            }

            // The money leaves the account first, so a shortfall stops the whole
            // refund rather than leaving the credit reduced with no cash out.
            if ($paymentAccountId) {
                $account = PaymentAccount::whereKey($paymentAccountId)->lockForUpdate()->firstOrFail();

                $this->accounts->debit($account, $amount, [
                    'transaction_date' => now()->toDateString(),
                    'title' => __('Refund of student credit — :name', [
                        'name' => $credit->enrollment?->student?->full_name ?? '#'.$credit->id,
                    ]),
                    'description' => $refund->reason,
                    'reference_type' => PaymentAccountTransaction::REF_CREDIT_REFUND,
                    'reference_id' => $refund->id,
                    'payment_method' => $method,
                    'payment_reference' => $reference,
                ]);
            }

            $credit->refunded_amount = round((float) $credit->refunded_amount + $amount, 2);
            $credit->recomputeBalance()->save();

            $refund->update([
                'status' => StudentCreditRefund::STATUS_PROCESSED,
                'processed_by' => auth()->id(),
                'processed_at' => now(),
                'method' => $method,
                'reference' => $reference,
                'payment_account_id' => $paymentAccountId,
                'note' => $note,
            ]);

            // DR Student Advances (419) → CR Cash/Bank. Returns false when no
            // rule is configured, so the caller can say so instead of the
            // refund silently never reaching the ledger.
            $posted = $this->mapper->autoMap('credit_refund', $refund->id, null, [
                'amount' => $amount,
                'date' => now()->toDateString(),
                'description' => __('Student credit refund').' - '.($credit->enrollment?->student?->full_name ?? '#'.$credit->id),
            ]);

            return ['refund' => $refund->fresh(), 'posted' => $posted];
        });
    }

    private function lock(StudentCreditRefund $refund): StudentCreditRefund
    {
        return StudentCreditRefund::whereKey($refund->getKey())->lockForUpdate()->firstOrFail();
    }
}
