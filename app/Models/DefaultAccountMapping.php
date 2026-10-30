<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class DefaultAccountMapping extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'mapping_type', 'category_id', 'budget_line_id', 'debit_account_id', 'credit_account_id', 'status',
    ];

    public function budgetLine()
    {
        return $this->belongsTo(BudgetLine::class);
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
