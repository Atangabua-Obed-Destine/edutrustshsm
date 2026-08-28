<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FixedAssetCategory extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'name', 'useful_life_years', 'method', 'declining_rate',
        'asset_account_id', 'depreciation_account_id', 'accumulated_account_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'useful_life_years' => 'integer',
            'declining_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function assets()
    {
        return $this->hasMany(FixedAsset::class);
    }

    public function assetAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_account_id');
    }

    public function depreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_account_id');
    }

    public function accumulatedAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
