<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TaxGroup extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'title', 'code', 'description', 'is_progressive',
        'effective_from', 'effective_to', 'display_order', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_progressive' => 'boolean',
            'status' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function brackets()
    {
        return $this->hasMany(TaxSetting::class)->orderBy('bracket_order');
    }

    public function scopeEffective($query, string $date)
    {
        return $query->where('status', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public static function getEffectiveGroups(string $date)
    {
        return static::effective($date)->orderBy('display_order')->with(['brackets' => fn ($q) => $q->effective($date)])->get();
    }

    /**
     * Step lookup: the single applicable bracket = the active bracket with the
     * highest min_amount ≤ salary (tax applied once, not summed across bands).
     */
    public function applicableBracket(float $salary): ?TaxSetting
    {
        return $this->brackets
            ->where('status', true)
            ->filter(fn ($b) => $salary >= (float) $b->min_amount)
            ->sortByDesc('min_amount')
            ->first();
    }
}
