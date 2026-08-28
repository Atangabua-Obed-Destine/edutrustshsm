<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentPlan;
use App\Models\SchoolSetting;
use App\Models\StudentFee;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;

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
     *                       proof_document?, notes?, received_by?, branch_id?
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
            $payment = Payment::create([
                // Stamped explicitly: BelongsToBranch skips stamping in
                // All-Branches mode, which would leave the row invisible to
                // every branch-scoped query afterwards.
                'branch_id'             => $data['branch_id'] ?? $this->currentBranchId(),
                'receipt_number'        => $this->nextReceiptNumber(),
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

            return $payment->load('allocations');
        });
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
     * Allocate an amount across outstanding fees. A targeted fee is paid first
     * (the collect-fees behaviour); any remainder spills over to the student's
     * other outstanding fees, oldest first.
     *
     * @return array<int, float> student_fee_id => amount actually applied
     */
    protected function allocate(
        Payment $payment,
        float $amount,
        ?int $targetFeeId = null,
        array $explicit = []
    ): array {
        $remaining = $amount;
        $applied = [];

        // Operator-chosen split, honoured in the order given.
        foreach ($explicit as $line) {
            if ($remaining <= 0) {
                break;
            }

            $requested = (float) ($line['amount'] ?? 0);
            if ($requested <= 0) {
                continue;
            }

            $fee = StudentFee::where('student_enrollment_id', $payment->student_enrollment_id)
                ->where('id', $line['fee_id'] ?? 0)
                ->first();

            if (! $fee) {
                continue;
            }

            // Cap this line at what was requested, but decrement the payment's
            // real remainder by what was actually used — passing the capped
            // figure back as the remainder would discard the rest of the payment.
            [, $used] = $this->applyToFee($payment, $fee, min($requested, $remaining));

            if ($used > 0) {
                $applied[$fee->id] = ($applied[$fee->id] ?? 0) + $used;
                $remaining -= $used;
            }
        }

        if ($targetFeeId) {
            $target = StudentFee::where('student_enrollment_id', $payment->student_enrollment_id)
                ->where('id', $targetFeeId)
                ->where('balance', '>', 0)
                ->first();

            if ($target) {
                [$remaining, $used] = $this->applyToFee($payment, $target, $remaining);
                if ($used > 0) {
                    $applied[$target->id] = $used;
                }
            }
        }

        if ($remaining > 0) {
            $outstanding = StudentFee::where('student_enrollment_id', $payment->student_enrollment_id)
                ->when($targetFeeId, fn ($q) => $q->where('id', '!=', $targetFeeId))
                ->where('balance', '>', 0)
                ->orderBy('id')
                ->get();

            foreach ($outstanding as $fee) {
                if ($remaining <= 0) {
                    break;
                }

                [$remaining, $used] = $this->applyToFee($payment, $fee, $remaining);
                if ($used > 0) {
                    $applied[$fee->id] = $used;
                }
            }
        }

        return $applied;
    }

    /**
     * Apply as much of $remaining as fits to one fee.
     *
     * @return array{0: float, 1: float} [leftover, amount applied]
     */
    protected function applyToFee(Payment $payment, StudentFee $fee, float $remaining): array
    {
        $allocAmount = min((float) $fee->balance, $remaining);

        if ($allocAmount <= 0) {
            return [$remaining, 0.0];
        }

        PaymentAllocation::create([
            'branch_id'      => $payment->branch_id,
            'payment_id'     => $payment->id,
            'student_fee_id' => $fee->id,
            'amount'         => $allocAmount,
        ]);

        $fee->paid_amount = (float) $fee->paid_amount + $allocAmount;
        $fee->balance = (float) $fee->net_amount - (float) $fee->paid_amount;
        $fee->status = $fee->balance <= 0
            ? 'paid'
            : ($fee->paid_amount > 0 ? 'partial' : 'unpaid');
        $fee->save();

        return [$remaining - $allocAmount, $allocAmount];
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

    private function currentBranchId(): ?int
    {
        if (! BranchContext::isActive() || BranchContext::isAllBranches()) {
            return null;
        }

        return BranchContext::current() ?: null;
    }
}
