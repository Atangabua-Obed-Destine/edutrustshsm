<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentPlan;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Changing what a pupil owes after the fee was billed.
 *
 * The reference system's policy, kept here as rules rather than as screens:
 *
 *  - an amount can always be raised, but lowered only while nothing has been
 *    paid on the fee — a reduction on a fee the family has started paying is a
 *    discount, which has a reason and shows on the books as one;
 *  - a fee with money on it cannot be removed; the money is moved first;
 *  - money paid on one fee can be moved to another fee of the same pupil, or
 *    back to the pupil's credit when it was paid on a fee that should never
 *    have been billed;
 *  - every change carries a reason, written onto the fee and the audit trail.
 *
 * Fees on an active payment plan are left alone: the plan's instalments are
 * built from the fee and would no longer add up.
 */
class FeeAdjustment
{
    public function __construct(private PaymentReversalService $reversals)
    {
    }

    /** @return list<string> why the amount cannot be changed to $amount */
    public function amountBlockers(StudentFee $fee, float $amount): array
    {
        $blockers = [];

        if ($amount < 1) {
            $blockers[] = __('The amount must be at least 1 franc. To take the fee off the pupil, remove it.');
        }
        if ($this->onActivePlan($fee)) {
            $blockers[] = __('This fee is on a payment plan. Cancel the plan first.');
        }
        if ($amount < (float) $fee->original_amount && $this->hasMoney($fee)) {
            $blockers[] = __(':paid has already been paid on this fee, so it can only be raised. To lower it, give a discount, or move the money to another fee first.', [
                'paid' => $this->francs((float) $fee->paid_amount),
            ]);
        }

        return $blockers;
    }

    public function correctAmount(StudentFee $fee, float $amount, string $reason): StudentFee
    {
        $amount = round($amount, 0);
        $this->refuse($this->amountBlockers($fee, $amount));

        return DB::transaction(function () use ($fee, $amount, $reason) {
            $old = (float) $fee->original_amount;
            $fee->original_amount = $amount;
            $this->note($fee, __('Amount corrected from :old to :new: :reason', [
                'old' => $this->francs($old), 'new' => $this->francs($amount), 'reason' => $reason,
            ]));
            $fee->recalculate()->save();

            return $fee;
        });
    }

    /** @return list<string> */
    public function dueDateBlockers(StudentFee $fee): array
    {
        $blockers = [];
        if ((float) $fee->balance <= 0) {
            $blockers[] = __('This fee is settled; its due date no longer matters.');
        }
        if ($this->onActivePlan($fee)) {
            $blockers[] = __('This fee is on a payment plan: change the instalment dates on the plan.');
        }

        return $blockers;
    }

    public function correctDueDate(StudentFee $fee, ?string $date, string $reason): StudentFee
    {
        $this->refuse($this->dueDateBlockers($fee));

        $old = $fee->due_date?->format('d/m/Y') ?? '—';
        $fee->due_date = $date;
        $this->note($fee, __('Due date changed from :old to :new: :reason', [
            'old' => $old, 'new' => $fee->due_date?->format('d/m/Y') ?? '—', 'reason' => $reason,
        ]));
        $fee->save();

        return $fee;
    }

    /** @return list<string> */
    public function removalBlockers(StudentFee $fee): array
    {
        $blockers = [];
        if ($this->hasMoney($fee)) {
            $blockers[] = __('Money has been received on this fee (:paid). Move it to another fee or to the pupil\'s credit first, then remove the fee.', [
                'paid' => $this->francs((float) $fee->paid_amount),
            ]);
        } elseif ($fee->allocations()->exists()) {
            // Reversed receipts still point at the fee and are kept as records.
            $blockers[] = __('Receipts have been issued against this fee, even if since reversed. They are kept as records, so the fee stays; correct its amount instead.');
        }
        if ($this->onActivePlan($fee)) {
            $blockers[] = __('This fee is on a payment plan. Cancel the plan first.');
        }

        return $blockers;
    }

    public function remove(StudentFee $fee, string $reason): void
    {
        $this->refuse($this->removalBlockers($fee));

        DB::transaction(function () use ($fee, $reason) {
            AuditLog::log('fee_removed', StudentFee::class, $fee->id, $fee->toArray() + ['reason' => $reason], null);
            PaymentPlan::where('student_fee_id', $fee->id)->delete();
            $fee->delete();
        });
    }

