<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'start_date', 'end_date', 'status', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /** Current session for the active branch (branch-scoped by BranchScope). */
    public static function current(): ?self
    {
        return static::where('is_current', true)->first();
    }

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /** The current term for the active branch (terms are branch-global). */
    public function currentTerm(): ?Term
    {
        return Term::where('is_current', true)->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
