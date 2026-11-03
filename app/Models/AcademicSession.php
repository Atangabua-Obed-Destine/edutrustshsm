<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class AcademicSession extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'start_date', 'end_date', 'status', 'is_current', 'is_recorded',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            // A year from before the system: recorded from the report cards,
            // never computed, never current. See App\Services\PastYearRecorder.
            'is_recorded' => 'boolean',
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
