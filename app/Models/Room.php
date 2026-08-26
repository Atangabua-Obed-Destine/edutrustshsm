<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'building_floor', 'type', 'capacity', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function classSections()
    {
        return $this->hasMany(ClassSection::class);
    }
}
