<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies credit a student is holding against fees they owe.
 *
 * Applying a credit records a Payment with method `student_credit`, which the
 * allocation observer posts as DR advances liability → CR revenue rather than
 * as a fresh cash receipt. The cash already arrived when the credit was
 * created; recording it again would double the school's takings.
 */
class StudentCreditService
{
    public function __construct(private PaymentRecorder $recorder)
    {
    }

    /**
     * Apply available credit to a student's outstanding fees.
     *
     * @param  int|null  $targetFeeId  settle this fee first, then spill over
     * @param  float|null  $amount     cap; defaults to everything available
     */
    public function apply(StudentEnrollment $enrollment, ?int $targetFeeId = null, ?float $amount = null): Payment
    {
        $available = StudentCredit::availableFor($enrollment->id);

        if ($available < 0.01) {
            throw new RuntimeException(__('This student has no credit available.'));
        }

        $outstanding = (float) StudentFee::where('student_enrollment_id', $enrollment->id)
            ->where('balance', '>', 0)
            ->sum('balance');

        if ($outstanding < 0.01) {
            throw new RuntimeException(__('This student has no outstanding fees to apply credit to.'));
        }

        // Never draw more than is available, more than was asked for, or more
        // than is actually owed — otherwise applying credit would create a new
        // credit from its own overflow.
        $toApply = round(min($available, $amount ?? $available, $outstanding), 2);

        if ($toApply < 0.01) {
            throw new RuntimeException(__('There is nothing to apply.'));
        }

        return DB::transaction(function () use ($enrollment, $targetFeeId, $toApply) {
            $payment = $this->recorder->record([
                'student_enrollment_id' => $enrollment->id,
                'target_fee_id' => $targetFeeId,
                'amount' => $toApply,
                'payment_method' => 'student_credit',
                'payment_date' => now()->toDateString(),
                'notes' => __('Applied from student credit'),
            ]);

            $this->drawDown($enrollment->id, $toApply);

            return $payment;
        });
    }

    /**
     * Draw an amount across a student's credits, oldest first.
     *
     * @return float the amount actually drawn
     */
    public function drawDown(int $enrollmentId, float $amount): float
    {
        $remaining = $amount;

        $credits = StudentCredit::where('student_enrollment_id', $enrollmentId)
            ->available()
            ->orderBy('id')
            ->get();

        foreach ($credits as $credit) {
            if ($remaining < 0.01) {
                break;
            }

            $remaining -= $credit->draw($remaining);
        }

        return round($amount - $remaining, 2);
    }
}
