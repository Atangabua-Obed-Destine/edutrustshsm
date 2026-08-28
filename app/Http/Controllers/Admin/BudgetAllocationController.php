<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BudgetAllocationController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'budget-allocation';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('budget-allocation.view', ['byBudget']),
        ];
    }

    public function store(Request $request, Budget $budget)
    {
        if ($budget->allocationsLocked()) {
            return back()->with('error', __('Allocations cannot be changed once the budget is active.'));
        }

        $validated = $this->validateAllocation($request);

        $available = $this->availableToAllocate($budget);
        if (bccomp((string) $validated['allocated_amount'], (string) $available, 2) > 0) {
            return back()->withInput()->with('error', __('Allocation exceeds available budget. Remaining to allocate: :amt', ['amt' => number_format($available, 2)]));
        }

        $allocation = BudgetAllocation::create($validated + [
            'budget_id' => $budget->id,
            'remaining_amount' => $validated['allocated_amount'],
            'created_by' => auth()->id(),
        ]);
        $budget->calculateAllocatedAmount();


        return back()->with('success', __('Allocation added successfully.'));
    }

    public function update(Request $request, BudgetAllocation $allocation)
    {
        $budget = $allocation->budget;
        if ($budget->allocationsLocked()) {
            return back()->with('error', __('Allocations cannot be changed once the budget is active.'));
        }

        $validated = $this->validateAllocation($request);

        $available = $this->availableToAllocate($budget, exceptAllocationId: $allocation->id);
        if (bccomp((string) $validated['allocated_amount'], (string) $available, 2) > 0) {
            return back()->withInput()->with('error', __('Allocation exceeds available budget. Remaining to allocate: :amt', ['amt' => number_format($available, 2)]));
        }

        $old = $allocation->toArray();
        $allocation->update($validated + [
            'remaining_amount' => bcsub((string) $validated['allocated_amount'], (string) $allocation->spent_amount, 2),
            'updated_by' => auth()->id(),
        ]);
        $budget->calculateAllocatedAmount();


        return back()->with('success', __('Allocation updated successfully.'));
    }

    public function destroy(BudgetAllocation $allocation)
    {
        $budget = $allocation->budget;
        if ($budget->allocationsLocked()) {
            return back()->with('error', __('Allocations cannot be changed once the budget is active.'));
        }

        $old = $allocation->toArray();
        $allocation->delete();
        $budget->calculateAllocatedAmount();


        return back()->with('success', __('Allocation removed.'));
    }

    /** JSON: allocations for a budget (feeds the dependent dropdown on the expense form). */
    public function byBudget(Budget $budget)
    {
        return response()->json(
            $budget->allocations()->with('expenseCategory')->get()->map(fn ($a) => [
                'id' => $a->id,
                'label' => $a->title . ' — ' . $a->expenseCategory?->title . ' (' . number_format($a->available_amount, 2) . ' left)',
            ])
        );
    }

    private function availableToAllocate(Budget $budget, ?int $exceptAllocationId = null): string
    {
        $otherAllocated = $budget->allocations()
            ->when($exceptAllocationId, fn ($q) => $q->where('id', '!=', $exceptAllocationId))
            ->sum('allocated_amount');

        return bcsub((string) $budget->total_amount, (string) $otherAllocated, 2);
    }

    private function validateAllocation(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:191'],
            'allocated_amount' => ['required', 'numeric', 'min:0.01'],
            'period' => ['required', 'in:yearly,q1,q2,q3,q4,semester1,semester2'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
