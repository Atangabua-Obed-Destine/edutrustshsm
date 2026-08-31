<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Requires the scheduler to be running:
|   * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Late-payment penalties. Accrual is a recompute, so a missed day or a double
// run costs nothing — the figure is derived from the bands, not accumulated.
Schedule::command('fees:accrue-fines')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->onOneServer();

// Recurring journal entries. Generation is idempotent per run date, so a
// missed day or a repeated run cannot produce duplicates.
Schedule::command('accounting:recurring-entries')
    ->dailyAt('01:30')
    ->withoutOverlapping()
    ->onOneServer();

// Outstanding-fee reminders. One message per guardian covering every child,
// and a fee already chased inside the interval is left alone — a daily run
// must not mail the same parent every morning.
Schedule::command('fees:remind')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping()
    ->onOneServer();
