<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BudgetDashboardController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'budget';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('budget.view', ['index', 'summary']),
        ];
    }

    public function index()
    {
        $budgets = Budget::active()->with(['department', 'allocations.expenseCategory'])->get();

        $kpis = [
            'total' => $budgets->sum('total_amount'),
            'allocated' => $budgets->sum('allocated_amount'),
            'spent' => $budgets->sum('spent_amount'),
            'remaining' => $budgets->sum('remaining_amount'),
            'active_count' => $budgets->count(),
        ];
        $kpis['utilization'] = $kpis['total'] > 0 ? round(($kpis['spent'] / $kpis['total']) * 100, 1) : 0;
        $kpis['allocation_pct'] = $kpis['total'] > 0 ? round(($kpis['allocated'] / $kpis['total']) * 100, 1) : 0;

        // Department spending (pie)
        $deptSpending = $budgets->groupBy(fn ($b) => $b->department?->name ?? 'General')
            ->map(fn ($g) => (float) $g->sum('spent_amount'))
            ->filter(fn ($v) => $v > 0)
            ->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values();

        // Category allocated-vs-spent (bar) across active budgets' allocations
        $allocations = BudgetAllocation::whereIn('budget_id', $budgets->pluck('id'))->with('expenseCategory')->get();
        $byCategory = $allocations->groupBy(fn ($a) => $a->expenseCategory?->title ?? '—')
            ->map(fn ($g, $k) => [
                'label' => $k,
                'allocated' => (float) $g->sum('allocated_amount'),
                'spent' => (float) $g->sum('spent_amount'),
            ])->values();

        // Monthly spend trend (budgeted expenses, current year)
        $months = [];
        $monthlySpend = [];
        $year = now()->year;
        for ($m = 1; $m <= now()->month; $m++) {
            $start = sprintf('%d-%02d-01', $year, $m);
            $end = date('Y-m-t', strtotime($start));
            $months[] = date('M', strtotime($start));
            $monthlySpend[] = (float) Expense::whereNotNull('budget_id')
                ->where('approval_status', '!=', 'rejected')
                ->whereBetween('date', [$start, $end])->sum('amount');
        }

        // Alerts at >75 / >90 / >100% for budgets and allocations
        $alerts = [];
        foreach ($budgets as $b) {
            $u = $b->utilization_percentage;
            if ($u > 75) {
                $alerts[] = ['name' => $b->title, 'level' => $u > 100 ? 'danger' : ($u > 90 ? 'warning' : 'info'), 'pct' => $u, 'type' => 'Budget'];
            }
        }
        foreach ($allocations as $a) {
            $u = $a->utilization_percentage;
            if ($u > 75) {
                $alerts[] = ['name' => $a->title, 'level' => $u > 100 ? 'danger' : ($u > 90 ? 'warning' : 'info'), 'pct' => $u, 'type' => 'Allocation'];
            }
        }

        return view('admin.budget.dashboard', compact('budgets', 'kpis', 'deptSpending', 'byCategory', 'months', 'monthlySpend', 'alerts'));
    }

    /** JSON drill-down for a single budget. */
    public function summary(Budget $budget)
    {
        return response()->json([
            'budget' => $budget->only(['id', 'title', 'budget_code', 'total_amount', 'allocated_amount', 'spent_amount', 'remaining_amount']),
            'utilization' => $budget->utilization_percentage,
            'allocations' => $budget->allocations()->with('expenseCategory')->get()->map(fn ($a) => [
                'title' => $a->title,
                'category' => $a->expenseCategory?->title,
                'allocated' => $a->allocated_amount,
                'spent' => $a->spent_amount,
                'utilization' => $a->utilization_percentage,
            ]),
        ]);
    }
}
