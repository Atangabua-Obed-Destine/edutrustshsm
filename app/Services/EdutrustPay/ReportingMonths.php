<?php

namespace App\Services\EdutrustPay;

use Carbon\Carbon;

/**
 * Which calendar months a reporting job covers.
 *
 * By default the last N complete months. With $toDate, the month in progress
 * as well, so the body's console shows this month's money as it comes in
 * rather than only once the month is over. That month goes out provisional,
 * is filed again whenever it changes (a new sequence, the console keeping
 * every version), and is filed final once it has ended.
 */
final class ReportingMonths
{
    /**
     * @return array<int, string> YYYY-MM, oldest first
     */
    public static function recent(int $count, bool $toDate = false): array
    {
        $count = max(1, $count);
        $cursor = Carbon::now()->startOfMonth()->subMonths($count + 1);

        $months = collect(range(1, $count))->map(fn () => $cursor->addMonth()->format('Y-m'))->all();

        if ($toDate) {
            $months[] = Carbon::now()->format('Y-m');
        }

        return $months;
    }
}
