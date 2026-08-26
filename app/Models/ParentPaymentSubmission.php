<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class ParentPaymentSubmission extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'guardian_id', 'student_enrollment_id', 'student_fee_id',
        'amount', 'payment_method', 'bank_name', 'transaction_ref',
        'payment_date', 'receipt_path', 'notes',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes', 'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function guardian() { return $this->belongsTo(Guardian::class); }
    public function enrollment() { return $this->belongsTo(StudentEnrollment::class, 'student_enrollment_id'); }
    public function studentFee() { return $this->belongsTo(StudentFee::class); }
    public function reviewedBy() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function payment() { return $this->belongsTo(Payment::class); }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
