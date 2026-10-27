<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Support\LevelContext;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'code', 'school_level', 'department_id', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Subjects taught at the level the admin is working in.
     *
     * A subject with no level is taught at both. English, Mathematics and
     * French are the obvious cases, and keeping them as one subject means one
     * set of marks and one report line rather than two of each.
     */
    public function scopeForCurrentLevel($query)
    {
        return $query->forLevel(LevelContext::current());
    }

    /**
     * Subjects taught at a named level.
     *
     * Preferred over forCurrentLevel() wherever the level is known from the
     * thing being worked on — the class whose curriculum is being set, say —
     * because that is the level that governs, not whichever one the admin
     * happens to have selected in the header.
     */
    public function scopeForLevel($query, ?string $level)
    {
        if (! $level) {
            return $query;
        }

        return $query->where(fn ($q) => $q
            ->whereNull('school_level')
            ->orWhere('school_level', $level));
    }

    /** Subjects the whole school shares, whichever level is in context. */
    public function scopeTaughtAtEveryLevel($query)
    {
        return $query->whereNull('school_level');
    }

    /** What this subject's level is called, for a badge or a dropdown. */
    public function levelLabel(): string
    {
        return $this->school_level
            ? (LevelContext::labels()[$this->school_level] ?? $this->school_level)
            : __('Both levels');
    }

    public function forms()
    {
        return $this->belongsToMany(Form::class, 'form_subject')
            ->withPivot('stream_id', 'coefficient', 'type');
    }
}
