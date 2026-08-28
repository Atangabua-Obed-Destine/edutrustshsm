<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class TaxSetting extends Model
{
    use Auditable;

    public const TYPE_PERCENTAGE = 1;
    public const TYPE_FIXED = 2;

    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'tax_title', 'tax_group_id', 'bracket_order', 'min_amount', 'max_amount',
        'max_no_taxable_amount', 'tax_type', 'percentage', 'fixed_amount',
        'employer_percentage', 'employer_fixed_amount', 'paid_by', 'is_shared',
        'is_dependent', 'depends_on_type', 'depends_on_id', 'effective_from',
        'effective_to', 'status',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'max_no_taxable_amount' => 'decimal:2',
            'percentage' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'employer_percentage' => 'decimal:4',
            'employer_fixed_amount' => 'decimal:2',
            'is_shared' => 'boolean',
            'is_dependent' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function taxGroup()
    {
        return $this->belongsTo(TaxGroup::class);
    }

    public function scopeEffective($query, string $date)
    {
        return $query->where('status', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    public function scopeStandalone($query)
    {
        return $query->whereNull('tax_group_id');
    }

    /** Base the percentage applies to (tax-free allowance removed). */
    public function taxableBase(float $amount): float
    {
        return max(0, $amount - (float) $this->max_no_taxable_amount);
    }

    /**
     * Employee contribution for a given base amount, honouring an optional
     * exemption: a custom value replaces the rate; no custom value ⇒ fully exempt.
     */
    public function employeeContribution(float $amount, ?StaffTaxExemption $exemption = null): float
    {
        if (! in_array($this->paid_by, ['employee', 'both'])) {
            return 0;
        }
        if ($exemption && ! $exemption->isExpired()) {
            if ($this->tax_type == self::TYPE_PERCENTAGE && $exemption->custom_percentage !== null) {
                return round($this->taxableBase($amount) * (float) $exemption->custom_percentage / 100, 2);
            }
            if ($this->tax_type == self::TYPE_FIXED && $exemption->custom_fixed_amount !== null) {
                return round((float) $exemption->custom_fixed_amount, 2);
            }
            return 0; // exempt, no custom rate
        }

        return $this->tax_type == self::TYPE_PERCENTAGE
            ? round($this->taxableBase($amount) * (float) $this->percentage / 100, 2)
            : round((float) $this->fixed_amount, 2);
    }

    /** Employer contribution; 0 when the staff member is exempt. */
    public function employerContribution(float $amount, ?StaffTaxExemption $exemption = null): float
    {
        if (! in_array($this->paid_by, ['employer', 'both'])) {
            return 0;
        }
        if ($exemption && ! $exemption->isExpired()) {
            return 0;
        }

        return $this->tax_type == self::TYPE_PERCENTAGE
            ? round($this->taxableBase($amount) * (float) $this->employer_percentage / 100, 2)
            : round((float) $this->employer_fixed_amount, 2);
    }

    public function coversAmount(float $amount): bool
    {
        return $amount >= (float) $this->min_amount
            && ((float) $this->max_amount <= 0 || $amount <= (float) $this->max_amount);
    }
}
