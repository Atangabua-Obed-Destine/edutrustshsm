<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class PayrollDetail extends Model
{
    use Auditable;

    public const TYPE_DEDUCTION = 0;
    public const TYPE_ALLOWANCE = 1;

    use BelongsToBranch;

    protected $fillable = [
        'branch_id','payroll_id', 'title', 'amount', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
