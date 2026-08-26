<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FeeBreakdown extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'fee_structure_id', 'name', 'amount', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }
}
