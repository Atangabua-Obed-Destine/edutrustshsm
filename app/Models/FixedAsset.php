<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'fixed_asset_category_id', 'code', 'name', 'description',
        'acquisition_date', 'cost', 'salvage_value', 'useful_life_years',
        'method', 'declining_rate', 'location', 'status',
        'disposal_date', 'disposal_amount', 'disposal_note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'disposal_date' => 'date',
            'cost' => 'decimal:2',
            'salvage_value' => 'decimal:2',
            'disposal_amount' => 'decimal:2',
            'useful_life_years' => 'integer',
            'declining_rate' => 'decimal:2',
        ];
    }

    public function category()
    {
        return $this->belongsTo(FixedAssetCategory::class, 'fixed_asset_category_id');
    }

    public function schedules()
    {
        return $this->hasMany(DepreciationSchedule::class)->orderBy('period_number');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isDisposed(): bool
    {
        return in_array($this->status, ['disposed', 'written_off'], true);
    }

    /** The portion of cost that will actually be depreciated. */
    public function depreciableAmount(): float
    {
        return max(0, round((float) $this->cost - (float) $this->salvage_value, 2));
    }

    /** Depreciation posted to the ledger so far. */
    public function accumulatedDepreciation(): float
    {
        return (float) $this->schedules()->where('is_posted', true)->sum('amount');
    }

    /**
     * Book value net of POSTED depreciation.
     *
     * Deliberately not net of the whole schedule: an unposted period has not
     * happened yet as far as the ledger is concerned, and disposal maths has to
     * agree with the accounts.
     */
    public function bookValue(): float
    {
        return round((float) $this->cost - $this->accumulatedDepreciation(), 2);
    }
}
