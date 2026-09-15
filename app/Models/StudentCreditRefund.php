<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One refund of money held on a student's account: requested, then approved or
 * rejected, then — once approved — paid out.
 */
class StudentCreditRefund extends Model
{
    use Auditable;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROCESSED = 'processed';

    /** Ways the money can be returned, with their labels. */
    public const METHODS = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque',
        'mobile_money' => 'Mobile Money',
    ];

    protected $fillable = [
        'student_credit_id', 'amount', 'reason', 'status',
        'requested_by', 'requested_at',
        'reviewed_by', 'reviewed_at', 'rejection_reason',
        'processed_by', 'processed_at', 'method', 'reference', 'payment_account_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function credit()
    {
        return $this->belongsTo(StudentCredit::class, 'student_credit_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /** Requested or approved: money promised but not yet paid out. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_APPROVED], true);
    }
}
