<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A pupil's report card for one term kept from the parent portal: unpaid
 * fees, a disciplinary matter, a dispute over a mark. The school still sees
 * and prints it; the family is told to contact the school. Released with a
 * reason of its own, and the row stays as history.
 */
class ResultHold extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id', 'student_id', 'academic_session_id', 'term_id',
        'reason', 'held_by', 'held_at', 'release_reason', 'released_by', 'released_at',
    ];

    protected function casts(): array
    {
        return ['held_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at');
    }

    /** The hold in force on this pupil's card for that term, if any. */
    public static function activeFor(?int $studentId, ?int $sessionId, ?int $termId): ?self
    {
        if (! $studentId || ! $sessionId || ! $termId) {
            return null;
        }

        return static::active()
            ->where('student_id', $studentId)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $termId)
            ->latest('id')
            ->first();
    }

    /** Whether a term result is held from the family. */
    public static function holds(TermResult $result): bool
    {
        $enrollment = $result->enrollment;

        return static::activeFor($enrollment?->student_id, $enrollment?->academic_session_id, $result->term_id) !== null;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    public function heldBy()
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
