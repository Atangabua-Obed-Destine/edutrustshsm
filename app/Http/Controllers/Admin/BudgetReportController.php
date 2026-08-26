<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Department;
use Illuminate\Http\Request;

class BudgetReportController extends Controller
{
    /** Performance: per-budget total/allocated/spent/remaining/utilization. */
    public function performance(Request $request)
    {
        $fiscalYear = $request->input('fiscal_year');
        $budgets = Budget::with('department')
            ->when($fiscalYear, fn ($q) => $q->where('fiscal_year', $fiscalYear))
            ->orderByDesc('id')->get();

        $fiscalYears = Budget::select('fiscal_year')->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year');

        return view('admin.budget.reports.performance', compact('budgets', 'fiscalYears', 'fiscalYear'));
    }

    /** Variance: planned (total) vs actual (spent), variance + variance %. */
    public function variance(Request $request)
    {
        $budgets = Budget::with('department')->orderByDesc('id')->get()->map(function ($b) {
            $variance = bcsub((string) $b->total_amount, (string) $b->spent_amount, 2);
            $variancePct = (float) $b->total_amount > 0 ? round(((float) $variance / (float) $b->total_amount) * 100, 1) : 0;
            return (object) ['budget' => $b, 'variance' => $variance, 'variance_pct' => $variancePct];
        });

        return view('admin.budget.reports.variance', compact('budgets'));
    }

    /** Department: roll-up of budget/spend per department. */
    public function department(Request $request)
    {
        $rows = Department::orderBy('name')->get()->map(function ($d) {
            $budgets = Budget::where('department_id', $d->id)->get();
            return (object) [
                'department' => $d->name,
                'count' => $budgets->count(),
                'total' => $budgets->sum('total_amount'),
                'spent' => $budgets->sum('spent_amount'),
                'remaining' => $budgets->sum('remaining_amount'),
            ];
        })->filter(fn ($r) => $r->count > 0)->values();

        // Budgets with no department (general/project)
        $generalBudgets = Budget::whereNull('department_id')->get();
        if ($generalBudgets->count()) {
            $rows->push((object) [
                'department' => __('General / No Department'),
                'count' => $generalBudgets->count(),
                'total' => $generalBudgets->sum('total_amount'),
                'spent' => $generalBudgets->sum('spent_amount'),
                'remaining' => $generalBudgets->sum('remaining_amount'),
            ]);
        }

        return view('admin.budget.reports.department', compact('rows'));
    }

    /** Cashflow: monthly budgeted spend across the fiscal year. */
    public function cashflow(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $start = sprintf('%d-%02d-01', $year, $m);
            $end = date('Y-m-t', strtotime($start));
            $months[] = (object) [
                'label' => date('F', strtotime($start)),
                'spent' => \App\Models\Expense::whereNotNull('budget_id')
                    ->where('approval_status', '!=', 'rejected')
                    ->whereBetween('date', [$start, $end])->sum('amount'),
            ];
        }

        return view('admin.budget.reports.cashflow', compact('months', 'year'));
    }
}
