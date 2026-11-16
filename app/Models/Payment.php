<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use Auditable, BelongsToBranch;

    /**
     * Methods that bring no new money in. A receipt paid from a pupil's
     * credit spends cash that arrived earlier; a payment from before the
     * switch was received and booked in the old system. Both settle fees,
     * neither belongs in a cash total or the ledger as a receipt.
     */
    public const NON_CASH_METHODS = ['student_credit', 'prior_system'];

    protected $fillable = [
        'branch_id',
        'receipt_number', 'student_enrollment_id', 'amount', 'payment_method',
        'payment_date', 'payer_name', 'payer_phone', 'bank_name',
        'transaction_ref', 'proof_document', 'verification_status',
        'received_by', 'notes', 'payment_account_id',
        'reversed_at', 'reversed_by', 'reversal_reason', 'verify_code',
    ];

    /** What a payment method is called, in the reader's language. */
    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'cash' => __('Cash'),
            'bank_transfer' => __('Bank Transfer'),
            'mtn_momo' => 'MTN Mobile Money',
            'orange_money' => 'Orange Money',
            'edutrustpay' => 'EduTrustPay',
            'student_credit' => __('Student Credit'),
            'prior_system' => __('Paid before the switch'),
            null, '' => '—',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }

    public function getMethodLabelAttribute(): string
    {
        return static::methodLabel($this->payment_method);
    }

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

    /** Letters for the check code (see App\Support\CheckCode). */
    public const VERIFY_ALPHABET = \App\Support\CheckCode::ALPHABET;

    /** A fresh, unused check code. */
    public static function newVerifyCode(): string
    {
        return \App\Support\CheckCode::generate(fn (string $code) => static::withoutBranchScope()->where('verify_code', $code)->exists());
    }

    /** What someone typed, as it is stored. */
    public static function normaliseVerifyCode(?string $typed): string
    {
        return \App\Support\CheckCode::normalise($typed);
    }

    /**
     * This receipt's check code, made the first time it is asked for (receipts
     * from before codes existed get one when they are next printed).
     */
    public function verifyCode(): string
    {
        if (! $this->verify_code) {
            $this->forceFill(['verify_code' => static::newVerifyCode()])->saveQuietly();
        }

        return $this->verify_code;
    }

    /** The code as printed: XXXX-XXXX-XXXX. */
    public function verifyCodeForPrint(): string
    {
        return \App\Support\CheckCode::forPrint($this->verifyCode());
    }

    /** The public page that says whether this receipt is genuine. */
    public function verifyUrl(): string
    {
        return route('receipt.verify', $this->verifyCode());
    }
}
