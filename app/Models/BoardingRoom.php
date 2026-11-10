<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A room in a boarding house, with a fixed number of beds. */
class BoardingRoom extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'boarding_house_id', 'name', 'beds', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['beds' => 'integer', 'is_active' => 'boolean'];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(BoardingHouse::class, 'boarding_house_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BoardingAllocation::class);
    }

    /** The boarders sleeping here now. */
    public function occupants(): HasMany
    {
        return $this->hasMany(BoardingAllocation::class)->whereNull('end_date');
    }
}
