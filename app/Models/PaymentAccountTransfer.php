<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PaymentAccountTransfer extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'from_account_id', 'to_account_id', 'amount', 'transfer_date',
        'note', 'attach', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
        ];
    }

    public function fromAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(PaymentAccount::class, 'to_account_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
