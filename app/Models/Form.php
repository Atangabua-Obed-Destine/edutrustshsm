<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Support\LevelContext;
use Illuminate\Database\Eloquent\Model;

class Form extends Model
{
    use BelongsToBranch;

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
