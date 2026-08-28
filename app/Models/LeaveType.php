<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use Auditable;

    protected $fillable = ['title', 'slug', 'annual_limit', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return [
            'annual_limit' => 'integer',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Whether this type caps how many days a staff member may take per year. */
    public function isCapped(): bool
    {
        return $this->annual_limit !== null && $this->annual_limit > 0;
    }
}
