<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * One payment of withheld payroll tax to an authority, for one salary month.
 */
class TaxRemittance extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'liability_account_id', 'salary_month', 'amount', 'payment_date',
        'source_account_id', 'payment_account_id', 'reference', 'note', 'journal_entry_id',
        'voided_at', 'voided_by', 'void_reason', 'void_journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function liabilityAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'liability_account_id')->withTrashed();
    }

    public function sourceAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'source_account_id')->withTrashed();
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** Remittances that still count — voided ones stay on file but do not. */
    public function scopeLive($query)
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
