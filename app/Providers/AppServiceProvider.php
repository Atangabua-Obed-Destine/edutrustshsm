<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Income;
use App\Models\PaymentAllocation;
use App\Models\StudentCredit;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Observers\ExpenseObserver;
use App\Observers\IncomeObserver;
use App\Observers\PaymentAllocationObserver;
use App\Observers\StudentCreditObserver;
use App\Observers\StudentEnrollmentObserver;
use App\Observers\StudentObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Asked by the menu and by a banner on several screens in the same
        // request; one instance means one count.
        $this->app->scoped(\App\Services\OutOfScopeService::class);
        $this->app->scoped(\App\Services\TeachingScope::class);

        // Lets a school level's word list sit on top of lang/{locale}.json.
        // (extend, not singleton: the translation provider is deferred and
        // would replace a plain binding when it loads.)
        $this->app->extend('translation.loader', fn ($loader, $app) => new \App\Support\LevelFileLoader(
            $app['files'], [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']]
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->trustTheTunnel();
        $this->registerAuthorization();
        $this->registerRateLimiters();

        // Every scheduler run leaves a timestamp, so the EdutrustPay screen can
        // say whether reports go out on their own or the scheduler was never
        // set up on this computer. See App\Services\EdutrustPay\SchedulerPulse.
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Console\Events\CommandStarting::class, function ($event) {
            if ($event->command === 'schedule:run') {
                \App\Services\EdutrustPay\SchedulerPulse::beat();
            }
        });

        Student::observe(StudentObserver::class);
        StudentEnrollment::observe(StudentEnrollmentObserver::class);

        // OHADA auto-posting: turn operational facts into journal entries.
        Income::observe(IncomeObserver::class);
        Expense::observe(ExpenseObserver::class);

        // Fee revenue posts per ALLOCATION, not per payment: a payment can span
        // several fee categories, and the ledger needs to tell them apart.
        PaymentAllocation::observe(PaymentAllocationObserver::class);

        // An over-payment is a liability until it is applied to a fee.
        StudentCredit::observe(StudentCreditObserver::class);
    }

    /**
     * Throttle credential submission on every portal.
     *
     * None of the three login endpoints (staff, parent, applicant) was rate
     * limited, so all three were open to unbounded credential stuffing.
     * Keyed on email + IP so one attacker cannot lock out a whole school behind
     * a shared NAT, nor hammer many accounts from one address.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identifier = (string) $request->input('email', $request->input('login_email', ''));

            return [
                Limit::perMinute(5)->by(mb_strtolower($identifier).'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });
    }

    /**
     * Bridge the Role/Permission tables into Laravel's Gate.
     *
     * Every seeded permission becomes a gate ability under its own name
     * ("fee-collection.view"), so views can use @can / @canany and controllers
     * the `permission:` middleware without hand-defining ~150 gates.
     *
     * Returning null falls through to Gate's default deny, so an ability that
     * nobody granted is denied rather than silently allowed.
     */
    private function registerAuthorization(): void
    {
        Gate::before(function ($user, string $ability) {
            // Only staff users carry permissions. Guardians and applicants sit on
            // their own guards and must never be granted an admin ability.
            if (! $user instanceof User) {
                return null;
            }

            if ($user->role === 'super_admin') {
                return true;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }

    /**
     * Believe the tunnel in front of the school's server about the scheme
     * (https), port and visitor's address, so links come out https. The host
     * header is not trusted: a forged one could put another domain into
     * password-reset links. See config('app.trusted_proxies').
     */
    private function trustTheTunnel(): void
    {
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) config('app.trusted_proxies')))));
        if ($proxies === []) {
            return;
        }

        TrustProxies::at($proxies);
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    }
}
