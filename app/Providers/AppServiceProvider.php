<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Observers\ExpenseObserver;
use App\Observers\IncomeObserver;
use App\Observers\PaymentObserver;
use App\Observers\StudentEnrollmentObserver;
use Illuminate\Cache\RateLimiting\Limit;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuthorization();
        $this->registerRateLimiters();

        StudentEnrollment::observe(StudentEnrollmentObserver::class);

        // OHADA auto-posting: turn operational facts into journal entries.
        Income::observe(IncomeObserver::class);
        Expense::observe(ExpenseObserver::class);
        Payment::observe(PaymentObserver::class);
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
}
