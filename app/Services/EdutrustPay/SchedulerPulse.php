<?php

namespace App\Services\EdutrustPay;

use Carbon\Carbon;

/**
 * Whether the scheduler is actually running on this computer.
 *
 * Reports are built and delivered by scheduled jobs, and the scheduler only
 * runs if the server has been told to start it every minute (cron on Linux,
 * Task Scheduler on Windows). When nobody has done that, nothing fails and
 * nothing is logged: the institution simply never reports, and from the
 * console it looks like a school that went quiet.
 *
 * So every `schedule:run` leaves a timestamp in a small file, and the
 * EdutrustPay settings screen reads it back. A file rather than the cache, so
 * the answer does not depend on which cache driver this install uses.
 */
class SchedulerPulse
{
    /** Minutes without a run before the scheduler counts as stopped. */
    public const STALE_AFTER_MINUTES = 10;

    public static function path(): string
    {
        return storage_path('app/edutrustpay-scheduler-pulse');
    }

    public static function beat(): void
    {
        @file_put_contents(self::path(), (string) time());
    }

    public static function lastSeen(): ?Carbon
    {
        $path = self::path();

        if (! is_file($path)) {
            return null;
        }

        $time = (int) trim((string) @file_get_contents($path));

        return $time > 0 ? Carbon::createFromTimestamp($time) : null;
    }

    /**
     * @return 'running'|'stopped'|'never'
     */
    public static function state(): string
    {
        $seen = self::lastSeen();

        if ($seen === null) {
            return 'never';
        }

        return $seen->gt(now()->subMinutes(self::STALE_AFTER_MINUTES)) ? 'running' : 'stopped';
    }
}
