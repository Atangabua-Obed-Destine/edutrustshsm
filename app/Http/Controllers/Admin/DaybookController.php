<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DaybookExport;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Budget;
use App\Models\SchoolSetting;
use App\Services\BookConfidence;
use App\Services\DaybookService;
use App\Services\FeeCollectionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cash book, one month at a time, and the year it adds up to.
 */
class DaybookController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'daybook';

    public function __construct(private DaybookService $daybook) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('daybook.view', ['year', 'summary', 'analysis']),
            static::can('daybook.export', ['pdf', 'csv', 'excel', 'analysisPdf']),
        ];
    }

    /** The book for a month. */
    public function index(Request $request)
    {
        return view('admin.daybook.index', $this->monthData($request));
    }

    /** The old address of the year view, which Trends & Analysis replaced. */
    public function year(Request $request)
    {
        return redirect()->route('admin.daybook.analysis', $request->only('month'));
    }

    /**
     * Trends & Analysis: the year read for a meeting, as the reference system
     * has it. Where the cash stood, whether spending runs ahead of the year,
     * who has paid, and whether the figures can be trusted.
     */
    public function analysis(Request $request)
    {
        return view('admin.daybook.analysis', $this->analysisData($request));
    }

    /**
     * The meeting pack: every chart on the page, with its numbers, on the
     * letterhead. The charts arrive as images the page drew, because the PDF
     * renderer runs no JavaScript.
     */
    public function analysisPdf(Request $request)
    {
        $request->validate(['charts' => ['array'], 'charts.*' => ['nullable', 'string', 'max:3000000']]);

        $charts = collect($request->input('charts', []))
            ->only(['cash', 'monthly', 'pacing'])
            ->filter(fn ($image) => is_string($image) && str_starts_with($image, 'data:image/png;base64,'))
            ->all();

        $data = $this->analysisData($request) + ['charts' => $charts, 'school' => SchoolSetting::current()];

        return Pdf::loadView('admin.daybook.analysis-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->download('trends-and-analysis-'.$data['year']['start']->format('Y-m').'.pdf');
    }

    /** The book as a workbook: the month, the year, and the month against the sheet. */
    public function excel(Request $request)
    {
        $data = $this->monthData($request);
        $sheet = $this->sheetFor($data['month'], $data['month']->copy()->endOfMonth());

        return Excel::download(new DaybookExport(
            school: SchoolSetting::current(),
            month: $data['month'],
            book: $data['book'],
            yearLabel: $data['year']['label'],
            year: $this->daybook->analysis($data['year']['start'], $data['year']['end'], $this->sheetFor($data['year']['start'], $data['year']['end'])),
            sheet: $sheet,
            summary: $sheet ? $this->daybook->monthlySummary($sheet, $data['month']) : null,
        ), 'cash-book-'.$data['month']->format('Y-m').'.xlsx');
    }

    /** The month against the Income & Expenditure sheet: Budget · Monthly · B/F · Cumulative · Balance. */
    public function summary(Request $request)
    {
        $sheets = \App\Models\Budget::where('is_institutional', true)->orderByDesc('start_date')->get();
        $month = $this->monthFrom($request);

        $budget = $request->integer('budget')
            ? $sheets->firstWhere('id', $request->integer('budget'))
            : ($sheets->first(fn ($s) => $s->start_date->lte($month->copy()->endOfMonth()) && $s->end_date->gte($month)) ?? $sheets->first());

        if ($budget && ! $request->has('month')) {
            $month = now()->between($budget->start_date, $budget->end_date) ? now()->startOfMonth() : $budget->start_date->copy()->startOfMonth();
        }
        if ($budget) {
            $month = $month->max($budget->start_date->copy()->startOfMonth())->min($budget->end_date->copy()->startOfMonth());
        }

        return view('admin.daybook.summary', [
            'sheets' => $sheets,
            'budget' => $budget,
            'month' => $month,
            'months' => $budget ? $this->daybook->monthsFor($budget) : [],
            'summary' => $budget ? $this->daybook->monthlySummary($budget, $month) : null,
        ]);
    }

    /** The month as a document, for signing and filing. */
    public function pdf(Request $request)
    {
        $data = $this->monthData($request) + ['school' => SchoolSetting::current()];

        return Pdf::loadView('admin.daybook.pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download('cash-book-'.$data['month']->format('Y-m').'.pdf');
    }

    /** The month as a sheet to work in. */
    public function csv(Request $request): StreamedResponse
    {
        $data = $this->monthData($request);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Date'), __('Reference'), __('Description'), __('Party'), __('Method'), __('Heading'), __('In'), __('Out'), __('Balance')]);
            fputcsv($out, [$data['month']->copy()->startOfMonth()->toDateString(), '', __('Balance brought forward'), '', '', '', '', '', number_format($data['book']['brought_forward'], 2, '.', '')]);

            foreach ($data['book']['rows'] as $row) {
                fputcsv($out, [
                    $row['date'], $row['ref'], $row['description'], $row['party'] ?? '', $row['method'] ?? '', $row['heading'],
                    $row['direction'] === DaybookService::IN ? number_format($row['amount'], 2, '.', '') : '',
                    $row['direction'] === DaybookService::OUT ? number_format($row['amount'], 2, '.', '') : '',
                    number_format($row['balance'], 2, '.', ''),
                ]);
            }

            fputcsv($out, ['', '', __('Totals'), '', '', '', number_format($data['book']['total_in'], 2, '.', ''), number_format($data['book']['total_out'], 2, '.', ''), number_format($data['book']['closing'], 2, '.', '')]);
            fclose($out);
        }, 'cash-book-'.$data['month']->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function monthData(Request $request): array
    {
        $month = $this->monthFrom($request);
        $year = $this->daybook->yearFor($month);

        return [
            'month' => $month,
            'year' => $year,
            'book' => $this->daybook->book($month, $year['start']),
            'currency' => SchoolSetting::current()?->currency ?? 'FCFA',
            'previous' => $month->copy()->subMonth()->format('Y-m'),
            'next' => $month->copy()->addMonth()->format('Y-m'),
        ];
    }

    /** @return array<string, mixed> */
    private function analysisData(Request $request): array
    {
        $year = $this->daybook->yearFor($this->monthFrom($request));
        $sheet = $this->sheetFor($year['start'], $year['end']);
        $analysis = $this->daybook->analysis($year['start'], $year['end'], $sheet);

        $session = AcademicSession::query()
            ->whereDate('start_date', '<=', $year['end'])
            ->whereDate('end_date', '>=', $year['start'])
            ->orderByDesc('start_date')
            ->first();

        $checks = app(BookConfidence::class)->checks($year['start'], $year['end'], $sheet, $analysis['unanalysed']);

        return [
            'year' => $year,
            'sheet' => $sheet,
            'analysis' => $analysis,
            'collection' => app(FeeCollectionService::class)->forSession($session),
            'checks' => $checks,
            'concerns' => collect($checks)->where('ok', false)->count(),
            'currency' => SchoolSetting::current()?->currency ?? 'FCFA',
            'previousYear' => $year['start']->copy()->subDay()->format('Y-m'),
            'nextYear' => $year['end']->copy()->addDay()->format('Y-m'),
        ];
    }

    /** The Income & Expenditure sheet that covers a stretch of time, latest first. */
    private function sheetFor(Carbon $from, Carbon $to): ?Budget
    {
        return Budget::where('is_institutional', true)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->orderByDesc('start_date')
            ->first();
    }

    private function monthFrom(Request $request): Carbon
    {
        $month = (string) $request->input('month', '');

        return preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth()
            : now()->startOfMonth();
    }
}
