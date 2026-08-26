<?php

namespace App\Services;

use App\Models\TaxGroup;
use App\Models\TaxSetting;
use App\Models\User;

/**
 * The Cameroon multi-pass tax engine. Identical logic must be used by the
 * payslip and the tax-distribution report (guide gotcha #1).
 *
 * Pass 1 — group taxes (step lookup: single applicable bracket).
 * Pass 2 — standalone base taxes (non-dependent).
 * Pass 3 — dependent taxes (computed from a source tax's output, not salary).
 */
class TaxCalculationService
{
    /**
     * @return array{employee_tax: float, employer_tax: float, lines: array<int, array{title:string, employee:float, employer:float, kind:string}>}
     */
    public function calculate(float $grossSalary, string $payDate, ?User $staff = null): array
    {
        $exemptions = $this->exemptionMap($staff);
        $lines = [];
        $results = []; // keyed source results for dependents: "tax_group:ID" / "tax_setting:ID" => emp+employer

        $employeeTax = 0.0;
        $employerTax = 0.0;

        // ── Pass 1: group taxes ──
        foreach (TaxGroup::getEffectiveGroups($payDate) as $group) {
            $bracket = $group->applicableBracket($grossSalary);
            if (! $bracket) {
                continue;
            }
            $ex = $exemptions[$bracket->id] ?? null;
            $emp = $bracket->employeeContribution($grossSalary, $ex);
            $empr = $bracket->employerContribution($grossSalary, $ex);

            $lines[] = ['title' => $group->title, 'employee' => $emp, 'employer' => $empr, 'kind' => 'group'];
            $results['tax_group:' . $group->id] = $emp + $empr;
            $employeeTax += $emp;
            $employerTax += $empr;
        }

        // ── Pass 2: standalone, non-dependent ──
        $standalone = TaxSetting::standalone()->where('is_dependent', false)->effective($payDate)->get();
        foreach ($standalone as $setting) {
            if (! $setting->coversAmount($grossSalary)) {
                continue;
            }
            $ex = $exemptions[$setting->id] ?? null;
            $emp = $setting->employeeContribution($grossSalary, $ex);
            $empr = $setting->employerContribution($grossSalary, $ex);

            $lines[] = ['title' => $setting->tax_title, 'employee' => $emp, 'employer' => $empr, 'kind' => 'standalone'];
            $results['tax_setting:' . $setting->id] = $emp + $empr;
            $employeeTax += $emp;
            $employerTax += $empr;
        }

        // ── Pass 3: dependent taxes (base = source tax output) ──
        $dependents = TaxSetting::where('is_dependent', true)->effective($payDate)->get();
        foreach ($dependents as $setting) {
            $base = $results[$setting->depends_on_type . ':' . $setting->depends_on_id] ?? 0;
            if ($base <= 0) {
                continue;
            }
            $ex = $exemptions[$setting->id] ?? null;
            $emp = $setting->employeeContribution((float) $base, $ex);
            $empr = $setting->employerContribution((float) $base, $ex);

            $lines[] = ['title' => $setting->tax_title, 'employee' => $emp, 'employer' => $empr, 'kind' => 'dependent'];
            $employeeTax += $emp;
            $employerTax += $empr;
        }

        return [
            'employee_tax' => round($employeeTax, 2),
            'employer_tax' => round($employerTax, 2),
            'lines' => $lines,
        ];
    }

    /** @return array<int, \App\Models\StaffTaxExemption> keyed by tax_setting_id */
    private function exemptionMap(?User $staff): array
    {
        if (! $staff) {
            return [];
        }
        return $staff->taxExemptions()->get()->keyBy('tax_setting_id')->all();
    }
}
