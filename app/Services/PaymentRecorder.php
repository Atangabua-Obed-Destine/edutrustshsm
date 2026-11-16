<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentAllocation;
use App\Models\PaymentPlan;
use App\Models\SchoolSetting;
use App\Models\StudentCredit;
use App\Models\StudentFee;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for recording an official fee Payment: allocating it
 * against outstanding StudentFee rows, updating their balances, and advancing
 * any payment plan attached to the fees it touched.
 *
 * Every entry point goes through here — admin fee collection, the general
 * payment screen, and parent-portal receipt approval — so the three cannot
 * drift apart again. They previously each had their own copy, and only one of
 * them advanced payment plans, so a parent's approved receipt left the plan
 * untouched.
 */
class PaymentRecorder
{
    /**
     * Create a verified Payment and allocate it across outstanding fees.
     *
     * @param  array  $data  keys: student_enrollment_id, amount, payment_method,
     *                       payment_date, target_fee_id?, allocations?, payer_name?,
     *                       payer_phone?, bank_name?, transaction_ref?,
     *                       proof_document?, notes?, received_by?, branch_id?,
     *                       payment_account_id? (the cash box or bank credited),
     *                       accept_credit? (see assertCreditAccepted)
     *
     *                       `allocations` is an operator-chosen split:
     *                       [['fee_id' => int, 'amount' => float], ...]. Each is
     *                       capped at that fee's outstanding balance, and any
     *                       remainder still spills over FIFO rather than being
     *                       silently dropped.
     */
    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $this->assertCreditAccepted($data);

            $payment = Payment::create([
                // Stamped explicitly: BelongsToBranch skips stamping in
                // All-Branches mode, which would leave the row invisible to
                // every branch-scoped query afterwards.
                'branch_id'             => $data['branch_id'] ?? $this->currentBranchId(),
                'receipt_number'        => $this->nextReceiptNumber(),
                'verify_code'           => Payment::newVerifyCode(),
                'student_enrollment_id' => $data['student_enrollment_id'],
                'amount'                => $data['amount'],
                'payment_method'        => $data['payment_method'],
                'payment_date'          => $data['payment_date'],
                'payer_name'            => $data['payer_name'] ?? null,
                'payer_phone'           => $data['payer_phone'] ?? null,
                'bank_name'             => $data['bank_name'] ?? null,
                'transaction_ref'       => $data['transaction_ref'] ?? null,
                'proof_document'        => $data['proof_document'] ?? null,
                'verification_status'   => 'verified',
                'received_by'           => $data['received_by'] ?? auth()->id(),
                'notes'                 => $data['notes'] ?? null,
            ]);

            $allocated = $this->allocate(
                $payment,
                (float) $data['amount'],
                $data['target_fee_id'] ?? null,
                $data['allocations'] ?? []
            );

            $this->syncPaymentPlans($payment, $allocated);
            $this->captureOverpayment($payment, (float) $data['amount'], $allocated);
            $this->creditPaymentAccount($payment, $data['payment_account_id'] ?? null);

