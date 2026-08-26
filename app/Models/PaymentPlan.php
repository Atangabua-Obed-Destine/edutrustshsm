<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_fee_id', 'total_amount', 'number_of_installments',
        'late_fee_percentage', 'grace_period_days', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'late_fee_percentage' => 'decimal:2',
            'grace_period_days' => 'integer',
            'number_of_installments' => 'integer',
        ];
    }

    public function studentFee()
    {
        return $this->belongsTo(StudentFee::class);
    }

    public function installments()
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('installment_number');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->installments->sum('paid_amount');
    }

    public function getRemainingAmountAttribute(): float
    {
        return (float) $this->total_amount - $this->paid_amount;
    }

    public function getProgressAttribute(): float
    {
        if ($this->total_amount <= 0) return 100;
        return round(($this->paid_amount / $this->total_amount) * 100, 1);
    }
}
