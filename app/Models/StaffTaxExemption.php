<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class StaffTaxExemption extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'user_id', 'tax_setting_id', 'custom_percentage', 'custom_fixed_amount',
        'reason', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_percentage' => 'decimal:4',
            'custom_fixed_amount' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function taxSetting()
    {
        return $this->belongsTo(TaxSetting::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopeNotExpired($query)
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', now()->toDateString()));
    }
}