    /**
     * The fees money on $fee could be moved to: the pupil's other fees still
     * owing something, from any year.
     */
    public function targets(StudentFee $fee)
    {
        $studentId = $fee->enrollment?->student_id;

        return StudentFee::with(['feeCategory', 'enrollment.academicSession', 'enrollment.term'])
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $studentId))
            ->where('id', '!=', $fee->id)
            ->where('balance', '>', 0)
            ->get()
            ->reject(fn (StudentFee $f) => $this->onActivePlan($f))
            ->values();
    }

    /**
     * @param  StudentFee|null  $target  null puts the money on the pupil's credit
     * @return list<string>
     */
    public function moveBlockers(StudentFee $source, ?StudentFee $target, float $amount): array
    {
        $blockers = [];
        $paid = $this->verifiedOn($source)->sum('amount');

        if ($amount < 1) {
            $blockers[] = __('Enter the amount to move.');
        }
        if ($amount > (float) $paid + 0.005) {
            $blockers[] = __('Only :paid has been paid on this fee.', ['paid' => $this->francs((float) $paid)]);
        }
        if ($this->onActivePlan($source)) {
            $blockers[] = __('This fee is on a payment plan. Cancel the plan first.');
        }

        if ($target) {
            if ($target->id === $source->id) {
                $blockers[] = __('Choose a different fee to move the money to.');
            } elseif ($target->enrollment?->student_id !== $source->enrollment?->student_id) {
                $blockers[] = __('Money can only move between fees of the same pupil.');
            } elseif ($amount > (float) $target->balance + 0.005) {
                $blockers[] = __('The fee it goes to only owes :owed. Move less, or put the rest on the pupil\'s credit.', ['owed' => $this->francs((float) $target->balance)]);
            } elseif ($this->onActivePlan($target)) {
                $blockers[] = __('The fee it goes to is on a payment plan.');
            }
        } else {
            // Credit is money the school holds. Money that was itself drawn
            // from a credit, or paid in the old system, was never received
            // here, so it cannot become cash held for the family.
            $cash = $this->verifiedOn($source)
                ->filter(fn (PaymentAllocation $a) => ! in_array($a->payment->payment_method, Payment::NON_CASH_METHODS, true))
                ->sum('amount');
            if ($amount > (float) $cash + 0.005) {
                $blockers[] = __('Only :cash of this fee was paid in money received here; the rest came from credit or before the switch and cannot become credit.', ['cash' => $this->francs((float) $cash)]);
            }
        }

        return $blockers;
    }

    public function move(StudentFee $source, ?StudentFee $target, float $amount, string $reason): void
    {
        $amount = round($amount, 0);
        $this->refuse($this->moveBlockers($source, $target, $amount));

        DB::transaction(function () use ($source, $target, $amount, $reason) {
            $left = $amount;

            // The latest receipts first, so the oldest payments keep paying
            // what they were first taken for.
            $allocations = $this->verifiedOn($source)->sortByDesc('id');
            if (! $target) {
                $allocations = $allocations->reject(fn ($a) => in_array($a->payment->payment_method, Payment::NON_CASH_METHODS, true));
            }

            foreach ($allocations as $allocation) {
                if ($left <= 0) {
                    break;
                }
                $take = min($left, (float) $allocation->amount);

                // Each step goes through the allocation observer, so the
                // ledger moves the revenue from one category to the other
                // (or to the advances account) with the receipt's own date.
                if ($take >= (float) $allocation->amount - 0.005) {
                    $allocation->delete();
                } else {
                    $allocation->amount = round((float) $allocation->amount - $take, 2);
                    $allocation->save();
                }

                if ($target) {
                    PaymentAllocation::create([
                        'branch_id' => $allocation->branch_id,
                        'payment_id' => $allocation->payment_id,
                        'student_fee_id' => $target->id,
                        'amount' => $take,
                    ]);
                } else {
                    StudentCredit::create([
                        'student_enrollment_id' => $this->currentEnrollmentId($source),
                        'payment_id' => $allocation->payment_id,
                        'amount' => $take,
                        'used_amount' => 0,
                        'balance' => $take,
                        'source' => 'transfer',
                        'note' => __('Moved from :fee: :reason', ['fee' => $source->feeCategory?->name, 'reason' => $reason]),
                        'created_by' => auth()->id(),
                    ]);
                }

                $left -= $take;
            }

            $to = $target ? $target->feeCategory?->name : __('the pupil\'s credit');
            $this->note($source, __(':amount moved to :to: :reason', ['amount' => $this->francs($amount), 'to' => $to, 'reason' => $reason]));
            $this->reversals->recomputeFee($source);

            if ($target) {
                $this->note($target, __(':amount moved from :from: :reason', ['amount' => $this->francs($amount), 'from' => $source->feeCategory?->name, 'reason' => $reason]));
                $this->reversals->recomputeFee($target);
            }
        });
    }

    private function verifiedOn(StudentFee $fee)
    {
        return $fee->allocations()
            ->with('payment')
            ->whereHas('payment', fn ($q) => $q->where('verification_status', 'verified'))
            ->get();
    }

    private function hasMoney(StudentFee $fee): bool
    {
        return (float) $fee->paid_amount > 0.005 || $this->verifiedOn($fee)->isNotEmpty();
    }

    private function onActivePlan(StudentFee $fee): bool
    {
        return PaymentPlan::where('student_fee_id', $fee->id)->where('status', 'active')->exists();
    }

    /** Credit sits on the enrolment the pupil is on now, where it can be applied. */
    private function currentEnrollmentId(StudentFee $fee): int
    {
        return StudentEnrollment::where('student_id', $fee->enrollment->student_id)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->value('id') ?? $fee->student_enrollment_id;
    }

    private function note(StudentFee $fee, string $line): void
    {
        $stamp = now()->format('d/m/Y').' ('.(auth()->user()?->full_name ?? auth()->user()?->name ?? '—').')';
        $fee->notes = trim(($fee->notes ? $fee->notes."\n" : '').$stamp.' '.$line);
    }

    private function refuse(array $blockers): void
    {
        if ($blockers !== []) {
            throw new RuntimeException(implode(' ', $blockers));
        }
    }

    private function francs(float $amount): string
    {
        return number_format($amount, 0, '.', ' ').' FCFA';
    }
}
