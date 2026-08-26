<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'receipt_number', 'student_enrollment_id', 'amount', 'payment_method',
        'payment_date', 'payer_name', 'payer_phone', 'bank_name',
        'transaction_ref', 'proof_document', 'verification_status',
        'received_by', 'notes', 'payment_account_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
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
}
