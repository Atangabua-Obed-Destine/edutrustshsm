<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountTransaction extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'payment_account_id', 'transaction_type', 'amount', 'transaction_date',
        'title', 'description', 'reference_type', 'reference_id',
        'payment_method', 'payment_reference', 'balance_after', 'attach', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    /**
     * Canonical reference_type tokens — one per source, used everywhere
     * (store, transfer, link) so the soft-FK back to the source is consistent.
     */
    public const REF_INCOME = 'income';
    public const REF_EXPENSE = 'expense';
    public const REF_FEE_PAYMENT = 'fee_payment';
    public const REF_TRANSFER = 'transfer';
    public const REF_DEPOSIT = 'deposit';
    public const REF_WITHDRAWAL = 'withdrawal';
    public const REF_PAYROLL = 'payroll';

    /**
     * Source types whose transactions must NOT be hand-edited/deleted on the
     * account book (they're owned by the originating record).
     */
    public const LINKED_REFS = [
        self::REF_INCOME, self::REF_EXPENSE, self::REF_FEE_PAYMENT, self::REF_TRANSFER,
        self::REF_PAYROLL,
    ];

    public function isLinked(): bool
    {
        return in_array($this->reference_type, self::LINKED_REFS, true);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Resolve the originating model for this transaction, if any.
     */
    public function reference(): ?Model
    {
        return match ($this->reference_type) {
            self::REF_INCOME => Income::find($this->reference_id),
            self::REF_EXPENSE => Expense::find($this->reference_id),
            self::REF_FEE_PAYMENT => Payment::find($this->reference_id),
            self::REF_TRANSFER => PaymentAccountTransfer::find($this->reference_id),
            self::REF_PAYROLL => Payroll::find($this->reference_id),
            default => null,
        };
    }
}
