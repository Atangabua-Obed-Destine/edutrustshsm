<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    // Not Auditable: the permission catalogue is seeded reference data, not
    // something operators edit — auditing it just floods the trail on install.
    protected $fillable = [
        'name',
        'display_name',
        'group_name',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')->withTimestamps();
    }

    /**
     * Get all permissions grouped by group_name.
     */
    public static function allGrouped(): array
    {
        return static::orderBy('group_name')
            ->orderBy('id')
            ->get()
            ->groupBy('group_name')
            ->toArray();
    }
}
