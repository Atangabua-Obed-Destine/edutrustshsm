<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentPlanInstallment extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'payment_plan_id', 'installment_number', 'amount', 'due_date',
        'paid_amount', 'late_fee_amount', 'status', 'payment_id', 'paid_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_date' => 'date',
        ];
    }

    public function paymentPlan()
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount - (float) $this->paid_amount;
    }
}
