<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\TaxCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Recomputes taxes under the CURRENT config (a "what-if"), using the same
 * TaxCalculationService as the payslip so the two never disagree (guide #1).
 */
class StaffTaxReportController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'staff-tax-report';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('staff-tax-report.view', ['index']),
            // The reference system exports this report to PDF and Excel; the
            // export permission was seeded here but guarded nothing.
            static::can('staff-tax-report.export', ['export']),
        ];
    }

    public function __construct(private TaxCalculationService $tax)
    {
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        return view('admin.hr.tax.report', $this->report($date) + ['date' => $date]);
    }

    /** The same report as PDF or CSV. */
    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => ['required', 'in:pdf,csv'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $report = $this->report($date);
        $filename = 'staff-tax-report-'.$date;

        if ($validated['format'] === 'pdf') {
            return Pdf::loadView('admin.hr.tax.report-pdf', $report + [
                'date' => $date,
                'school' => SchoolSetting::current(),
                'currency' => SchoolSetting::current()?->currency ?? 'FCFA',
            ])->setPaper('a4', 'portrait')->download($filename.'.pdf');
        }

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [__('Staff ID'), __('Staff'), __('Gross'), __('Employee Tax'), __('Employer Tax'), __('Net'), __('Effective %')]);

            foreach ($report['rows'] as $r) {
                fputcsv($out, [$r->staff->staff_id, $r->staff->full_name, $r->gross, $r->employee_tax, $r->employer_tax, $r->net, $r->effective]);
            }

            fputcsv($out, ['', __('Total'), $report['totals']['gross'], $report['totals']['employee_tax'], $report['totals']['employer_tax'], $report['totals']['net'], '']);

            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{rows: \Illuminate\Support\Collection, totals: array<string, float>, distribution: \Illuminate\Support\Collection} */
    private function report(string $date): array
    {
        $rows = User::staff()->whereNotNull('basic_salary')->where('basic_salary', '>', 0)
            ->orderBy('staff_id')->get()
            ->map(function ($staff) use ($date) {
                $gross = (float) $staff->basic_salary;
                $r = $this->tax->calculate($gross, $date, $staff);
                $effective = $gross > 0 ? round($r['employee_tax'] / $gross * 100, 2) : 0;

                return (object) [
                    'staff' => $staff,
                    'gross' => $gross,
                    'employee_tax' => $r['employee_tax'],
                    'employer_tax' => $r['employer_tax'],
                    'net' => $gross - $r['employee_tax'],
                    'effective' => $effective,
                ];
            });

        $totals = [
            'gross' => $rows->sum('gross'),
            'employee_tax' => $rows->sum('employee_tax'),
            'employer_tax' => $rows->sum('employer_tax'),
            'net' => $rows->sum('net'),
        ];

        // Salary-band distribution
        $bands = [
            ['label' => '0 – 62,000', 'min' => 0, 'max' => 62000],
            ['label' => '62,001 – 100,000', 'min' => 62001, 'max' => 100000],
            ['label' => '100,001 – 200,000', 'min' => 100001, 'max' => 200000],
            ['label' => '200,001 – 500,000', 'min' => 200001, 'max' => 500000],
            ['label' => '500,001 +', 'min' => 500001, 'max' => PHP_INT_MAX],
        ];

        $distribution = collect($bands)->map(function ($b) use ($rows) {
            $inBand = $rows->filter(fn ($r) => $r->gross >= $b['min'] && $r->gross <= $b['max']);

            return (object) [
                'label' => $b['label'],
                'count' => $inBand->count(),
                'employee_tax' => $inBand->sum('employee_tax'),
            ];
        });

        return compact('rows', 'totals', 'distribution');
    }
}
