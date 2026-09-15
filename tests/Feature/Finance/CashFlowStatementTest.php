<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\CashFlowStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The OHADA cash flow statement, which the reference system has and this one
 * did not. Every non-cash account is sorted into exactly one line, so the
 * statement reconciles to the cash accounts by construction.
 */
class CashFlowStatementTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private FiscalYear $fy;
    private FiscalYear $priorFy;

    /** @var array<string, ChartOfAccount> */
    private array $accounts = [];

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

        $this->priorFy = $this->year('FY2025', '2025-01-01', '2025-12-31', false);
        $this->fy = $this->year('FY2026', '2026-01-01', '2026-12-31', true);

        foreach ([
            ['571', 5, 'debit', 'Cash Box'],
            ['521', 5, 'debit', 'Bank'],
            ['706', 7, 'credit', 'Fee Revenue'],
            ['661', 6, 'debit', 'Salaries'],
            ['681', 6, 'debit', 'Depreciation Expense'],
            ['411', 4, 'debit', 'Students Receivable'],
            ['401', 4, 'debit', 'Suppliers'],
            ['244', 2, 'debit', 'Furniture'],
            ['284', 2, 'credit', 'Accumulated Depreciation'],
            ['162', 1, 'credit', 'Bank Loan'],
            ['101', 1, 'credit', 'Capital'],
            ['131', 1, 'credit', 'Net Result'],
        ] as [$code, $class, $normal, $name]) {
            $this->accounts[$code] = ChartOfAccount::create([
                'branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => $name,
                'class_number' => $class, 'account_type' => 'detail', 'account_category' => 'detail',
                'normal_balance' => $normal, 'is_active' => true,
            ]);
        }
    }

    private function year(string $name, string $start, string $end, bool $active): FiscalYear
    {
        $fy = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => $name,
            'start_date' => $start, 'end_date' => $end,
            'is_active' => $active, 'is_closed' => false,
        ]);

        foreach (range(1, 12) as $m) {
            $first = \Illuminate\Support\Carbon::parse($start)->addMonthsNoOverflow($m - 1)->startOfMonth();
            AccountingPeriod::create([
                'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
                'name' => $first->format('F Y'), 'period_number' => $m,
                'start_date' => $first->toDateString(), 'end_date' => $first->copy()->endOfMonth()->toDateString(),
                'is_closed' => false,
            ]);
        }

        return $fy;
    }

    /** Post a balanced two-line entry: DR $debit / CR $credit. */
    private function postEntry(string $date, string $debit, string $credit, float $amount): void
    {
        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id,
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $date,
            'fiscal_year_id' => FiscalYear::whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->value('id'),
            'accounting_period_id' => AccountingPeriod::forDate($date)?->id,
            'journal_type' => 'general',
            'description' => "DR {$debit} / CR {$credit}",
        ]);

        $entry->lines()->create(['account_id' => $this->accounts[$debit]->id, 'line_number' => 1, 'debit' => $amount, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $this->accounts[$credit]->id, 'line_number' => 2, 'debit' => 0, 'credit' => $amount]);

        $entry->post();
    }

    /** @param array<string, mixed> $statement */
    private function line(array $statement, string $section, string $key): float
    {
        return (float) (collect($statement['sections'][$section]['items'])->firstWhere('key', $key)['amount'] ?? 0);
    }

    private function service(): CashFlowStatementService
    {
        return app(CashFlowStatementService::class);
    }

    /** A realistic year: capital in, fees earned and partly collected, a loan, furniture, salaries, depreciation. */
    private function aYearOfActivity(): void
    {
        $this->postEntry('2026-01-02', '521', '101', 1000000);  // capital injected
        $this->postEntry('2026-02-10', '411', '706', 800000);   // fees billed
        $this->postEntry('2026-02-20', '571', '411', 600000);   // 600k collected, 200k still owed
        $this->postEntry('2026-03-01', '521', '162', 500000);   // loan received
        $this->postEntry('2026-03-15', '244', '521', 300000);   // furniture bought
        $this->postEntry('2026-04-30', '661', '571', 250000);   // salaries paid
        $this->postEntry('2026-05-05', '401', '521', 0.01);     // trivial supplier prepayment
        $this->postEntry('2026-12-31', '681', '284', 60000);    // depreciation — no cash
    }

    public function test_the_statement_reconciles_to_the_cash_accounts(): void
    {
        $this->aYearOfActivity();

        $s = $this->service()->statement('2026-01-01', '2026-12-31');

        $this->assertTrue($s['balanced']);
        $this->assertEquals(0.0, $s['opening_cash']);
        // Cash: +1,000,000 +600,000 +500,000 −300,000 −250,000 −0.01
        $this->assertEquals(1549999.99, $s['closing_cash']);
        $this->assertEquals($s['closing_cash'], $s['opening_cash'] + $s['net_change']);
    }

    public function test_operating_uses_the_indirect_method(): void
    {
        $this->aYearOfActivity();

        $s = $this->service()->statement('2026-01-01', '2026-12-31');

        // Result: 800,000 fees − 250,000 salaries − 60,000 depreciation.
        $this->assertEquals(490000.0, $this->line($s, 'operating', 'net_result'));
        // Depreciation moved no cash, so it is added back.
        $this->assertEquals(60000.0, $this->line($s, 'operating', 'depreciation'));
        // 200,000 of fees billed but not collected.
        $this->assertEquals(-200000.0, $this->line($s, 'operating', 'customers'));
        $this->assertEquals(350000.0 - 0.01, $s['sections']['operating']['total']);
    }

    public function test_investing_and_financing(): void
    {
        $this->aYearOfActivity();

        $s = $this->service()->statement('2026-01-01', '2026-12-31');

        $this->assertEquals(-300000.0, $this->line($s, 'investing', 'acquisitions'));
        // Accumulated depreciation is not an investing flow.
        $this->assertEquals(0.0, $this->line($s, 'investing', 'disposals'));
        $this->assertEquals(500000.0, $this->line($s, 'financing', 'borrowings'));
        $this->assertEquals(1000000.0, $this->line($s, 'financing', 'equity'));
    }

    public function test_a_period_including_the_closing_entry_still_shows_its_result(): void
    {
        $this->postEntry('2026-02-10', '571', '706', 100000);
        $this->postEntry('2026-03-10', '661', '571', 40000);
        // Year-end closing moves the result out of 6/7 and into 13.
        $this->postEntry('2026-12-31', '706', '661', 40000);
        $this->postEntry('2026-12-31', '706', '131', 60000);

        $s = $this->service()->statement('2026-01-01', '2026-12-31');

        $this->assertEquals(60000.0, $this->line($s, 'operating', 'net_result'));
        $this->assertEquals(0.0, $this->line($s, 'financing', 'equity'), 'the result is not financing');
        $this->assertTrue($s['balanced']);
    }

    public function test_opening_cash_carries_over_from_before_the_period(): void
    {
        $this->postEntry('2025-06-01', '521', '101', 400000);
        $this->postEntry('2026-02-01', '571', '706', 50000);

        $s = $this->service()->statement('2026-01-01', '2026-12-31');

        $this->assertEquals(400000.0, $s['opening_cash']);
        $this->assertEquals(450000.0, $s['closing_cash']);
        $this->assertEquals(50000.0, $s['net_change']);
        $this->assertTrue($s['balanced']);
    }

    public function test_draft_entries_are_ignored(): void
    {
        $this->postEntry('2026-02-01', '571', '706', 50000);

        $draft = JournalEntry::create([
            'branch_id' => $this->branch->id, 'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => '2026-02-02', 'journal_type' => 'general', 'description' => 'draft',
        ]);
        $draft->lines()->create(['account_id' => $this->accounts['571']->id, 'line_number' => 1, 'debit' => 999, 'credit' => 0]);
        $draft->lines()->create(['account_id' => $this->accounts['706']->id, 'line_number' => 2, 'debit' => 0, 'credit' => 999]);

        $s = $this->service()->statement('2026-01-01', '2026-12-31');

        $this->assertEquals(50000.0, $s['closing_cash']);
    }

    public function test_the_comparative_statement_shows_variances(): void
    {
        $this->postEntry('2025-03-01', '571', '706', 100000);
        $this->postEntry('2026-03-01', '571', '706', 150000);

        $c = $this->service()->comparative('2026-01-01', '2026-12-31', '2025-01-01', '2025-12-31');

        $this->assertEquals(150000.0, $this->line($c['current'], 'operating', 'net_result'));
        $this->assertEquals(100000.0, $this->line($c['previous'], 'operating', 'net_result'));
        $this->assertEquals(50000.0, $c['variance']['operating']['net_result']);
        $this->assertEquals(50000.0, $c['variance']['operating']['_total']);
    }

    public function test_a_line_only_in_the_previous_period_still_appears(): void
    {
        $this->postEntry('2025-03-01', '521', '162', 200000);  // loan last year only
        $this->postEntry('2026-03-01', '571', '706', 10000);

        $c = $this->service()->comparative('2026-01-01', '2026-12-31', '2025-01-01', '2025-12-31');

        $this->assertTrue(collect($c['current']['sections']['financing']['items'])->contains('key', 'borrowings'));
        $this->assertEquals(-200000.0, $c['variance']['financing']['borrowings']);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_screens_render_with_the_active_fiscal_year_by_default(): void
    {
        $this->aYearOfActivity();

        $this->get(route('admin.accounting-reports.cash-flow-statement'))
            ->assertSuccessful()
            ->assertSee(__('Cash flows from operating activities'))
            ->assertViewHas('report', fn ($r) => $r['from'] === '2026-01-01' && $r['balanced']);

        $this->get(route('admin.accounting-reports.comparative-cash-flow'))
            ->assertSuccessful()
            // The fiscal year before the active one is the comparison.
            ->assertViewHas('report', fn ($r) => $r['previous']['from'] === '2025-01-01');

        $this->get(route('admin.accounting-reports.index'))->assertSuccessful()->assertSee(__('Cash Flow Statement'));
    }

    public function test_explicit_dates_override_the_fiscal_year(): void
    {
        $this->aYearOfActivity();

        $this->get(route('admin.accounting-reports.cash-flow-statement', ['start_date' => '2026-03-01', 'end_date' => '2026-03-31']))
            ->assertSuccessful()
            ->assertViewHas('report', fn ($r) => $r['from'] === '2026-03-01' && $r['net_change'] == 200000.0);
    }

    public function test_the_csv_export(): void
    {
        $this->aYearOfActivity();

        $csv = $this->get(route('admin.accounting-reports.cash-flow-export', ['format' => 'csv']))
            ->assertSuccessful()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(__('Acquisition of fixed assets'), $csv);
        $this->assertStringContainsString('-300000', $csv);
    }

    public function test_the_pdf_exports_render(): void
    {
        $this->aYearOfActivity();

        $this->get(route('admin.accounting-reports.cash-flow-export', ['format' => 'pdf']))
            ->assertSuccessful()->assertHeader('content-type', 'application/pdf');

        $this->get(route('admin.accounting-reports.cash-flow-export', ['format' => 'pdf', 'comparative' => 1]))
            ->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_report_needs_the_permission(): void
    {
        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)->get(route('admin.accounting-reports.cash-flow-statement'))->assertForbidden();
    }
}
