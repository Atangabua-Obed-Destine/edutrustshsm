<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'term_number', 'name', 'start_date', 'end_date', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /** Current term for the active branch (branch-scoped by BranchScope). */
    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }

    public function sequences()
    {
        return $this->hasMany(Sequence::class);
    }
}
