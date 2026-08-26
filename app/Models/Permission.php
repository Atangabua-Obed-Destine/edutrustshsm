<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
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
