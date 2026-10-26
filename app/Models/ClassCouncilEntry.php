<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/**
 * What the council decided about one pupil.
 *
 * The awards are the ones Cameroonian schools actually give. They are not
 * computed from the average — the service suggests one, and the council
 * overrides it, which is the entire point of holding a council rather than
 * running a query.
 */
class ClassCouncilEntry extends Model
{
    use BelongsToBranch;

    public const NONE = 'none';
    public const CONGRATULATIONS = 'congratulations';
    public const HONOUR_ROLL = 'honour_roll';
    public const ENCOURAGEMENT = 'encouragement';
    public const WORK_WARNING = 'work_warning';
    public const CONDUCT_WARNING = 'conduct_warning';

    protected $fillable = [
        'branch_id', 'class_council_id', 'student_id', 'term_result_id',
        'average', 'rank', 'award', 'conduct', 'remark',
    ];

    protected function casts(): array
    {
        return ['average' => 'decimal:2'];
    }

    public function council()
    {
        return $this->belongsTo(ClassCouncil::class, 'class_council_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function termResult()
    {
        return $this->belongsTo(TermResult::class);
    }

    /** @return array<string, string> award => label */
    public static function awards(): array
    {
        return [
            self::CONGRATULATIONS => __('Congratulations'),
            self::HONOUR_ROLL => __('Honour Roll'),
            self::ENCOURAGEMENT => __('Encouragement'),
            self::NONE => __('No award'),
            self::WORK_WARNING => __('Warning — work'),
            self::CONDUCT_WARNING => __('Warning — conduct'),
        ];
    }

    public function awardLabel(): string
    {
        return self::awards()[$this->award] ?? $this->award;
    }

    /** Tailwind classes for the award chip, so praise and warning read apart at a glance. */
    public function awardClasses(): string
    {
        return match ($this->award) {
            self::CONGRATULATIONS => 'bg-emerald-100 text-emerald-700',
            self::HONOUR_ROLL => 'bg-teal-100 text-teal-700',
            self::ENCOURAGEMENT => 'bg-sky-100 text-sky-700',
            self::WORK_WARNING => 'bg-amber-100 text-amber-700',
            self::CONDUCT_WARNING => 'bg-rose-100 text-rose-700',
            default => 'bg-gray-100 text-gray-500',
        };
    }
}
