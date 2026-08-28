<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class BudgetAllocation extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'budget_id', 'expense_category_id', 'department_id', 'title',
        'allocated_amount', 'spent_amount', 'remaining_amount', 'period',
        'description', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function expenseCategory()
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'budget_allocation_id');
    }

    /**
     * Recompute own spend from the expense rows, then cascade to the parent budget.
     */
    public function updateSpentAmount(): void
    {
        $this->spent_amount = $this->expenses()->where('approval_status', '!=', 'rejected')->sum('amount');
        $this->remaining_amount = bcsub((string) $this->allocated_amount, (string) $this->spent_amount, 2);
        $this->save();

        if ($this->budget) {
            $this->budget->updateSpentAmount();
        }
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if ((float) $this->allocated_amount <= 0) {
            return 0;
        }
        return round(((float) $this->spent_amount / (float) $this->allocated_amount) * 100, 1);
    }

    public function getAvailableAmountAttribute(): string
    {
        return bcsub((string) $this->allocated_amount, (string) $this->spent_amount, 2);
    }

    public function getStatusColorAttribute(): string
    {
        $u = $this->utilization_percentage;
        return $u > 100 ? 'red' : ($u > 90 ? 'amber' : ($u > 75 ? 'yellow' : 'green'));
    }
}
