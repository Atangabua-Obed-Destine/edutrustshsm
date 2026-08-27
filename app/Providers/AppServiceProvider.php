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
use Illuminate\Support\Facades\Gate;
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

        StudentEnrollment::observe(StudentEnrollmentObserver::class);

        // OHADA auto-posting: turn operational facts into journal entries.
        Income::observe(IncomeObserver::class);
        Expense::observe(ExpenseObserver::class);
        Payment::observe(PaymentObserver::class);
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
