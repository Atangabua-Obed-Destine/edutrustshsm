<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class BudgetRevision extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'budget_id', 'revision_number', 'previous_amount', 'new_amount',
        'change_amount', 'change_type', 'reason', 'justification', 'status',
        'rejected_reason', 'requested_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BudgetRevision $rev) {
            if (! $rev->revision_number) {
                $rev->revision_number = (static::where('budget_id', $rev->budget_id)->max('revision_number') ?? 0) + 1;
            }
            $rev->change_amount = bcsub((string) $rev->new_amount, (string) $rev->previous_amount, 2);
            $cmp = bccomp((string) $rev->new_amount, (string) $rev->previous_amount, 2);
            $rev->change_type = $cmp > 0 ? 'increase' : ($cmp < 0 ? 'decrease' : 'reallocation');
        });
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Apply this revision to the parent budget. */
    public function approve(?int $userId = null): void
    {
        $budget = $this->budget;
        $budget->total_amount = $this->new_amount;
        $budget->remaining_amount = bcsub((string) $this->new_amount, (string) $budget->spent_amount, 2);
        $budget->save();

        $this->update([
            'status' => 'approved',
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update(['status' => 'rejected', 'rejected_reason' => $reason]);
    }
}
