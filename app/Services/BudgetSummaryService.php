<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Payroll;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * A budget read the way a bursar reads it: month by month, and against the
 * calendar.
 *
 * The monthly summary is the columns of the paper form — Budget · This month ·
 * Brought forward · Cumulative · Balance — for each line. Pacing is the one
 * number that turns the budget from a record into a control: how much of the
 * year has gone against how much of the money has, said early enough to act
 * on.
 *
 * Spending the budget cannot place is reported, never dropped: an expense in
 * a category with no allocation, and salaries, which are paid outside the
 * expense categories altogether.
 */
class BudgetSummaryService
{
    /**
     * @return array{
     *   month: Carbon, months: array<int, array{label: string, value: string}>,
     *   lines: array<int, array>, totals: array<string, float>,
     *   outside: array<int, array{heading: string, monthly: float, cumulative: float}>,
     *   salaries: array{monthly: float, cumulative: float}
     * }
     */
    public function monthly(Budget $budget, Carbon $month): array
    {
        $start = $budget->start_date->copy()->startOfDay();
        $end = $budget->end_date->copy()->endOfDay();

        $month = $month->copy()->startOfMonth();
        $monthStart = $month->max($start);
        $monthEnd = $month->copy()->endOfMonth()->min($end);
        $priorEnd = $monthStart->copy()->subDay();

        $monthly = $this->spentByCategory($monthStart, $monthEnd, $budget);
        $brought = $priorEnd->gte($start) ? $this->spentByCategory($start, $priorEnd, $budget) : collect();

        $lines = [];
        $totals = ['budget' => 0.0, 'monthly' => 0.0, 'brought' => 0.0, 'cumulative' => 0.0, 'balance' => 0.0];

        foreach ($budget->allocations()->with('expenseCategory')->orderBy('title')->get() as $allocation) {
            $key = $allocation->expense_category_id;
            $m = (float) ($monthly[$key] ?? 0);
            $b = (float) ($brought[$key] ?? 0);
            $figure = (float) $allocation->allocated_amount;

            $lines[] = [
                'allocation' => $allocation,
                'title' => $allocation->title,
                'category' => $allocation->expenseCategory?->title,
                'budget' => $figure,
                'monthly' => $m,
                'brought' => $b,
                'cumulative' => $m + $b,
                'balance' => $figure - ($m + $b),
            ];

            $totals['budget'] += $figure;
            $totals['monthly'] += $m;
            $totals['brought'] += $b;
            $totals['cumulative'] += $m + $b;
            $totals['balance'] += $figure - ($m + $b);
        }

        // Categories that spent but were never given a line.
        $allocated = $budget->allocations()->pluck('expense_category_id')->flip();
        $outsideMonthly = $this->spentByCategory($monthStart, $monthEnd, null, 'title');
        $outsideCumulative = $this->spentByCategory($start, $monthEnd, null, 'title');
        $outside = [];
        foreach ($outsideCumulative as $categoryId => $amount) {
            if ($allocated->has($categoryId)) {
                continue;
            }
            $outside[] = [
                'heading' => $this->categoryTitles()[$categoryId] ?? "#{$categoryId}",
                'monthly' => (float) ($outsideMonthly[$categoryId] ?? 0),
                'cumulative' => (float) $amount,
            ];
        }

        return [
            'month' => $month,
            'months' => collect(CarbonPeriod::create($start->copy()->startOfMonth(), '1 month', $end))
                ->map(fn ($d) => ['label' => $d->translatedFormat('M Y'), 'value' => $d->format('Y-m')])->all(),
            'lines' => $lines,
            'totals' => $totals,
            'outside' => $outside,
            'salaries' => [
                'monthly' => $this->salariesPaid($monthStart, $monthEnd),
                'cumulative' => $this->salariesPaid($start, $monthEnd),
            ],
        ];
    }

    /**
     * Are we spending faster than the year is passing?
     *
     * @return array{elapsed: float, consumed: float, gap: float, status: string, projected: float, days_left: int}
     */
    public function pacing(Budget $budget, ?Carbon $today = null): array
    {
        $today ??= now();
        $start = $budget->start_date->copy()->startOfDay();
        $end = $budget->end_date->copy()->endOfDay();

        $span = max(1, $start->diffInSeconds($end));
        $elapsed = max(0.0, min(1.0, $start->diffInSeconds($today->copy()->max($start), false) / $span)) * 100;

        $total = (float) $budget->total_amount;
        $spent = (float) $budget->spent_amount;
        $consumed = $total > 0 ? ($spent / $total) * 100 : 0.0;
        $gap = $consumed - $elapsed;

        // Where the year lands if this rate simply continues.
        $projected = $elapsed > 0 ? $spent / ($elapsed / 100) : 0.0;

        if ($total <= 0) {
            $status = 'none';
        } elseif ($gap > 15) {
            $status = 'critical';
        } elseif ($gap > 5) {
            $status = 'warning';
        } else {
            $status = 'good';
        }

        return [
            'elapsed' => $elapsed,
            'consumed' => $consumed,
            'gap' => $gap,
            'status' => $status,
            'projected' => $projected,
            'days_left' => max(0, (int) $today->diffInDays($end, false)),
        ];
    }

    /**
     * Spend between two dates, keyed by expense category.
     *
     * Read from the expense rows by category and date — not from the tags —
     * so the summary says what was really spent on each line whether or not
     * the expense happened to be linked.
     *
     * @return \Illuminate\Support\Collection<int, float>
     */
    private function spentByCategory(Carbon $from, Carbon $to, ?Budget $budget, string $keyBy = 'id')
    {
        return Expense::query()
            ->where('status', true)
            ->where('approval_status', '!=', 'rejected')
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (float) $v);
    }

    private function salariesPaid(Carbon $from, Carbon $to): float
    {
        return (float) Payroll::query()
            ->where('status', Payroll::STATUS_PAID)
            ->whereDate('pay_date', '>=', $from->toDateString())
            ->whereDate('pay_date', '<=', $to->toDateString())
            ->sum('net_salary');
    }

    /** @var array<int, string>|null */
    private ?array $titles = null;

    /** @return array<int, string> */
    private function categoryTitles(): array
    {
        return $this->titles ??= DB::table('expense_categories')->pluck('title', 'id')->all();
    }
}
