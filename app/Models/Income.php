<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'category_id', 'title', 'invoice_id', 'amount', 'date', 'reference',
        'payment_method', 'payment_account_id', 'note', 'attach', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'status' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(IncomeCategory::class, 'category_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
