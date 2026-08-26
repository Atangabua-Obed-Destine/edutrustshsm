<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    public const STATUS_UNPAID = 0;
    public const STATUS_PAID = 1;

    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'user_id', 'basic_salary', 'salary_type', 'total_earning', 'total_allowance',
        'bonus', 'total_deduction', 'gross_salary', 'tax', 'employer_tax',
        'net_salary', 'total_cost', 'salary_month', 'pay_date', 'payment_method',
        'payment_account_id', 'bank_account_id', 'status', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'total_earning' => 'decimal:2',
            'total_allowance' => 'decimal:2',
            'bonus' => 'decimal:2',
            'total_deduction' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'tax' => 'decimal:2',
            'employer_tax' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'pay_date' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class);
    }

    public function allowances()
    {
        return $this->details()->where('status', 1);
    }

    public function deductions()
    {
        return $this->details()->where('status', 0);
    }

    public function paymentAccount()
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(StaffBankAccount::class, 'bank_account_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
