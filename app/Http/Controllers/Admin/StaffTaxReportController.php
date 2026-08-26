<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TaxCalculationService;
use Illuminate\Http\Request;

/**
 * Recomputes taxes under the CURRENT config (a "what-if"), using the same
 * TaxCalculationService as the payslip so the two never disagree (guide #1).
 */
class StaffTaxReportController extends Controller
{
    public function __construct(private TaxCalculationService $tax)
    {
    }

    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

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

        return view('admin.hr.tax.report', compact('rows', 'totals', 'distribution', 'date'));
    }
}
