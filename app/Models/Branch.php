<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use Auditable;

    protected $fillable = ['name', 'code', 'slug', 'logo', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user')->withPivot('is_default')->withTimestamps();
    }

    public function settings()
    {
        return $this->hasOne(SchoolSetting::class);
    }
}
