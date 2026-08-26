<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolSetting;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for recording an official fee Payment + allocating it
 * (FIFO) against outstanding StudentFee rows and updating their balances.
 *
 * Used by both the admin PaymentController and the parent-portal receipt
 * approval flow so there is no drift between the two entry points.
 */
class PaymentRecorder
{
    /**
     * Create a verified Payment and allocate it across outstanding fees.
     *
     * @param  array  $data  keys: student_enrollment_id, amount, payment_method,
     *                       payment_date, payer_name?, payer_phone?, bank_name?,
     *                       transaction_ref?, proof_document?, notes?, received_by?,
     *                       branch_id?
     */
    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::create([
                'branch_id'             => $data['branch_id'] ?? null,
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

            $this->allocate($payment, (float) $data['amount'], $data['target_fee_id'] ?? null);

            return $payment;
        });
    }

    /** Branch-scoped, prefix-aware sequential receipt number. */
    public function nextReceiptNumber(): string
    {
        $settings = SchoolSetting::current();
        $prefix = $settings->receipt_prefix ?? 'RCP';

        $lastPayment = Payment::where('receipt_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->first();

        $nextNum = 1;
        if ($lastPayment) {
            $parts = explode('-', $lastPayment->receipt_number);
            $nextNum = (int) end($parts) + 1;
        }

        return sprintf('%s-%06d', $prefix, $nextNum);
    }

    /**
     * Allocate an amount across outstanding fees. If $targetFeeId is given, that
     * fee is paid first (mirroring the admin collect-fees behaviour), then any
     * remainder spills over to the other outstanding fees oldest-first.
     */
    protected function allocate(Payment $payment, float $amount, ?int $targetFeeId = null): void
    {
        $remaining = $amount;

        // 1. Targeted fee first.
        if ($targetFeeId) {
            $target = StudentFee::where('student_enrollment_id', $payment->student_enrollment_id)
                ->where('id', $targetFeeId)
                ->where('balance', '>', 0)
                ->first();

            if ($target) {
                $remaining = $this->applyToFee($payment, $target, $remaining);
            }
        }

        // 2. Remaining spills over to other outstanding fees oldest-first.
        if ($remaining > 0) {
            $outstandingFees = StudentFee::where('student_enrollment_id', $payment->student_enrollment_id)
                ->when($targetFeeId, fn ($q) => $q->where('id', '!=', $targetFeeId))
                ->where('balance', '>', 0)
                ->orderBy('id')
                ->get();

            foreach ($outstandingFees as $fee) {
                if ($remaining <= 0) {
                    break;
                }
                $remaining = $this->applyToFee($payment, $fee, $remaining);
            }
        }
    }

    /** Apply as much of $remaining as fits to one fee; returns the leftover. */
    protected function applyToFee(Payment $payment, StudentFee $fee, float $remaining): float
    {
        $allocAmount = min((float) $fee->balance, $remaining);
        if ($allocAmount <= 0) {
            return $remaining;
        }

        PaymentAllocation::create([
            'branch_id'      => $payment->branch_id,
            'payment_id'     => $payment->id,
            'student_fee_id' => $fee->id,
            'amount'         => $allocAmount,
        ]);

        $fee->paid_amount += $allocAmount;
        $fee->balance = $fee->net_amount - $fee->paid_amount;
        $fee->status = $fee->balance <= 0 ? 'paid' : ($fee->paid_amount > 0 ? 'partial' : 'unpaid');
        $fee->save();

        return $remaining - $allocAmount;
    }
}
