<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class OutcomeController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'outcome';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('outcome.view', ['index']),
        ];
    }

    public function index(Request $request)
    {
        $from = $request->input('from_date', now()->subYear()->toDateString());
        $to = $request->input('to_date', now()->toDateString());

        $totalIncome = Income::where('status', true)->whereBetween('date', [$from, $to])->sum('amount');
        $totalExpense = Expense::where('status', true)->whereBetween('date', [$from, $to])->sum('amount');
        $net = bcsub((string) $totalIncome, (string) $totalExpense, 2);

        // Breakdown by category (for donut charts)
        $incomeByCategory = IncomeCategory::where('status', true)
            ->withSum(['incomes as total' => fn ($q) => $q->whereBetween('date', [$from, $to])], 'amount')
            ->get()
            ->filter(fn ($c) => $c->total > 0)
            ->map(fn ($c) => ['label' => $c->title, 'value' => (float) $c->total])
            ->values();

        $expenseByCategory = ExpenseCategory::where('status', true)
            ->withSum(['expenses as total' => fn ($q) => $q->whereBetween('date', [$from, $to])], 'amount')
            ->get()
            ->filter(fn ($c) => $c->total > 0)
            ->map(fn ($c) => ['label' => $c->title, 'value' => (float) $c->total])
            ->values();

        // Monthly series for the current year (Jan -> current month)
        $months = [];
        $monthlyIncome = [];
        $monthlyExpense = [];
        $year = now()->year;
        for ($m = 1; $m <= now()->month; $m++) {
            $start = sprintf('%d-%02d-01', $year, $m);
            $end = date('Y-m-t', strtotime($start));
            $months[] = date('M', strtotime($start));
            $monthlyIncome[] = (float) Income::where('status', true)->whereBetween('date', [$start, $end])->sum('amount');
            $monthlyExpense[] = (float) Expense::where('status', true)->whereBetween('date', [$start, $end])->sum('amount');
        }

        return view('admin.account.outcome.index', compact(
            'from', 'to', 'totalIncome', 'totalExpense', 'net',
            'incomeByCategory', 'expenseByCategory', 'months', 'monthlyIncome', 'monthlyExpense'
        ));
    }
}
