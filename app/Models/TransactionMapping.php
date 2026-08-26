<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TransactionMapping extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'transaction_type', 'transaction_id', 'debit_account_id', 'credit_account_id',
        'amount', 'journal_entry_id', 'status',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }
}
