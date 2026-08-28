<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentAllocation extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'payment_id', 'student_fee_id', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function studentFee()
    {
        return $this->belongsTo(StudentFee::class);
    }
}
