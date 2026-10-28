<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;

/**
 * Puts an expense on the budget line it belongs to, by itself.
 *
 * A budget only ever saw expenses someone tagged by hand — and the expense
 * form never offered the tag, so every budget read zero spent, forever. The
 * reference system's answer is that actuals are derived, not typed: the
 * category and the date already say which allocation an expense draws on.
 *
 * The match is the active budget whose period covers the expense's date and
 * which has an allocation for its category. An expense that matches nothing
 * is simply untagged, and the monthly summary reports it as spending outside
 * the budget rather than losing it.
 */
class BudgetMatcher
{
    /** The allocation an expense of this category, on this date, draws on. */
    public function allocationFor(int $categoryId, string $date): ?BudgetAllocation
    {
        return BudgetAllocation::query()
            ->where('expense_category_id', $categoryId)
            ->where('is_active', true)
            ->whereHas('budget', fn ($q) => $q
                ->where('status', 'active')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date))
            ->orderBy('id')
            ->first();
    }

    /**
     * Fill in the budget on an expense that has none.
     *
     * @param  array<string, mixed>  $data  the validated expense attributes, changed in place
     * @return bool whether a match was written
     */
    public function fill(array &$data): bool
    {
        if (! empty($data['budget_allocation_id']) || empty($data['category_id']) || empty($data['date'])) {
            return false;
        }

        $allocation = $this->allocationFor((int) $data['category_id'], (string) $data['date']);

        if (! $allocation) {
            return false;
        }

        $data['budget_id'] = $allocation->budget_id;
        $data['budget_allocation_id'] = $allocation->id;

        return true;
    }

    /**
     * Take in the expenses already recorded for a budget's period.
     *
     * A budget is usually activated after the year has started, so the first
     * weeks' spending predates it. Those expenses belong to it just the same;
     * this claims any that are not yet on another budget and brings the
     * figures up to date.
     *
     * @return int how many expenses were taken in
     */
    public function adopt(Budget $budget): int
    {
        if ($budget->status !== 'active') {
            return 0;
        }

        $taken = 0;

        foreach ($budget->allocations()->where('is_active', true)->get() as $allocation) {
            $taken += Expense::query()
                ->whereNull('budget_id')
                ->where('category_id', $allocation->expense_category_id)
                ->whereDate('date', '>=', $budget->start_date)
                ->whereDate('date', '<=', $budget->end_date)
                ->update(['budget_id' => $budget->id, 'budget_allocation_id' => $allocation->id]);

            $allocation->updateSpentAmount();
        }

        $budget->updateSpentAmount();

        return $taken;
    }
}
