<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'code', 'description', 'is_mandatory', 'is_refundable', 'is_tuition', 'is_boarding', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_refundable' => 'boolean',
            'is_tuition' => 'boolean',
            'is_boarding' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }
}
