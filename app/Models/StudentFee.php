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
        'original_amount', 'discount_amount', 'waiver_amount',
        'net_amount', 'paid_amount', 'balance', 'status',
        'due_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'waiver_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'due_date' => 'date',
        ];
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
