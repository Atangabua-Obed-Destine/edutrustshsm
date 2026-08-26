<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\StudentEnrollment;
use App\Observers\ExpenseObserver;
use App\Observers\IncomeObserver;
use App\Observers\PaymentObserver;
use App\Observers\StudentEnrollmentObserver;
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
        StudentEnrollment::observe(StudentEnrollmentObserver::class);

        // OHADA auto-posting: turn operational facts into journal entries.
        Income::observe(IncomeObserver::class);
        Expense::observe(ExpenseObserver::class);
        Payment::observe(PaymentObserver::class);
    }
}
