<?php

namespace Tests\Feature\Hr;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountType;
use App\Models\Payroll;
use App\Models\TaxRemittance;
use App\Models\User;
use App\Services\PaymentAccountService;
use App\Services\PayrollAccountingService;
use App\Services\TaxRemittanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Paying a payslip withheld tax into a liability account, and nothing ever
 * cleared it. Behaviour follows the reference system's TaxRemittanceService:
 * owed per authority and salary month, read from the ledger; paid DR liability
 * → CR cash; voided by a reversing entry.
 */
class TaxRemittanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    /** @var array<string, ChartOfAccount> */
    private array $accounts = [];

    private int $staffSeq = 0;

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

        $year = now()->year;
        $fy = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY'.$year,
            'start_date' => $year.'-01-01', 'end_date' => $year.'-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);
        foreach (range(1, 12) as $m) {
            $first = now()->setDate($year, $m, 1);
            AccountingPeriod::create([
                'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
                'name' => $first->format('F Y'), 'period_number' => $m,
                'start_date' => $first->toDateString(), 'end_date' => $first->copy()->endOfMonth()->toDateString(),
                'is_closed' => false,
            ]);
        }

        foreach ([
            ['661', 6, 'debit', 'Salaries'],
            ['641', 6, 'debit', 'Employer Charges'],
            ['441', 4, 'credit', 'Taxes Payable'],
            ['521', 5, 'debit', 'Bank'],
            ['571', 5, 'debit', 'Cash Box'],
        ] as [$code, $class, $normal, $name]) {
            $this->accounts[$code] = ChartOfAccount::create([
                'branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => $name,
                'class_number' => $class, 'account_type' => 'detail', 'account_category' => 'detail',
                'normal_balance' => $normal, 'is_active' => true,
            ]);
        }
    }

    /** A paid payslip for the month, posted to the ledger the way payroll does. */
    private function paidPayroll(string $month, float $employeeTax = 20000, float $employerTax = 10000): Payroll
    {
        $this->staffSeq++;
        $staff = User::create([
            'first_name' => 'Staff', 'last_name' => (string) $this->staffSeq,
            'email' => "staff{$this->staffSeq}@example.test", 'password' => 'password',
            'role' => 'staff', 'is_active' => true, 'basic_salary' => 200000,
        ]);

        $payroll = Payroll::create([
            'branch_id' => $this->branch->id, 'user_id' => $staff->id,
            'basic_salary' => 200000, 'gross_salary' => 200000,
            'tax' => $employeeTax, 'employer_tax' => $employerTax,
            'net_salary' => 200000 - $employeeTax, 'total_cost' => 200000 + $employerTax,
            'salary_month' => $month, 'status' => Payroll::STATUS_PAID,
            'pay_date' => now()->setDate(now()->year, (int) substr($month, 5), 1)->endOfMonth()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);

        app(PayrollAccountingService::class)->createPayrollJournalEntry($payroll);

        return $payroll;
    }

    private function month(int $m): string
    {
        return sprintf('%d-%02d', now()->year, $m);
    }

    private function service(): TaxRemittanceService
    {
        return app(TaxRemittanceService::class);
    }

    /** @return array<string, mixed> */
    private function rowFor(string $month): array
    {
        return collect($this->service()->outstanding())->firstWhere('month', $month) ?? [];
    }

    /** @param array<string, mixed> $overrides */
    private function pay(string $month, float $amount, array $overrides = []): TaxRemittance
    {
        return $this->service()->record(array_merge([
            'liability_account_id' => $this->accounts['441']->id,
            'salary_month' => $month,
            'amount' => $amount,
            'payment_date' => today()->toDateString(),
            'source_account_id' => $this->accounts['521']->id,
            'reference' => 'DGI-001',
        ], $overrides));
    }

    // ------------------------------------------------------------ what is owed

    public function test_withheld_tax_is_owed_per_month_from_the_ledger(): void
    {
        $this->paidPayroll($this->month(1));
        $this->paidPayroll($this->month(1));

        $row = $this->rowFor($this->month(1));

        // Two payslips × (20,000 employee + 10,000 employer).
        $this->assertEquals(60000, $row['due']);
        $this->assertEquals(40000, $row['employee']);
        $this->assertEquals(20000, $row['employer']);
        $this->assertSame(2, $row['staff_count']);
        $this->assertSame('undeclared', $row['status']);
    }

    public function test_months_are_listed_oldest_first(): void
    {
        $this->paidPayroll($this->month(3));
        $this->paidPayroll($this->month(1));

        $months = array_column($this->service()->outstanding(), 'month');

        $this->assertSame([$this->month(1), $this->month(3)], $months);
    }

    public function test_an_unpaid_payroll_is_no_longer_owed(): void
    {
        $payroll = $this->paidPayroll($this->month(2));

        app(PayrollAccountingService::class)->reversePayrollJournalEntry($payroll);

        // The reversal shares the payroll reference, so the month nets out.
        $this->assertSame([], $this->rowFor($this->month(2)));
    }

    // ------------------------------------------------------------ paying

    public function test_paying_clears_the_month_and_the_liability(): void
    {
        $this->paidPayroll($this->month(1));

        $remittance = $this->pay($this->month(1), 30000);

        $this->assertNotNull($remittance->journal_entry_id);
        $this->assertSame('settled', $this->rowFor($this->month(1))['status']);
        $this->assertEquals(0.0, $this->service()->ledgerBalance($this->accounts['441']->id));

        // DR liability → CR bank.
        $lines = JournalEntry::findOrFail($remittance->journal_entry_id)->lines;
        $this->assertEquals(30000, (float) $lines->firstWhere('account_id', $this->accounts['441']->id)->debit);
        $this->assertEquals(30000, (float) $lines->firstWhere('account_id', $this->accounts['521']->id)->credit);
    }

    public function test_a_part_payment_leaves_the_rest_owed(): void
    {
        $this->paidPayroll($this->month(1));

        $this->pay($this->month(1), 10000);

        $row = $this->rowFor($this->month(1));
        $this->assertSame('part_paid', $row['status']);
        $this->assertEquals(20000, $row['outstanding']);
    }

    public function test_tax_cannot_be_paid_from_a_non_cash_account(): void
    {
        $this->paidPayroll($this->month(1));

        // Paying from an expense account would post the cost a second time.
        $this->expectExceptionMessage('cash or bank account');
        $this->pay($this->month(1), 30000, ['source_account_id' => $this->accounts['661']->id]);
    }

    public function test_tax_is_remitted_against_a_liability_account(): void
    {
        $this->paidPayroll($this->month(1));

        $this->expectExceptionMessage('liability account');
        $this->pay($this->month(1), 30000, ['liability_account_id' => $this->accounts['661']->id]);
    }

    public function test_a_second_payment_for_the_month_needs_confirming(): void
    {
        $this->paidPayroll($this->month(1));
        $this->pay($this->month(1), 10000);

        try {
            $this->pay($this->month(1), 5000);
            $this->fail('A second payment for the same month was accepted without confirmation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already been paid', $e->getMessage());
        }

        $this->pay($this->month(1), 5000, ['allow_additional' => true]);
        $this->assertEquals(15000, $this->rowFor($this->month(1))['paid']);
    }

    public function test_overpaying_needs_confirming(): void
    {
        $this->paidPayroll($this->month(1));

        try {
            $this->pay($this->month(1), 35000);
            $this->fail('An overpayment was accepted without confirmation.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('more than is outstanding', $e->getMessage());
        }

        $this->pay($this->month(1), 35000, ['allow_overpayment' => true]);
        $this->assertSame('overpaid', $this->rowFor($this->month(1))['status']);
    }

    // ------------------------------------------------------------ voiding

    public function test_voiding_reverses_the_entry_and_the_month_is_owed_again(): void
    {
        $this->paidPayroll($this->month(1));
        $remittance = $this->pay($this->month(1), 30000);

        $voided = $this->service()->void($remittance, 'Paid to the wrong office');

        $this->assertTrue($voided->isVoided());
        $this->assertNotNull($voided->void_journal_entry_id);
        $this->assertSame('undeclared', $this->rowFor($this->month(1))['status']);
        $this->assertEquals(30000.0, $this->service()->ledgerBalance($this->accounts['441']->id));
        // The row stays: it is the audit trail.
        $this->assertSame(1, TaxRemittance::count());

        // And the month can be paid properly now.
        $this->pay($this->month(1), 30000);
        $this->assertSame('settled', $this->rowFor($this->month(1))['status']);
    }

    public function test_a_payment_cannot_be_voided_twice(): void
    {
        $this->paidPayroll($this->month(1));
        $remittance = $this->pay($this->month(1), 30000);
        $this->service()->void($remittance, 'First void');

        $this->expectExceptionMessage('already been voided');
        $this->service()->void($remittance->fresh(), 'Second void');
    }

    // ------------------------------------------------------------ treasury

    public function test_a_named_payment_account_moves_with_the_ledger(): void
    {
        $this->paidPayroll($this->month(1));

        $type = PaymentAccountType::firstOrCreate(['slug' => 'bank'], ['branch_id' => $this->branch->id, 'title' => 'Bank', 'status' => true]);
        $account = PaymentAccount::create([
            'branch_id' => $this->branch->id, 'title' => 'Main Bank', 'account_type_id' => $type->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => true,
        ]);
        app(PaymentAccountService::class)->credit($account, 100000, ['reference_type' => 'deposit']);

        $remittance = $this->pay($this->month(1), 30000, ['payment_account_id' => $account->id]);
        $this->assertEquals(70000, (float) $account->fresh()->current_balance);

        $this->service()->void($remittance, 'Bounced transfer');
        $this->assertEquals(100000, (float) $account->fresh()->current_balance);
    }

    // ------------------------------------------------------------ reconciliation

    public function test_a_hand_typed_entry_against_the_liability_is_flagged(): void
    {
        $this->paidPayroll($this->month(1));

        $this->assertTrue(collect($this->service()->reconciliation())->every(fn ($r) => $r['agrees']));

        // Someone clears 5,000 of the tax account by hand, outside remittances.
        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id, 'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => today()->toDateString(), 'journal_type' => 'general', 'description' => 'manual',
            'fiscal_year_id' => FiscalYear::active()->id, 'accounting_period_id' => AccountingPeriod::forDate(today()->toDateString())?->id,
        ]);
        $entry->lines()->create(['account_id' => $this->accounts['441']->id, 'line_number' => 1, 'debit' => 5000, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $this->accounts['571']->id, 'line_number' => 2, 'debit' => 0, 'credit' => 5000]);
        $entry->post();

        $check = collect($this->service()->reconciliation())->firstWhere('account_id', $this->accounts['441']->id);
        $this->assertFalse($check['agrees']);
        $this->assertEquals(-5000.0, $check['difference']);
    }

    // ------------------------------------------------------------ the screen

    public function test_the_screen_records_and_voids(): void
    {
        $this->paidPayroll($this->month(1));

        $this->get(route('admin.tax-remittances.index'))->assertSuccessful()->assertSee('441');

        $this->post(route('admin.tax-remittances.store'), [
            'liability_account_id' => $this->accounts['441']->id,
            'salary_month' => $this->month(1),
            'amount' => 30000,
            'payment_date' => today()->toDateString(),
            'source_account_id' => $this->accounts['521']->id,
            'reference' => 'DGI-4471',
        ])->assertRedirect(route('admin.tax-remittances.index'))->assertSessionHas('success');

        $remittance = TaxRemittance::firstOrFail();
        $this->get(route('admin.tax-remittances.index'))->assertSee('DGI-4471');

        $this->post(route('admin.tax-remittances.void', $remittance), ['void_reason' => 'Wrong office'])
            ->assertSessionHas('success');

        $this->assertTrue($remittance->fresh()->isVoided());
    }

    public function test_the_screen_reports_a_refusal(): void
    {
        $this->paidPayroll($this->month(1));

        $this->post(route('admin.tax-remittances.store'), [
            'liability_account_id' => $this->accounts['441']->id,
            'salary_month' => $this->month(1),
            'amount' => 99999,
            'payment_date' => today()->toDateString(),
            'source_account_id' => $this->accounts['521']->id,
        ])->assertSessionHas('error');

        $this->assertSame(0, TaxRemittance::count());
    }

    public function test_the_screen_needs_the_permission(): void
    {
        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)->get(route('admin.tax-remittances.index'))->assertForbidden();
    }
}
