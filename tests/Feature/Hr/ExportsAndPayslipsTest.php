<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reference system prints payslips and exports its tax and aging reports;
 * here payroll had no printable payslip at all, and those reports could only be
 * read on screen.
 */
class ExportsAndPayslipsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $this->staff = User::create([
            'first_name' => 'Sam', 'last_name' => 'Staff', 'staff_id' => 'STF001',
            'email' => 'staff@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true, 'basic_salary' => 200000,
        ]);
        $this->staff->branches()->attach($this->branch->id, ['is_default' => true]);
    }

    private function payroll(float $tax = 0, float $employerTax = 0, string $month = '2026-01'): Payroll
    {
        $payroll = Payroll::create([
            'branch_id' => $this->branch->id, 'user_id' => $this->staff->id,
            'basic_salary' => 200000, 'gross_salary' => 210000,
            'total_allowance' => 15000, 'bonus' => 0, 'total_deduction' => 5000,
            'tax' => $tax, 'employer_tax' => $employerTax,
            'net_salary' => 210000 - $tax, 'total_cost' => 210000 + $employerTax,
            'salary_month' => $month, 'status' => Payroll::STATUS_UNPAID,
        ]);

        PayrollDetail::create(['branch_id' => $this->branch->id, 'payroll_id' => $payroll->id, 'title' => 'Housing allowance', 'amount' => 15000, 'status' => 1]);
        PayrollDetail::create(['branch_id' => $this->branch->id, 'payroll_id' => $payroll->id, 'title' => 'Salary advance', 'amount' => 5000, 'status' => 0]);

        return $payroll;
    }

    // ------------------------------------------------------------ payslips

    public function test_a_payslip_renders_as_a_pdf(): void
    {
        $payroll = $this->payroll();

        $this->get(route('admin.payroll.payslip', $payroll))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_payslip_itemises_taxes_only_when_they_match_what_was_withheld(): void
    {
        $calculated = app(\App\Services\TaxCalculationService::class)
            ->calculate(210000, '2026-01-31', $this->staff);

        $matching = $this->payroll($calculated['employee_tax'], $calculated['employer_tax'], '2026-01');
        // Withheld under rules that have since changed.
        $stale = $this->payroll($calculated['employee_tax'] + 5000, $calculated['employer_tax'], '2026-02');

        $slips = app(\App\Http\Controllers\Admin\PayrollController::class)
            ->slipsFor(collect([$matching, $stale]))
            ->keyBy(fn ($s) => $s->payroll->salary_month);

        // A breakdown that contradicted the amount actually withheld would be
        // worse than none, so the stale payslip shows totals only.
        $this->assertTrue($slips['2026-01']->itemised);
        $this->assertFalse($slips['2026-02']->itemised);
        $this->assertSame([], $slips['2026-02']->taxLines);

        $this->get(route('admin.payroll.payslip', $stale))->assertSuccessful();
    }

    public function test_every_payslip_for_a_month_prints_together(): void
    {
        $this->payroll(month: '2026-03');

        $this->get(route('admin.payroll.payslips', ['month' => 3, 'year' => 2026]))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_month_with_no_payroll_says_so(): void
    {
        $this->from(route('admin.payroll.report'))
            ->get(route('admin.payroll.payslips', ['month' => 7, 'year' => 2026]))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_the_payroll_screens_link_to_the_payslips(): void
    {
        $payroll = $this->payroll(month: '2026-04');

        $this->get(route('admin.payroll.report', ['month' => 4, 'year' => 2026]))
            ->assertSuccessful()
            ->assertSee(route('admin.payroll.payslip', $payroll), false);
    }

    // ------------------------------------------------------------ staff tax report

    public function test_the_staff_tax_report_exports(): void
    {
        $csv = $this->get(route('admin.tax-report.export', ['format' => 'csv']))->assertSuccessful()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('STF001', $csv);

        $this->get(route('admin.tax-report.export', ['format' => 'pdf']))
            ->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_staff_tax_export_needs_the_export_permission(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.tax-report.export', ['format' => 'csv']))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ aging

    public function test_every_aging_report_exports(): void
    {
        foreach (['receivables', 'payables', 'student-fees'] as $report) {
            $this->get(route('admin.accounting-reports.aging-export', ['report' => $report, 'format' => 'csv']))
                ->assertSuccessful();

            $this->get(route('admin.accounting-reports.aging-export', ['report' => $report, 'format' => 'pdf']))
                ->assertSuccessful()->assertHeader('content-type', 'application/pdf');
        }

        $this->get(route('admin.accounting-reports.student-fee-aging'))
            ->assertSuccessful()
            ->assertSee(route('admin.accounting-reports.aging-export', ['report' => 'student-fees', 'format' => 'pdf']), false);
    }

    public function test_an_unknown_aging_report_is_not_found(): void
    {
        $this->get(route('admin.accounting-reports.aging-export', ['report' => 'secrets', 'format' => 'csv']))
            ->assertNotFound();
    }
}
