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
