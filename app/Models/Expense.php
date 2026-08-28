<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'category_id', 'title', 'invoice_id', 'amount', 'date', 'reference',
        'payment_method', 'payment_account_id', 'note', 'attach', 'status',
        'budget_id', 'budget_allocation_id', 'approval_status', 'approved_by', 'approved_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'date' => 'date',
            'status' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetAllocation()
    {
        return $this->belongsTo(BudgetAllocation::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
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
