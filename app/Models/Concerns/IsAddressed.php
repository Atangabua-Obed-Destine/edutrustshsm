<?php

namespace App\Models\Concerns;

use App\Models\ClassSection;
use App\Models\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared by notices and events: who a posting reaches, and how widely.
 *
 * Two questions, deliberately kept apart. `audience` is who — guardians, staff,
 * or both. `scope` is how widely — the whole school, one form, or one class.
 * The PTA table used to answer both with a single column, which is why it could
 * never address staff.
 *
 * One definition of "addressed to me" lives here so the notice board, the PTA
 * page and the dashboard panel cannot drift apart.
 */
trait IsAddressed
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_FORM = 'form_specific';
    public const SCOPE_CLASS = 'class_specific';

    public const AUDIENCE_GUARDIANS = 'guardians';
    public const AUDIENCE_STAFF = 'staff';
    public const AUDIENCE_BOTH = 'both';

    /** @return array<int, string> */
    public static function scopes(): array
    {
        return [self::SCOPE_ALL, self::SCOPE_FORM, self::SCOPE_CLASS];
    }

    /** @return array<int, string> */
    public static function audiences(): array
    {
        return [self::AUDIENCE_GUARDIANS, self::AUDIENCE_STAFF, self::AUDIENCE_BOTH];
    }

    public function targetForm()
    {
        return $this->belongsTo(Form::class, 'target_form_id');
    }

    public function targetClass()
    {
        return $this->belongsTo(ClassSection::class, 'target_class_section_id');
    }

    /** Postings whose audience includes the given side of the school. */
    public function scopeFor(Builder $query, string $audience): Builder
    {
        return $query->whereIn('audience', [$audience, self::AUDIENCE_BOTH]);
    }

    public function scopeForGuardians(Builder $query): Builder
    {
        return $query->for(self::AUDIENCE_GUARDIANS);
    }

    public function scopeForStaff(Builder $query): Builder
    {
        return $query->for(self::AUDIENCE_STAFF);
    }

    /**
     * Postings that reach at least one of these children — school-wide, or
     * aimed at a form or class one of them is currently in.
     *
     * @param  Collection<int, \App\Models\Student>  $children
     */
    public function scopeAddressedToChildren(Builder $query, Collection $children): Builder
    {
        $formIds = $children
            ->map(fn ($child) => optional($child->currentEnrollment?->classSection)->form_id)
            ->filter()->unique()->all();

        $classIds = $children
            ->map(fn ($child) => $child->currentEnrollment?->class_section_id)
            ->filter()->unique()->all();

        return $query->where(function (Builder $q) use ($formIds, $classIds) {
            $q->where('scope', self::SCOPE_ALL)
                ->orWhere(fn (Builder $sub) => $sub->where('scope', self::SCOPE_FORM)
                    ->whereIn('target_form_id', $formIds ?: [0]))
                ->orWhere(fn (Builder $sub) => $sub->where('scope', self::SCOPE_CLASS)
                    ->whereIn('target_class_section_id', $classIds ?: [0]));
        });
    }

    /** How widely this posting reaches, in words, for a list or a badge. */
    public function reach(): string
    {
        return match ($this->scope) {
            self::SCOPE_FORM => $this->targetForm?->name ?? __('A form'),
            self::SCOPE_CLASS => $this->targetClass?->name ?? __('A class'),
            default => __('Whole school'),
        };
    }

    /**
     * Keep the target columns honest: a form-wide notice must not keep a class
     * from an earlier edit, or narrowing it later would silently change who
     * sees it.
     *
     * @param  array<string, mixed>  $input
     * @return array{target_form_id: int|null, target_class_section_id: int|null}
     */
    public static function targetsFor(array $input): array
    {
        $scope = $input['scope'] ?? self::SCOPE_ALL;

        return [
            'target_form_id' => $scope === self::SCOPE_FORM ? ($input['target_form_id'] ?? null) : null,
            'target_class_section_id' => $scope === self::SCOPE_CLASS ? ($input['target_class_section_id'] ?? null) : null,
        ];
    }
}
