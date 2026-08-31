<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StudentFee extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_enrollment_id', 'fee_category_id',
        'original_amount', 'discount_amount', 'waiver_amount', 'fine_amount',
        'net_amount', 'paid_amount', 'balance', 'status',
        'due_date', 'last_reminded_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'due_date' => 'date',
            'last_reminded_at' => 'datetime',
        ];
    }

    /**
     * Recompute net, balance and status from the components.
     *
     * The formula lived in five places (fee sync, quick assign, discount apply
     * and reverse, payment allocation) and had already drifted — the discount
     * reversal, for instance, had lost the "waived" status branch. Fines would
     * have made it six. One method, one definition.
     */
    public function recalculate(): static
    {
        $net = (float) $this->original_amount
            - (float) $this->discount_amount
            - (float) $this->waiver_amount
            + (float) $this->fine_amount;

        $this->net_amount = max(0, round($net, 2));
        $this->balance = max(0, round((float) $this->net_amount - (float) $this->paid_amount, 2));

        $this->status = match (true) {
            (float) $this->balance > 0 && (float) $this->paid_amount > 0 => 'partial',
            (float) $this->balance > 0 => 'unpaid',
            // Nothing left to pay: either it was settled, or it was written off
            // entirely by a discount/waiver and was never really charged.
            (float) $this->net_amount <= 0 && (float) $this->paid_amount <= 0 => 'waived',
            default => 'paid',
        };

        return $this;
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function feeCategory()
    {
        return $this->belongsTo(FeeCategory::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentPlan()
    {
        return $this->hasOne(PaymentPlan::class);
    }
}
