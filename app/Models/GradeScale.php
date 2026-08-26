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
        return static::where('min_mark', '<=', $score)
            ->where('max_mark', '>=', $score)
            ->orderBy('display_order')
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
