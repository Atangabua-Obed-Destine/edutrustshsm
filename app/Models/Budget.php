<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'title', 'budget_code', 'type', 'department_id', 'fiscal_year',
        'start_date', 'end_date', 'total_amount', 'allocated_amount',
        'spent_amount', 'remaining_amount', 'status', 'description', 'note',
        'created_by', 'updated_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_amount' => 'decimal:2',
            'allocated_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Budget $budget) {
            if (! $budget->budget_code) {
                $budget->budget_code = static::generateCode();
            }
            // Seed remaining = total at creation (no spend yet).
            $budget->remaining_amount = $budget->total_amount;
        });
    }

    public static function generateCode(): string
    {
        $year = now()->year;
        $prefix = "BDG-{$year}-";
        $last = static::where('budget_code', 'like', $prefix . '%')->orderByDesc('budget_code')->first();
        $next = $last ? ((int) substr($last->budget_code, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // ── Relationships ──
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function allocations()
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'budget_id');
    }

    public function revisions()
    {
        return $this->hasMany(BudgetRevision::class)->orderByDesc('revision_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ── Single source of truth ──
    public function updateSpentAmount(): void
    {
        $this->spent_amount = $this->expenses()->where('approval_status', '!=', 'rejected')->sum('amount');
        $this->remaining_amount = bcsub((string) $this->total_amount, (string) $this->spent_amount, 2);
        $this->save();
    }

    public function calculateAllocatedAmount(): void
    {
        $this->allocated_amount = $this->allocations()->sum('allocated_amount');
        $this->save();
    }

    public function isOverBudget(): bool
    {
        return bccomp((string) $this->spent_amount, (string) $this->total_amount, 2) > 0;
    }

    public function canAddExpense($amount): bool
    {
        return bccomp(bcadd((string) $this->spent_amount, (string) $amount, 2), (string) $this->total_amount, 2) <= 0;
    }

    // ── Accessors ──
    public function getUtilizationPercentageAttribute(): float
    {
        if ((float) $this->total_amount <= 0) {
            return 0;
        }
        return round(((float) $this->spent_amount / (float) $this->total_amount) * 100, 1);
    }

    public function getUtilizationColorAttribute(): string
    {
        $u = $this->utilization_percentage;
        return $u > 100 ? 'red' : ($u > 90 ? 'amber' : ($u > 75 ? 'yellow' : 'green'));
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-700',
            'pending_approval' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-blue-100 text-blue-700',
            'active' => 'bg-green-100 text-green-800',
            'closed' => 'bg-slate-200 text-slate-700',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    // ── Scopes ──
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByFiscalYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /** Lifecycle helpers. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval'], true);
    }

    public function allocationsLocked(): bool
    {
        return in_array($this->status, ['active', 'closed', 'cancelled'], true);
    }
}
