<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'shortcode', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }
}
