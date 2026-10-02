<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Support\LevelContext;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use Auditable, BelongsToBranch;

    protected $fillable = [
        'branch_id',
        'name', 'short_name', 'level', 'school_level', 'education_system',
        'has_streams', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'has_streams' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Two-tier taxonomy: school_level -> its valid sub-levels.
     */
    public const LEVELS = [
        'secondary' => ['first_cycle', 'second_cycle'],
        'nursery_primary' => ['nursery', 'primary'],
    ];

    public static function levelsFor(string $schoolLevel): array
    {
        return self::LEVELS[$schoolLevel] ?? [];
    }

    /**
     * What each sub-level is called, and the classes it covers.
     *
     * Screens used to hardcode the secondary pair, which left the student form
     * offering "First Cycle / Second Cycle" to a nursery school — and, because
     * its form dropdown matches on this value, offering no classes at all.
     */
    public static function levelLabel(?string $level): string
    {
        return match ($level) {
            'nursery' => __('Nursery'),
            'primary' => __('Primary'),
            'first_cycle' => __('First Cycle'),
            'second_cycle' => __('Second Cycle'),
            default => (string) $level,
        };
    }

    public static function levelHint(?string $level): string
    {
        return match ($level) {
            'nursery' => __('Nursery 1–3'),
            'primary' => __('Class 1–6'),
            'first_cycle' => __('Form 1–5'),
            'second_cycle' => __('6th Form'),
            default => '',
        };
    }

    /**
     * The sub-levels a school level offers, as value => [label, hint].
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function subLevelsFor(string $schoolLevel): array
    {
        $levels = [];

        foreach (self::levelsFor($schoolLevel) as $level) {
            $levels[$level] = ['label' => self::levelLabel($level), 'hint' => self::levelHint($level)];
        }

        return $levels;
    }

    /** Forms belonging to the school levels this installation actually runs. */
    public function scopeForActiveSchoolLevels($query)
    {
        return $query->whereIn('school_level', \App\Support\LevelContext::options());
    }

    public function streams()
    {
        return $this->belongsToMany(Stream::class, 'form_stream');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'form_subject')
            ->withPivot('stream_id', 'coefficient', 'type');
    }

    public function classSections()
    {
        return $this->hasMany(ClassSection::class);
    }

    public function feeStructures()
    {
        return $this->hasMany(FeeStructure::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    public function scopeForCurrentLevel($query)
    {
        return $query->where('school_level', LevelContext::current());
    }
}
