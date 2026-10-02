<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'receipt_number', 'student_enrollment_id', 'amount', 'payment_method',
        'payment_date', 'payer_name', 'payer_phone', 'bank_name',
        'transaction_ref', 'proof_document', 'verification_status',
        'received_by', 'notes', 'payment_account_id',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function enrollment()
    {
        return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function reversedBy()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    /** A reversed payment is kept as a record but no longer counts as money. */
    public function isReversed(): bool
    {
        return $this->verification_status === 'reversed';
    }
}
