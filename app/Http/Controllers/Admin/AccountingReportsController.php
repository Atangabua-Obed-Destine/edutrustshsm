<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\FiscalYear;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\StudentFee;
use App\Services\AgingReportService;
use App\Services\CashFlowStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports that answer the questions a bursar is actually asked: who owes us,
 * how long have they owed it, what did we plan to spend against what we spent,
 * and where did the cash go.
 *
 * All read-side, all derived from data the system already holds — nothing here
 * stores or mutates anything.
 */
class AccountingReportsController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'accounting-report';

    public function __construct(
        private AgingReportService $aging,
        private CashFlowStatementService $cashFlow,
    ) {
    }

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('accounting-report.view', [
                'index', 'receivablesAging', 'payablesAging', 'studentFeeAging', 'budgetVsActual',
                'cashFlowStatement', 'comparativeCashFlow', 'exportCashFlow',
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

    /** Where the cash came from and went, for one period. */
    public function cashFlowStatement(Request $request)
    {
        [$from, $to] = $this->period($request);

        return view('admin.accounting.reports.cash-flow', [
            'report' => $this->cashFlow->statement($from, $to),
            'comparative' => false,
            'fiscalYears' => FiscalYear::orderByDesc('start_date')->get(['id', 'name']),
        ]);
    }

    /** The same statement beside the preceding period, with variances. */
    public function comparativeCashFlow(Request $request)
    {
        [$from, $to, $fiscalYear] = $this->period($request);
        [$previousFrom, $previousTo] = $this->previousPeriod($from, $to, $fiscalYear);

        return view('admin.accounting.reports.cash-flow', [
            'report' => $this->cashFlow->comparative($from, $to, $previousFrom, $previousTo),
            'comparative' => true,
            'fiscalYears' => FiscalYear::orderByDesc('start_date')->get(['id', 'name']),
        ]);
    }

    /** PDF or CSV of either statement, with the same filters as the screen. */
    public function exportCashFlow(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,csv'],
        ]);

        $comparative = $request->boolean('comparative');
        [$from, $to, $fiscalYear] = $this->period($request);

        $report = $comparative
            ? $this->cashFlow->comparative($from, $to, ...$this->previousPeriod($from, $to, $fiscalYear))
            : $this->cashFlow->statement($from, $to);

        $filename = ($comparative ? 'comparative-cash-flow' : 'cash-flow').'-'.$from.'-'.$to;

        if ($validated['format'] === 'pdf') {
            return Pdf::loadView('admin.accounting.reports.pdf.cash-flow', [
                'report' => $report,
                'comparative' => $comparative,
                'school' => SchoolSetting::current(),
                'currency' => SchoolSetting::current()?->currency ?? 'FCFA',
            ])->setPaper('a4', 'portrait')->download($filename.'.pdf');
        }

        return $this->csv($report, $comparative, $filename.'.csv');
    }

    /**
     * The period a report covers: explicit dates win, then a chosen fiscal
     * year, then the active fiscal year, then the calendar year to date.
     *
     * @return array{0: string, 1: string, 2: ?FiscalYear}
     */
    private function period(Request $request): array
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'fiscal_year_id' => ['nullable', 'exists:fiscal_years,id'],
        ]);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [$request->input('start_date'), $request->input('end_date'), null];
        }

        $fiscalYear = $request->filled('fiscal_year_id')
            ? FiscalYear::find($request->input('fiscal_year_id'))
            : FiscalYear::active();

        if ($fiscalYear) {
            return [$fiscalYear->start_date->toDateString(), $fiscalYear->end_date->toDateString(), $fiscalYear];
        }

        return [now()->startOfYear()->toDateString(), now()->toDateString(), null];
    }

    /**
     * The period to compare against: the fiscal year before, when there is
     * one, otherwise the same dates a year earlier.
     *
     * @return array{0: string, 1: string}
     */
    private function previousPeriod(string $from, string $to, ?FiscalYear $fiscalYear): array
    {
        if ($fiscalYear) {
            $prior = FiscalYear::whereDate('start_date', '<', $fiscalYear->start_date)
                ->orderByDesc('start_date')
                ->first();

            if ($prior) {
                return [$prior->start_date->toDateString(), $prior->end_date->toDateString()];
            }
        }

        return [
            Carbon::parse($from)->subYearNoOverflow()->toDateString(),
            Carbon::parse($to)->subYearNoOverflow()->toDateString(),
        ];
    }

    /** @param array<string, mixed> $report */
    private function csv(array $report, bool $comparative, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($report, $comparative) {
            $out = fopen('php://output', 'w');

            // Excel reads UTF-8 as Latin-1 without this.
            fwrite($out, "\xEF\xBB\xBF");

            $current = $comparative ? $report['current'] : $report;
            $previous = $comparative ? $report['previous'] : null;

            fputcsv($out, $comparative
                ? [__('Section'), __('Line'), __('Current'), __('Previous'), __('Variance')]
                : [__('Section'), __('Line'), __('Amount')]);

            foreach ($current['sections'] as $key => $section) {
                $prior = $comparative ? collect($previous['sections'][$key]['items'])->keyBy('key') : null;

                foreach ($section['items'] as $item) {
                    fputcsv($out, $comparative
                        ? [$section['title'], $item['label'], $item['amount'], $prior[$item['key']]['amount'] ?? 0, $report['variance'][$key][$item['key']] ?? 0]
                        : [$section['title'], $item['label'], $item['amount']]);
                }

                fputcsv($out, $comparative
                    ? [$section['title'], __('Net cash from this activity'), $section['total'], $previous['sections'][$key]['total'], $report['variance'][$key]['_total']]
                    : [$section['title'], __('Net cash from this activity'), $section['total']]);
            }

            foreach ([
                [__('Net change in cash'), 'net_change'],
                [__('Cash at the start of the period'), 'opening_cash'],
                [__('Cash at the end of the period'), 'closing_cash'],
            ] as [$label, $field]) {
                fputcsv($out, $comparative
                    ? ['', $label, $current[$field], $previous[$field], round($current[$field] - $previous[$field], 2)]
                    : ['', $label, $current[$field]]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