            return $payment->load('allocations');
        });
    }

    /**
     * The validation rule for "Paid into" on every screen that takes a fee.
     *
     * Required as soon as the school has an active cash box or bank account,
     * so the cash book matches the till without anyone linking receipts by
     * hand afterwards. A school that keeps no accounts can still take fees.
     *
     * @return array<int, mixed>
     */
    public static function accountRules(): array
    {
        return [
            PaymentAccount::active()->exists() ? 'required' : 'nullable',
            'integer',
            Rule::exists('payment_accounts', 'id')->where('status', true),
        ];
    }

    /**
     * Put the money into the cash box or bank account it was paid into.
     *
     * Fee receipts used to be recorded without an account, so they reached
     * the cash book only when someone linked each one on the "unlinked"
     * screen. A credit drawn from an existing balance is not new money and
     * moves no account.
     */
    protected function creditPaymentAccount(Payment $payment, $accountId): void
    {
        if (! $accountId || in_array($payment->payment_method, Payment::NON_CASH_METHODS, true)) {
            return;
        }

        $account = PaymentAccount::active()->whereKey($accountId)->lockForUpdate()->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'payment_account_id' => __('That account is closed or does not exist. Choose another.'),
            ]);
        }

        app(PaymentAccountService::class)->credit($account, $payment->amount, [
            'transaction_date' => $payment->payment_date,
            'title' => __('Fee receipt :r', ['r' => $payment->receipt_number]),
            'reference_type' => PaymentAccountTransaction::REF_FEE_PAYMENT,
            'reference_id' => $payment->id,
            'payment_method' => $payment->payment_method,
            'payment_reference' => $payment->transaction_ref,
        ]);

        $payment->update(['payment_account_id' => $account->id]);
    }

    /**
     * Next sequential receipt number.
     *
     * Deliberately queried WITHOUT the branch scope: `payments.receipt_number`
     * carries a global unique index, so taking the max within one branch would
     * make branch B's first receipt collide with branch A's. Numbers are
     * therefore globally sequential and interleaved across branches. Making
     * them per-branch contiguous requires a composite unique index — a schema
     * decision, not something to paper over here.
     */
    public function nextReceiptNumber(): string
    {
        $prefix = SchoolSetting::current()?->receipt_prefix ?: 'RCP';

        $last = Payment::withoutBranchScope()
            ->where('receipt_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->value('receipt_number');

        $next = 1;
        if ($last) {
            $parts = explode('-', $last);
            $next = (int) end($parts) + 1;
        }

        // Guard against concurrent cashiers landing on the same number.
        do {
            $number = sprintf('%s-%06d', $prefix, $next);
            $taken = Payment::withoutBranchScope()->where('receipt_number', $number)->exists();
            $next++;
        } while ($taken);

        return $number;
    }

    /**
     * Where an amount would go, fee by fee, without recording anything.
     *
     * The collection screen shows this before the bursar confirms, and
     * record() applies the very same plan, so what was shown is what is
     * saved. The order:
     *   1. an operator-chosen split, line by line, each capped at what the
     *      fee still owes;
     *   2. the fee the payment was taken against, if it still owes;
     *   3. the pupil's other fees, earlier years first (a debt does not lapse
     *      because the pupil moved up a class), then oldest first;
     *   4. whatever is left is credit, held for the pupil.
     *
     * @param  array  $explicit  [['fee_id' => int, 'amount' => float], ...]
     * @return array{lines: list<array{fee: StudentFee, amount: float, balance_before: float, balance_after: float}>, allocated: float, credit: float}
     */
    public function plan(int $enrollmentId, float $amount, ?int $targetFeeId = null, array $explicit = []): array
    {
        $fees = $this->pupilFees($enrollmentId)
            ->with(['feeCategory', 'enrollment.academicSession'])
            ->orderBy('academic_sessions.start_date')
            ->orderBy('student_fees.id')
            ->get()
            ->keyBy('id');

        $targetFeeId = $targetFeeId ? (int) $targetFeeId : null;
        $owing = $fees->map(fn (StudentFee $fee) => max(0.0, (float) $fee->balance))->all();
        $remaining = round($amount, 2);
        $applied = [];

        $take = function (int $feeId, float $wanted) use (&$owing, &$remaining, &$applied): void {
            $use = round(min($owing[$feeId] ?? 0.0, $wanted, $remaining), 2);
            if ($use <= 0) {
                return;
            }
            $applied[$feeId] = round(($applied[$feeId] ?? 0.0) + $use, 2);
            $owing[$feeId] -= $use;
            $remaining = round($remaining - $use, 2);
        };

        foreach ($explicit as $line) {
            $requested = (float) ($line['amount'] ?? 0);
            if ($requested > 0 && $fees->has((int) ($line['fee_id'] ?? 0))) {
                $take((int) $line['fee_id'], $requested);
            }
        }

        if ($targetFeeId && $fees->has($targetFeeId)) {
            $take($targetFeeId, $remaining);
        }

        foreach ($fees->keys() as $feeId) {
            if ($remaining <= 0) {
                break;
            }
            if ($feeId !== $targetFeeId) {
                $take($feeId, $remaining);
            }
        }

        $lines = [];
        foreach ($applied as $feeId => $used) {
            $fee = $fees[$feeId];
            $lines[] = [
                'fee' => $fee,
                'amount' => $used,
                'balance_before' => (float) $fee->balance,
                'balance_after' => round((float) $fee->balance - $used, 2),
            ];
        }

        return [
            'lines' => $lines,
            'allocated' => round(array_sum($applied), 2),
            'credit' => max(0.0, $remaining),
        ];
    }

    /**
     * Apply the plan for this payment: one allocation per fee, balances
     * updated.
     *
     * @return array<int, float> student_fee_id => amount actually applied
     */
    protected function allocate(
        Payment $payment,
        float $amount,
        ?int $targetFeeId = null,
        array $explicit = []
    ): array {
        $applied = [];

        foreach ($this->plan($payment->student_enrollment_id, $amount, $targetFeeId, $explicit)['lines'] as $line) {
            PaymentAllocation::create([
                'branch_id'      => $payment->branch_id,
                'payment_id'     => $payment->id,
                'student_fee_id' => $line['fee']->id,
                'amount'         => $line['amount'],
            ]);

            $fee = $line['fee'];
            $fee->paid_amount = (float) $fee->paid_amount + $line['amount'];
            $fee->recalculate()->save();

            $applied[$fee->id] = $line['amount'];
        }

        return $applied;
    }

    /**
     * Money beyond what the pupil owes becomes credit, and nobody should find
     * that out from the receipt. A screen that takes fees says the family
     * agreed by sending accept_credit; without it, the payment is refused
     * with the figure, so the bursar can hand the change back instead.
     *
     * Only checked when the caller passes the key: imports and the payment
     * reversal tests record without a person at the counter.
     */
    protected function assertCreditAccepted(array $data): void
    {
        if (! array_key_exists('accept_credit', $data) || $data['accept_credit']
            || in_array($data['payment_method'] ?? null, Payment::NON_CASH_METHODS, true)) {
            return;
        }

        $credit = $this->plan(
            (int) $data['student_enrollment_id'],
            (float) $data['amount'],
            $data['target_fee_id'] ?? null,
            $data['allocations'] ?? []
        )['credit'];

        if ($credit >= 0.01) {
            throw ValidationException::withMessages([
                'accept_credit' => __(':amount FCFA of this is more than the pupil owes. Keep it as the pupil\'s credit only if the family agrees: tick "Keep the extra as credit" and record it again. Otherwise take the lower amount and give the change back.', [
                    'amount' => number_format($credit, 0, ',', ' '),
                ]),
            ]);
        }
    }

    /**
     * Every fee the pupil owes, whichever year's enrolment it was billed on.
     *
     * A promoted pupil gets a new enrolment and the old one is closed, but
     * what was not paid on it is still owed: the school collects it at the
     * same counter, on the same receipt.
     */
    protected function pupilFees(int $enrollmentId)
    {
        $studentId = \App\Models\StudentEnrollment::whereKey($enrollmentId)->value('student_id');

        return StudentFee::query()
            ->select('student_fees.*')
            ->join('student_enrollments', 'student_enrollments.id', '=', 'student_fees.student_enrollment_id')
            ->leftJoin('academic_sessions', 'academic_sessions.id', '=', 'student_enrollments.academic_session_id')
            ->where('student_enrollments.student_id', $studentId);
    }

    /**
     * Advance the instalments of any active plan on the fees this payment hit.
     *
     * Each plan is advanced by the amount allocated to ITS fee, not by the whole
     * payment. Seeding this with the full payment amount (as the old collection
     * screen did) credited a plan for money that went to a different fee.
     *
     * @param  array<int, float>  $allocated  student_fee_id => amount applied
     */
    protected function syncPaymentPlans(Payment $payment, array $allocated): void
    {
        if ($allocated === []) {
            return;
        }

        $plans = PaymentPlan::whereIn('student_fee_id', array_keys($allocated))
            ->where('status', 'active')
            ->get();

        foreach ($plans as $plan) {
            $remaining = $allocated[$plan->student_fee_id] ?? 0.0;

            $instalments = $plan->installments()
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('installment_number')
                ->get();

            foreach ($instalments as $instalment) {
                if ($remaining <= 0) {
                    break;
                }

                $due = (float) $instalment->amount - (float) $instalment->paid_amount;
                $applied = min($due, $remaining);

                $instalment->paid_amount = (float) $instalment->paid_amount + $applied;
                $instalment->payment_id = $payment->id;

                if ($instalment->paid_amount >= (float) $instalment->amount - 0.01) {
                    $instalment->status = 'paid';
                    $instalment->paid_date = $payment->payment_date;
                    $instalment->paid_amount = $instalment->amount; // snap to exact
                } else {
                    $instalment->status = 'partial';
                }

                $instalment->save();
                $remaining -= $applied;
            }

            // A plan is complete only when instalments were actually PAID.
            // Counting "nothing left outstanding" also marked an all-cancelled
            // plan as completed.
            $total = $plan->installments()->where('status', '!=', 'cancelled')->count();
            $paid = $plan->installments()->where('status', 'paid')->count();

            if ($total > 0 && $paid === $total) {
                $plan->update(['status' => 'completed']);
            }
        }
    }

    /**
     * Whatever the payment could not be allocated to becomes a student credit.
     *
     * The remainder used to be dropped: the Payment recorded the full amount,
     * the allocations summed to less, and the difference existed nowhere. It is
     * not revenue — the school holds it on the student's behalf — so it is
     * carried as a credit and, in the ledger, as a liability.
     *
     * @param  array<int, float>  $allocated
     */
    protected function captureOverpayment(Payment $payment, float $amount, array $allocated): void
    {
        // Applying an existing credit must not create a new one from its own
        // rounding, and a credit is drawn down elsewhere.
        if (in_array($payment->payment_method, Payment::NON_CASH_METHODS, true)) {
            return;
        }

        $unallocated = round($amount - array_sum($allocated), 2);

        if ($unallocated < 0.01) {
            return;
        }

        StudentCredit::create([
            'student_enrollment_id' => $payment->student_enrollment_id,
            'payment_id' => $payment->id,
            'amount' => $unallocated,
            'used_amount' => 0,
            'balance' => $unallocated,
            'source' => 'overpayment',
            'note' => __('Over-payment on receipt :r', ['r' => $payment->receipt_number]),
            'created_by' => $payment->received_by,
        ]);
    }

    private function currentBranchId(): ?int
    {
        if (! BranchContext::isActive() || BranchContext::isAllBranches()) {
            return null;
        }

        return BranchContext::current() ?: null;
    }
}
