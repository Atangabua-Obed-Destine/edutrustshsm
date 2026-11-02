<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    use BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'min_mark', 'max_mark', 'grade', 'description', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'min_mark' => 'decimal:1',
            'max_mark' => 'decimal:1',
        ];
    }

    /** Resolve the grade letter for a score within the active branch's scale. */
    public static function gradeFor(float $score): ?self
    {
        // The highest band whose minimum the score reaches: bands are stored
        // to one decimal, so a maximum test leaves 15.95 in no band.
        return static::where('min_mark', '<=', $score)
            ->orderByDesc('min_mark')
            ->first();
    }

    /**
     * Alias of gradeFor() — the report-card controller and views call getGrade().
     * Kept so both names resolve to the same lookup.
     */
    public static function getGrade(float $score): ?self
    {
        return static::gradeFor($score);
    }
}
