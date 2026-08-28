<?php

namespace App\Services;

use App\Models\FeeFine;
use App\Models\StudentFee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Accrues late-payment penalties onto outstanding fees.
 *
 * Deliberately NOT applied at payment time. The reference system recalculates
 * the fine inside the pay dialog, so the amount owed changes every time a
 * cashier opens the screen and nothing records why. Here accrual is an explicit,
 * scheduled step: it runs once a day, the resulting figure is stored on the fee,
 * and the audit trail shows when it moved.
 *
 * Accrual is a RECOMPUTE, not an increment. Running it twice in one day changes
 * nothing, and deactivating a band removes its effect on the next run.
 */
class FeeFineService
{
    /**
     * Accrue penalties across every outstanding fee.
     *
     * @return array{examined: int, charged: int, total: float}
     */
    public function accrueAll(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf) : Carbon::today();
        $bands = $this->activeBands();

        $examined = 0;
        $charged = 0;
        $total = 0.0;

        StudentFee::query()
            ->with('feeCategory:id')
            ->where('balance', '>', 0)
            ->whereNotNull('due_date')
            ->chunkById(200, function (Collection $fees) use ($bands, $asOf, &$examined, &$charged, &$total) {
                foreach ($fees as $fee) {
                    $examined++;
                    $fine = $this->accrue($fee, $bands, $asOf);

                    if ($fine > 0) {
                        $charged++;
                        $total += $fine;
                    }
                }
            });

        return ['examined' => $examined, 'charged' => $charged, 'total' => round($total, 2)];
    }

    /**
     * Set the penalty on one fee and persist it.
     *
     * @param  Collection<int, FeeFine>|null  $bands  preloaded bands, to avoid a query per fee
     * @return float the fine now standing on the fee
     */
    public function accrue(StudentFee $fee, ?Collection $bands = null, ?Carbon $asOf = null): float
    {
        $asOf ??= Carbon::today();
        $bands ??= $this->activeBands();

        $fine = $this->fineFor($fee, $bands, $asOf);

        // Nothing to write: avoids touching updated_at and flooding the audit
        // trail with no-op rows on every daily run.
        if (abs($fine - (float) $fee->fine_amount) < 0.01) {
            return $fine;
        }

        $fee->fine_amount = $fine;
        $fee->recalculate()->save();

        return $fine;
    }

    /** The penalty a fee should carry today, from scratch. */
    public function fineFor(StudentFee $fee, Collection $bands, Carbon $asOf): float
    {
        if (! $fee->due_date || (float) $fee->balance <= 0) {
            return 0.0;
        }

        $daysOverdue = $fee->due_date->lt($asOf) ? (int) $fee->due_date->diffInDays($asOf) : 0;

        if ($daysOverdue <= 0) {
            return 0.0;
        }

        $band = $bands
            ->filter(fn (FeeFine $f) => $this->appliesToCategory($f, $fee->fee_category_id))
            ->filter(fn (FeeFine $f) => $f->covers($daysOverdue))
            // Bands can overlap; the one that starts latest is the most specific
            // to how overdue this fee actually is.
            ->sortByDesc('start_day')
            ->first();

        if (! $band) {
            return 0.0;
        }

        // Percentage bands charge against what was originally owed, not the
        // running balance — otherwise part-paying would shrink the penalty.
        $base = (float) $fee->original_amount - (float) $fee->discount_amount - (float) $fee->waiver_amount;

        return $band->penaltyOn(max(0, $base));
    }

    /** @return Collection<int, FeeFine> */
    public function activeBands(): Collection
    {
        return FeeFine::active()->with('feeCategories:id')->get();
    }

    /** A band with no categories attached applies to every fee. */
    private function appliesToCategory(FeeFine $band, ?int $categoryId): bool
    {
        if ($band->feeCategories->isEmpty()) {
            return true;
        }

        return $band->feeCategories->contains('id', $categoryId);
    }
}
