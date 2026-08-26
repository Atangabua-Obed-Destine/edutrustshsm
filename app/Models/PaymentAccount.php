<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentAccount extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'title', 'account_number', 'account_type_id', 'opening_balance',
        'current_balance', 'description', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function accountType()
    {
        return $this->belongsTo(PaymentAccountType::class, 'account_type_id');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentAccountTransaction::class)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');
    }

    public function transfersFrom()
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'from_account_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(PaymentAccountTransfer::class, 'to_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Recompute current_balance (and each balance_after) from the ledger,
     * in chronological order. Maintenance/repair action for drift.
     *
     * Starts from zero because the opening balance is itself recorded as the
     * first credit transaction (so it is already part of the ledger).
     */
    public function recomputeBalance(): string
    {
        $running = '0';

        $rows = $this->transactions()
            ->reorder()
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();

        foreach ($rows as $txn) {
            $running = $txn->transaction_type === 'credit'
                ? bcadd($running, (string) $txn->amount, 2)
                : bcsub($running, (string) $txn->amount, 2);
            $txn->update(['balance_after' => $running]);
        }

        $this->update(['current_balance' => $running]);

        return $running;
    }
}
