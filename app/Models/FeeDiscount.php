<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FeeDiscount extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'student_enrollment_id', 'discount_type', 'value',
        'reason', 'description', 'apply_to', 'fee_category_id',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
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

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
