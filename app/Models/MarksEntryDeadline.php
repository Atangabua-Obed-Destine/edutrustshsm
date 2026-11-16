<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * The last day teachers may enter or submit marks for a sequence in one
 * school year. After it, only someone holding results-control.deadline can
 * (to enter late marks themselves, or to move the date).
 */
class MarksEntryDeadline extends Model
{
    use BelongsToBranch;

    protected $fillable = ['branch_id', 'academic_session_id', 'sequence_id', 'closes_on', 'set_by'];

    protected function casts(): array
    {
        return ['closes_on' => 'date'];
    }

    /** The deadline for a sequence this year, if one is set. */
    public static function for(?int $sessionId, ?int $sequenceId): ?self
    {
        if (! $sessionId || ! $sequenceId) {
            return null;
        }

        return static::where('academic_session_id', $sessionId)->where('sequence_id', $sequenceId)->first();
    }

    /** Closed once the day after closes_on begins: the last day is a full day. */
    public function isClosed(): bool
    {
        return now()->startOfDay()->gt($this->closes_on);
    }

    public function sequence()
    {
        return $this->belongsTo(Sequence::class);
    }
}
