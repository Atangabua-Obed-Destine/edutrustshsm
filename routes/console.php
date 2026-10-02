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

/*
|--------------------------------------------------------------------------
| EdutrustPay reporting
|--------------------------------------------------------------------------
|
| This school PUSHES a signed monthly summary to its body's console. Nothing
| reaches in here: outbound HTTPS only, no inbound endpoint, no tunnel.
|
| Three jobs rather than one, because they fail differently. Building the report
| needs the database and nothing else; delivering it needs the network, which at
| a school office comes and goes; and the heartbeat has to keep going on the days
| there is nothing to build, because otherwise a quiet month and a dead server
| look identical from the other end.
|
| All three are no-ops unless EDUTRUSTPAY_ENABLED is true.
*/

// Built on the 4th, once the previous month has had time to settle.
Schedule::command('edutrustpay:report')
    ->monthlyOn(4, '03:00')
    ->withoutOverlapping()
    ->onOneServer();

// Delivery retries on its own schedule; the outbox decides what is due.
Schedule::command('edutrustpay:flush')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('edutrustpay:heartbeat')
    ->dailyAt('05:30')
    ->onOneServer();
