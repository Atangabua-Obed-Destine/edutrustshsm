<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Models\BudgetLineForecast;
use App\Models\SchoolSetting;
use App\Services\BudgetActualsService;
use App\Services\TuitionForecastService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * The Income & Expenditure sheet: the school's annual budget, line by line,
 * with what actually happened beside what was planned.
 *
 * A sheet is a Budget flagged institutional, so it shares the lifecycle,
 * the revisions and the audit trail of every other budget. What differs is
 * that its allocations are against budget lines — income as well as
 * expenditure — and its actuals are resolved from the money already
 * recorded, never typed.
 */
class BudgetSheetController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'budget-sheet';

    public function __construct(private BudgetActualsService $actuals, private TuitionForecastService $tuition) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('budget-sheet.view', ['index', 'show']),
            static::can('budget-sheet.edit', ['store', 'saveFigures', 'forecast', 'updatePeriod', 'destroy']),
            static::can('budget-sheet.export', ['pdf', 'csv']),
        ];
    }

    public function index()
    {
        $sheets = Budget::where('is_institutional', true)->orderByDesc('start_date')->get();

        // Suggest the next period: the current academic year, or the year after
        // the latest sheet.
        $latest = $sheets->first();
        $session = AcademicSession::current();
        $suggested = $latest
            ? ['start' => $latest->end_date->copy()->addDay(), 'end' => $latest->end_date->copy()->addYear()]
            : ['start' => $session?->start_date ? Carbon::parse($session->start_date) : now()->startOfYear(), 'end' => $session?->end_date ? Carbon::parse($session->end_date) : now()->endOfYear()];

        return view('admin.budget-sheet.index', [
            'sheets' => $sheets,
            'suggested' => $suggested,
            'linesReady' => BudgetLine::active()->postable()->exists(),
            'session' => $session,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'opening_balance' => ['nullable', 'numeric'],
            'seed_from' => ['nullable', 'in:actual,budget'],
            'uplift_percent' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
        ]);

        $overlap = Budget::where('is_institutional', true)
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlap) {
            return back()->withInput()->with('error', __('A sheet already covers part of that period. One sheet per period, or the same money is budgeted twice.'));
        }

        $budget = Budget::create([
            'title' => $data['title'],
            'type' => 'annual',
            'is_institutional' => true,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'opening_balance' => $data['opening_balance'] ?? $this->previousClosingBalance($data['start_date']),
            'total_amount' => 0,
            'status' => 'draft',
            'fiscal_year' => $this->label($data['start_date'], $data['end_date']),
            'budget_code' => 'IES-'.Carbon::parse($data['start_date'])->format('Y'),
            'created_by' => $request->user()?->id,
        ]);

        $seeded = empty($data['seed_from']) ? 0 : $this->seedFromPrevious($budget, $data['seed_from'], (float) ($data['uplift_percent'] ?? 0));

        return redirect()->route('admin.budget-sheet.show', $budget)->with('success', $seeded
            ? __(':count lines carried forward. Review every figure before submitting.', ['count' => $seeded])
            : __('Sheet created. Enter the figures below.'));
    }

    public function show(Budget $budget)
    {
        return view('admin.budget-sheet.show', $this->sheetData($budget));
    }

    /** The sheet as a PDF, for signing and filing. */
    public function pdf(Budget $budget)
    {
        $data = $this->sheetData($budget) + ['pdfMode' => true];

        return Pdf::loadView('admin.budget-sheet.pdf', $data)->setPaper('a4', 'portrait')
            ->download('income-expenditure-'.$budget->fiscal_year.'.pdf');
    }

    /** The sheet as CSV, to work in. */
    public function csv(Budget $budget)
    {
        $data = $this->sheetData($budget);
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [__('Code'), __('Description'), __('Last year'), __('Budget'), __('Actual'), __('Variance')]);

        foreach ($data['lines'] as $line) {
            $id = $line->id;
            fputcsv($out, [
                $line->code,
                ($line->is_header ? strtoupper($line->displayName()) : $line->displayName()),
                $data['priorActual'][$id] ?? 0,
                $data['budgeted'][$id] ?? 0,
                $data['actual'][$id] ?? 0,
                ($data['budgeted'][$id] ?? 0) - ($data['actual'][$id] ?? 0),
            ]);
        }
        foreach (['opening', 'income', 'expenditure', 'closing', 'capital'] as $key) {
            fputcsv($out, ['', strtoupper(__($this->totalLabel($key))), $data['priorTotals'][$key] ?? 0, $data['totals']['budget'][$key], $data['totals']['actual'][$key], $data['totals']['budget'][$key] - $data['totals']['actual'][$key]]);
        }
        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="income-expenditure-'.$budget->fiscal_year.'.csv"',
        ]);
    }

    /** Save the figures typed into the sheet. Only while it is still a draft. */
    public function saveFigures(Request $request, Budget $budget)
    {
        abort_unless($budget->is_institutional, 404);

        if (! $budget->isEditable()) {
            return redirect()->route('admin.budget-sheet.show', $budget)
                ->with('error', __('This sheet is :status, so its figures can no longer be changed. Use a revision instead.', ['status' => str_replace('_', ' ', $budget->status)]));
        }

        $request->validate([
            'opening_balance' => ['nullable', 'numeric'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $headers = BudgetLine::where('is_header', true)->pluck('id')->flip();
        $lines = BudgetLine::postable()->get()->keyBy('id');

        DB::transaction(function () use ($request, $budget, $headers, $lines) {
            $budget->opening_balance = (float) ($request->input('opening_balance') ?: 0);

            foreach ((array) $request->input('amounts', []) as $lineId => $amount) {
                $lineId = (int) $lineId;
                if ($headers->has($lineId) || ! $lines->has($lineId)) {
                    continue;
                }
                $amount = ($amount === null || $amount === '') ? 0.0 : (float) $amount;

                if ($amount == 0.0) {
                    BudgetAllocation::where('budget_id', $budget->id)->where('budget_line_id', $lineId)->delete();

                    continue;
                }

                BudgetAllocation::updateOrCreate(
                    ['budget_id' => $budget->id, 'budget_line_id' => $lineId],
                    ['allocated_amount' => $amount, 'title' => $lines[$lineId]->name, 'period' => 'yearly', 'is_active' => true, 'updated_by' => $request->user()?->id, 'branch_id' => $budget->branch_id],
                );
            }

            $this->refreshTotal($budget);
        });

        return redirect()->route('admin.budget-sheet.show', $budget)->with('success', __('Figures saved.'));
    }

    /** Budget a tuition line from expected pupil numbers at a rate. */
    public function forecast(Request $request, Budget $budget)
    {
        abort_unless($budget->is_institutional, 404);

        if (! $budget->isEditable()) {
            return redirect()->route('admin.budget-sheet.show', $budget)->with('error', __('This sheet is :status, so its figures can no longer be changed. Use a revision instead.', ['status' => str_replace('_', ' ', $budget->status)]));
        }

        $data = $request->validate([
            'budget_line_id' => ['required', 'exists:budget_lines,id'],
            'student_count' => ['required', 'integer', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $line = BudgetLine::findOrFail($data['budget_line_id']);
        $amount = round($data['student_count'] * $data['rate'], 2);

        DB::transaction(function () use ($budget, $line, $data, $amount, $request) {
            BudgetLineForecast::updateOrCreate(
                ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                ['student_count' => $data['student_count'], 'rate' => $data['rate'], 'computed_amount' => $amount, 'note' => $data['note'] ?? null],
            );
            BudgetAllocation::updateOrCreate(
                ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                ['allocated_amount' => $amount, 'title' => $line->name, 'period' => 'yearly', 'is_active' => true, 'updated_by' => $request->user()?->id, 'branch_id' => $budget->branch_id],
            );
            $this->refreshTotal($budget);
        });

        return redirect()->route('admin.budget-sheet.show', $budget)->with('success', __(':code budgeted at :amount — :count pupils at :rate.', [
            'code' => $line->code, 'amount' => number_format($amount), 'count' => $data['student_count'], 'rate' => number_format((float) $data['rate']),
        ]));
    }

    public function updatePeriod(Request $request, Budget $budget)
    {
        abort_unless($budget->is_institutional, 404);

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        $budget->update($data + ['fiscal_year' => $this->label($data['start_date'], $data['end_date'])]);

        return back()->with('success', __('Period updated. The actuals now read from :start to :end.', ['start' => $budget->start_date->format('d M Y'), 'end' => $budget->end_date->format('d M Y')]));
    }

    public function destroy(Budget $budget)
    {
        abort_unless($budget->is_institutional, 404);

        if ($budget->status !== 'draft') {
            return back()->with('error', __('Only a draft sheet can be deleted. Cancel or close this one instead.'));
        }

        $budget->delete();

        return redirect()->route('admin.budget-sheet.index')->with('success', __('Sheet deleted.'));
    }

    // ---------------------------------------------------------------- assembly

    /**
     * Everything the sheet shows, assembled once and shared by the screen,
     * the PDF and the CSV so none of them can drift from the others.
     *
     * @return array<string, mixed>
     */
    private function sheetData(Budget $budget): array
    {
        abort_unless($budget->is_institutional, 404);

        $lines = BudgetLine::sheet();
        $from = $budget->start_date->format('Y-m-d');
        $to = $budget->end_date->format('Y-m-d');

        $budgeted = BudgetAllocation::where('budget_id', $budget->id)->whereNotNull('budget_line_id')
            ->pluck('allocated_amount', 'budget_line_id')->map(fn ($v) => (float) $v)->all();

        $result = $this->actuals->forPeriod($from, $to);
        $this->syncSpent($budget, $result['lines']);

        $actual = BudgetLine::rollUp($lines, $result['lines']);
        $budgetedWithHeaders = BudgetLine::rollUp($lines, $budgeted);

        $previous = $this->previousSheet($budget);
        $priorActual = $previous
            ? BudgetLine::rollUp($lines, $this->actuals->forPeriod($previous->start_date->format('Y-m-d'), $previous->end_date->format('Y-m-d'))['lines'])
            : [];

        return [
            'budget' => $budget,
            'school' => SchoolSetting::current(),
            'lines' => $lines,
            'budgeted' => $budgetedWithHeaders,
            'actual' => $actual,
            'unallocated' => $result['unallocated'],
            'totals' => $this->totals($budget, $lines, $budgeted, $result['lines']),
            'previous' => $previous,
            'priorActual' => $priorActual,
            'priorTotals' => $previous ? $this->totals($previous, $lines, [], $this->actuals->forPeriod($previous->start_date->format('Y-m-d'), $previous->end_date->format('Y-m-d'))['lines'])['actual'] : null,
            'editable' => $budget->isEditable(),
            'groupEnds' => BudgetLine::groupEnds($lines),
            'ledger' => $this->ledgerAgreement($lines, $result['lines'], $from, $to),
            'forecasts' => BudgetLineForecast::where('budget_id', $budget->id)->get()->keyBy('budget_line_id'),
            'forecastRates' => $this->tuition->ratesByLine(),
        ];
    }

    /**
     * Opening, income, expenditure, closing, capital — for both the budget
     * and the actual columns, exactly as the paper lays it out: capital sits
     * below the closing balance and is not deducted from it.
     *
     * @return array{budget: array<string, float>, actual: array<string, float>}
     */
    private function totals(Budget $budget, $lines, array $budgeted, array $actual): array
    {
        $opening = (float) $budget->opening_balance;
        $out = [];

        foreach ([['budget', $budgeted], ['actual', $actual]] as [$key, $figures]) {
            $income = BudgetLine::sectionTotal($lines, $figures, BudgetLine::SECTION_INCOME);
            $expenditure = BudgetLine::sectionTotal($lines, $figures, BudgetLine::SECTION_EXPENDITURE);
            $capital = BudgetLine::sectionTotal($lines, $figures, BudgetLine::SECTION_CAPITAL);

            $out[$key] = [
                'opening' => $opening,
                'income' => $income,
                'expenditure' => $expenditure,
                'closing' => $opening + $income - $expenditure,
                'capital' => $capital,
                'cash' => $opening + $income - $expenditure - $capital,
            ];
        }

        return $out;
    }

    /**
     * Does the sheet agree with the ledger? Income against posted class 7,
     * expenditure against class 6, capital against class 2, for the period.
     * A difference is a mapping gap, said out loud.
     *
     * @return array<string, array{sheet: float, ledger: float, difference: float}>
     */
    private function ledgerAgreement($lines, array $actual, string $from, string $to): array
    {
        $byClass = DB::table('journal_entry_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jl.account_id')
            ->where('je.is_posted', true)
            ->whereDate('je.entry_date', '>=', $from)->whereDate('je.entry_date', '<=', $to)
            ->whereIn('coa.class_number', [2, 6, 7])
            ->selectRaw('coa.class_number, SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->groupBy('coa.class_number')
            ->get()->keyBy('class_number');

        $ledger = [
            'income' => (float) (($byClass[7]->credit ?? 0) - ($byClass[7]->debit ?? 0)),
            'expenditure' => (float) (($byClass[6]->debit ?? 0) - ($byClass[6]->credit ?? 0)),
            'capital' => (float) (($byClass[2]->debit ?? 0) - ($byClass[2]->credit ?? 0)),
        ];

        $out = [];
        foreach ($ledger as $section => $amount) {
            $sheet = BudgetLine::sectionTotal($lines, $actual, $section);
            $out[$section] = ['sheet' => $sheet, 'ledger' => $amount, 'difference' => $sheet - $amount];
        }

        return $out;
    }

    /** Keep each allocation's spent / remaining in step with the real actuals. */
    private function syncSpent(Budget $budget, array $actualByLine): void
    {
        $allocations = BudgetAllocation::where('budget_id', $budget->id)->whereNotNull('budget_line_id')->get();
        $total = 0.0;

        foreach ($allocations as $allocation) {
            $spent = (float) ($actualByLine[$allocation->budget_line_id] ?? 0);
            if ($allocation->budgetLine?->section !== BudgetLine::SECTION_INCOME) {
                $total += $spent;
            }
            if (abs((float) $allocation->spent_amount - $spent) > 0.005) {
                $allocation->forceFill(['spent_amount' => $spent, 'remaining_amount' => (float) $allocation->allocated_amount - $spent])->saveQuietly();
            }
        }

        if (abs((float) $budget->spent_amount - $total) > 0.005) {
            $budget->forceFill(['spent_amount' => $total, 'remaining_amount' => (float) $budget->total_amount - $total])->saveQuietly();
        }
    }

    /** The stored total is expenditure and capital only: income is a forecast, not a pot. */
    private function refreshTotal(Budget $budget): void
    {
        $ids = BudgetLine::whereIn('section', [BudgetLine::SECTION_EXPENDITURE, BudgetLine::SECTION_CAPITAL])->postable()->pluck('id');
        $total = (float) BudgetAllocation::where('budget_id', $budget->id)->whereIn('budget_line_id', $ids)->sum('allocated_amount');
        $allocated = (float) BudgetAllocation::where('budget_id', $budget->id)->whereNotNull('budget_line_id')->sum('allocated_amount');

        $budget->forceFill(['total_amount' => $total, 'allocated_amount' => $allocated, 'remaining_amount' => $total - (float) $budget->spent_amount])->save();
    }

    private function seedFromPrevious(Budget $budget, string $basis, float $uplift): int
    {
        $previous = $this->previousSheet($budget);
        if (! $previous) {
            return 0;
        }

        $source = $basis === 'actual'
            ? $this->actuals->forPeriod($previous->start_date->format('Y-m-d'), $previous->end_date->format('Y-m-d'))['lines']
            : BudgetAllocation::where('budget_id', $previous->id)->whereNotNull('budget_line_id')->pluck('allocated_amount', 'budget_line_id')->map(fn ($v) => (float) $v)->all();

        $postable = BudgetLine::postable()->active()->get()->keyBy('id');
        $multiplier = 1 + $uplift / 100;
        $count = 0;

        foreach ($source as $lineId => $amount) {
            $line = $postable->get((int) $lineId);
            if (! $line || $amount == 0) {
                continue;
            }
            BudgetAllocation::updateOrCreate(
                ['budget_id' => $budget->id, 'budget_line_id' => $line->id],
                ['allocated_amount' => round($amount * $multiplier, 2), 'title' => $line->name, 'period' => 'yearly', 'is_active' => true, 'branch_id' => $budget->branch_id],
            );
            $count++;
        }

        $this->refreshTotal($budget);

        return $count;
    }

    private function previousSheet(Budget $budget): ?Budget
    {
        return Budget::where('is_institutional', true)
            ->where('id', '!=', $budget->id)
            ->whereDate('end_date', '<', $budget->start_date->toDateString())
            ->orderByDesc('end_date')
            ->first();
    }

    private function previousClosingBalance(string $startDate): float
    {
        $previous = Budget::where('is_institutional', true)->whereDate('end_date', '<', $startDate)->orderByDesc('end_date')->first();
        if (! $previous) {
            return 0.0;
        }

        $actual = $this->actuals->forPeriod($previous->start_date->format('Y-m-d'), $previous->end_date->format('Y-m-d'))['lines'];
        $lines = BudgetLine::sheet(false);

        return (float) $previous->opening_balance
            + BudgetLine::sectionTotal($lines, $actual, BudgetLine::SECTION_INCOME)
            - BudgetLine::sectionTotal($lines, $actual, BudgetLine::SECTION_EXPENDITURE);
    }

    private function label(string $start, string $end): string
    {
        $a = Carbon::parse($start)->format('Y');
        $b = Carbon::parse($end)->format('Y');

        return $a === $b ? $a : "$a/$b";
    }

    private function totalLabel(string $key): string
    {
        return match ($key) {
            'opening' => 'Opening balance',
            'income' => 'Total income',
            'expenditure' => 'Total expenditure',
            'closing' => 'Closing balance',
            'capital' => 'Capital expenses',
            default => $key,
        };
    }
}
