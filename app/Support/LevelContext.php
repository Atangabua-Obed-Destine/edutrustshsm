<?php

namespace App\Support;

use App\Models\Form;
use App\Models\SchoolSetting;

/**
 * Manages the admin's currently-active "school level" working context
 * (Nursery/Primary vs Secondary/High), stored in the session and used to
 * scope form dropdowns and Academic lists across the portal.
 */
class LevelContext
{
    public const SESSION_KEY = 'active_school_level';

    /**
     * Human-readable labels for each school level.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'nursery_primary' => __('Nursery / Primary'),
            'secondary' => __('Secondary / High School'),
        ];
    }

    /**
     * The school-level keys this installation actually operates, driven by the
     * School Level Mode setting (Nursery/Primary only, Secondary only, or Both).
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return SchoolSetting::current()?->activeSchoolLevels()
            ?? array_keys(self::labels());
    }

    /**
     * Labels for only the levels this installation runs (mode-aware), for the
     * switcher and the Form create/edit dropdown.
     *
     * @return array<string, string>
     */
    public static function availableLabels(): array
    {
        return array_intersect_key(self::labels(), array_flip(self::options()));
    }

    /**
     * Whether this school runs a given level at all.
     *
     * Distinct from current(): a school may run both and be working in one.
     * This asks what the school is, not where the admin is standing — which
     * is the right question for deciding whether a whole feature belongs.
     */
    public static function runs(string $level): bool
    {
        return in_array($level, self::options(), true);
    }

    /** Whether this school runs any secondary level. */
    public static function runsSecondary(): bool
    {
        return self::runs('secondary');
    }

    /** Whether this school runs any nursery or primary level. */
    public static function runsNurseryPrimary(): bool
    {
        return self::runs('nursery_primary');
    }

    /**
     * Whether the header level switcher should be shown — only when the school
     * runs more than one level (mode = Both).
     */
    public static function showSwitcher(): bool
    {
        return self::isConfigured() && count(self::options()) > 1;
    }

    /**
     * Whether the installation has chosen its School Level Mode yet.
     */
    public static function isConfigured(): bool
    {
        return (bool) SchoolSetting::current()?->isLevelModeConfigured();
    }

    /**
     * The currently-active school level, clamped to the modes this school runs.
     * Falls back to the first allowed level (which, in single-level mode, is the
     * only one).
     */
    public static function current(): string
    {
        $options = self::options();
        $level = session(self::SESSION_KEY);

        if ($level && in_array($level, $options, true)) {
            return $level;
        }

        $fromData = Form::query()->distinct()->pluck('school_level')->first();
        if ($fromData && in_array($fromData, $options, true)) {
            return $fromData;
        }

        return $options[0] ?? 'secondary';
    }

    /**
     * Whether any active class at the level the admin is working in is split
     * into streams (Science / Arts / ...). A nursery or primary school never
     * is, so its fee screens should not ask which stream.
     */
    public static function usesStreams(): bool
    {
        return Form::query()
            ->where('school_level', self::current())
            ->where('is_active', true)
            ->where('has_streams', true)
            ->exists();
    }

    /**
     * The label for the currently-active school level.
     */
    public static function currentLabel(): string
    {
        return self::labels()[self::current()] ?? self::current();
    }

    /**
     * Set the active school level (ignored if invalid).
     */
    public static function set(string $level): void
    {
        if (in_array($level, self::options(), true)) {
            session([self::SESSION_KEY => $level]);
        }
    }
}
