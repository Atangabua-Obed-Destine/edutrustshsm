<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** One mark changed after approval: before, after, why and who. Never edited. */
class MarkCorrection extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id', 'mark_id', 'marks_submission_id',
        'old_score', 'old_absent', 'new_score', 'new_absent', 'reason', 'corrected_by',
    ];

    protected function casts(): array
    {
        return [
            'old_score' => 'decimal:1', 'new_score' => 'decimal:1',
            'old_absent' => 'boolean', 'new_absent' => 'boolean',
        ];
    }

    public function mark()
    {
        return $this->belongsTo(Mark::class);
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /** A mark as read on paper: "12.5", or "Absent", or "—". */
    public static function describe($score, bool $absent): string
    {
        if ($absent) {
            return __('Absent');
        }

        return $score === null ? '—' : rtrim(rtrim(number_format((float) $score, 1, '.', ''), '0'), '.');
    }
}
