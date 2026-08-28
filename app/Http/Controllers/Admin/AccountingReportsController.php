<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\Form;
use App\Models\StudentFee;
use App\Services\AgingReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;

/**
 * Reports that answer the questions a bursar is actually asked: who owes us,
 * how long have they owed it, what did we plan to spend against what we spent.
 *
 * All read-side, all derived from data the system already holds — nothing here
 * stores or mutates anything.
 */
class AccountingReportsController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'accounting-report';

    public function __construct(private AgingReportService $aging)
    {
    }

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('accounting-report.view', [
                'index', 'receivablesAging', 'payablesAging', 'studentFeeAging', 'budgetVsActual',
            ]),
        ];
    }

    public function index()
    {
        $asOf = Carbon::today();

        return view('admin.accounting.reports.dashboard', [
            'asOf' => $asOf,
            'outstandingFees' => (float) StudentFee::where('balance', '>', 0)->sum('balance'),
            'studentsOwing' => StudentFee::where('balance', '>', 0)
                ->distinct('student_enrollment_id')->count('student_enrollment_id'),
            'receivables' => $this->aging->ledgerAging('debit')['totals']['total'] ?? 0.0,
            'payables' => $this->aging->ledgerAging('credit')['totals']['total'] ?? 0.0,
            'activeBudgets' => Budget::whereIn('status', ['approved', 'active'])->count(),
        ]);
    }

    /** Money owed TO the school, aged (OHADA class 4 debit accounts). */
    public function receivablesAging(Request $request)
    {
        $report = $this->aging->ledgerAging('debit', $request->input('as_of'));

        return view('admin.accounting.reports.aging', $report + [
            'title' => __('Receivables Aging'),
            'subject' => __('Account'),
            'asOf' => $request->input('as_of') ?? Carbon::today()->toDateString(),
            'kind' => 'ledger',
        ]);
    }

    /** Money the school OWES, aged (OHADA class 4 credit accounts). */
    public function payablesAging(Request $request)
    {
        $report = $this->aging->ledgerAging('credit', $request->input('as_of'));

        return view('admin.accounting.reports.aging', $report + [
            'title' => __('Payables Aging'),
            'subject' => __('Account'),
            'asOf' => $request->input('as_of') ?? Carbon::today()->toDateString(),
            'kind' => 'ledger',
        ]);
    }

    /**
     * Outstanding student fees, aged by due date.
     *
     * Sourced from student_fees rather than the ledger: it carries a real
     * per-fee due date and balance, which the chart of accounts does not model.
     */
    public function studentFeeAging(Request $request)
    {
        $report = $this->aging->studentFees(
            $request->input('as_of'),
            $request->input('form_id')
        );

        return view('admin.accounting.reports.aging', $report + [
            'title' => __('Student Fee Aging'),
            'subject' => __('Student'),
            'asOf' => $request->input('as_of') ?? Carbon::today()->toDateString(),
            'kind' => 'student',
            'forms' => Form::active()->ordered()->get(['id', 'name']),
        ]);
    }

    /** Planned vs actual spend, per budget and per allocation. */
    public function budgetVsActual(Request $request)
    {
        $budgets = Budget::with(['allocations.expenseCategory'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->get()
            ->map(function (Budget $budget) {
                $planned = (float) $budget->total_amount;
                $spent = (float) $budget->spent_amount;

                return (object) [
                    'budget' => $budget,
                    'planned' => $planned,
                    'spent' => $spent,
                    'variance' => $planned - $spent,
                    // Positive variance = under budget. Guard against a zero
                    // total so an unfunded budget does not divide by zero.
                    'utilisation' => $planned > 0 ? round($spent / $planned * 100, 1) : 0.0,
                    'lines' => $budget->allocations->map(fn ($a) => (object) [
                        'label' => $a->expenseCategory?->title ?? __('Unassigned'),
                        'planned' => (float) $a->allocated_amount,
                        'spent' => (float) $a->spent_amount,
                        'variance' => (float) $a->allocated_amount - (float) $a->spent_amount,
                    ]),
                ];
            });

        return view('admin.accounting.reports.budget-vs-actual', [
            'budgets' => $budgets,
            'status' => $request->input('status'),
        ]);
    }
}
