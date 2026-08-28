<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A late-payment penalty band: "between :start_day and :end_day days past due,
 * charge :amount" — either a fixed sum or a percentage of the fee.
 *
 * Bands attach to fee categories, so tuition can carry a penalty while a PTA
 * levy does not.
 */
class FeeFine extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'title', 'start_day', 'end_day', 'type', 'amount', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_day' => 'integer',
            'end_day' => 'integer',
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function feeCategories(): BelongsToMany
    {
        return $this->belongsToMany(FeeCategory::class, 'fee_category_fee_fine');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Whether this band covers a given number of days past due. */
    public function covers(int $daysOverdue): bool
    {
        if ($daysOverdue < $this->start_day) {
            return false;
        }

        return $this->end_day === null || $daysOverdue <= $this->end_day;
    }

    /** The penalty this band imposes on a given fee amount. */
    public function penaltyOn(float $amount): float
    {
        return $this->type === 'percentage'
            ? round($amount * (float) $this->amount / 100, 2)
            : round((float) $this->amount, 2);
    }

    public function getDescriptionAttribute(): string
    {
        $window = $this->end_day === null
            ? __(':n+ days late', ['n' => $this->start_day])
            : __(':a-:b days late', ['a' => $this->start_day, 'b' => $this->end_day]);

        $charge = $this->type === 'percentage'
            ? $this->amount.'%'
            : number_format((float) $this->amount, 0);

        return $window.' — '.$charge;
    }
}
