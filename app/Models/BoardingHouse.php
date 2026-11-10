<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** A boarding house (dormitory): boys', girls' or mixed, with a house master or mistress. */
class BoardingHouse extends Model
{
    use BelongsToBranch;

    public const GENDERS = ['boys', 'girls', 'mixed'];

    protected $fillable = ['branch_id', 'name', 'gender', 'house_master_id', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(BoardingRoom::class)->orderBy('name');
    }

    public function houseMaster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'house_master_id');
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(BoardingAllocation::class, BoardingRoom::class);
    }

    /** Whether a pupil of this gender may be placed here. */
    public function takes(?string $gender): bool
    {
        return match ($this->gender) {
            'boys' => $gender === 'male',
            'girls' => $gender === 'female',
            default => true,
        };
    }
}
