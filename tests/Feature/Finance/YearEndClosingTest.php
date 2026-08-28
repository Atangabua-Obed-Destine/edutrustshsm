<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\YearEndClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closing a fiscal year used to flip a flag and nothing else, so profit-and-loss
 * balances ran forever and the year's result never reached equity.
 */
class YearEndClosingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private FiscalYear $year;
    private ChartOfAccount $cash;
    private ChartOfAccount $tuition;
    private ChartOfAccount $salaries;
    private ChartOfAccount $summary;
    private ChartOfAccount $retained;

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

        $this->year = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);

        AccountingPeriod::create([
            'branch_id' => $this->branch->id, 'fiscal_year_id' => $this->year->id,
            'name' => 'January 2026', 'period_number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'is_closed' => false,
        ]);

        $this->cash = $this->account('521', 5, 'debit', 'Bank');
        $this->tuition = $this->account('706', 7, 'credit', 'Tuition Revenue');
        $this->salaries = $this->account('661', 6, 'debit', 'Salaries');
        $this->summary = $this->account('120', 1, 'credit', 'Income Summary');
        $this->retained = $this->account('110', 1, 'credit', 'Retained Earnings');
    }

    private function account(string $code, int $class, string $normal, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code,
            'account_name' => $name, 'class_number' => $class,
            'account_type' => 'detail', 'normal_balance' => $normal, 'is_active' => true,
        ]);
    }

    /** Post a simple two-line entry inside the year. */
    private function postEntry(ChartOfAccount $debit, ChartOfAccount $credit, float $amount, string $date = '2026-01-15'): void
    {
        $period = AccountingPeriod::where('fiscal_year_id', $this->year->id)->first();

        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id,
            'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => $date,
            'fiscal_year_id' => $this->year->id,
            'accounting_period_id' => $period->id,
            'journal_type' => 'general',
            'description' => 'Test entry',
        ]);
        $entry->lines()->create(['account_id' => $debit->id, 'line_number' => 1, 'debit' => $amount, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $credit->id, 'line_number' => 2, 'debit' => 0, 'credit' => $amount]);
        $entry->post();
    }

    private function closeEverything(): void
    {
        AccountingPeriod::where('fiscal_year_id', $this->year->id)->update(['is_closed' => true]);
    }

    private function service(): YearEndClosingService
    {
        return app(YearEndClosingService::class);
    }

    public function test_the_preview_reports_revenue_expenses_and_net(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);   // revenue
        $this->postEntry($this->salaries, $this->cash, 60000);   // expense

        $preview = $this->service()->preview($this->year);

        $this->assertEquals(100000, $preview['revenue']);
        $this->assertEquals(60000, $preview['expenses']);
        $this->assertEquals(40000, $preview['net']);
    }

    public function test_closing_zeroes_the_profit_and_loss_accounts(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->postEntry($this->salaries, $this->cash, 60000);
        $this->closeEverything();

        $this->service()->close($this->year);

        // After closing, class 6 and 7 must carry nothing forward.
        $this->assertEqualsWithDelta(0, (float) $this->tuition->postedBalance(), 0.01);
        $this->assertEqualsWithDelta(0, (float) $this->salaries->postedBalance(), 0.01);
    }

    public function test_the_net_result_lands_in_retained_earnings(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->postEntry($this->salaries, $this->cash, 60000);
        $this->closeEverything();

        $this->service()->close($this->year);

        $this->assertEqualsWithDelta(40000, (float) $this->retained->postedBalance(), 0.01);
        // Income summary is a conduit; it must end empty.
        $this->assertEqualsWithDelta(0, (float) $this->summary->postedBalance(), 0.01);
    }

    public function test_a_loss_debits_retained_earnings(): void
    {
        $this->postEntry($this->cash, $this->tuition, 40000);
        $this->postEntry($this->salaries, $this->cash, 100000);
        $this->closeEverything();

        $this->service()->close($this->year);

        // A 60,000 loss reduces equity.
        $this->assertEqualsWithDelta(-60000, (float) $this->retained->postedBalance(), 0.01);
    }

    public function test_the_closing_entry_balances(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->postEntry($this->salaries, $this->cash, 60000);
        $this->closeEverything();

        $entry = $this->service()->close($this->year);

        $this->assertEqualsWithDelta(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit'),
            0.01
        );
        $this->assertTrue($entry->is_posted);
        $this->assertSame('closing', $entry->journal_type);
    }

    public function test_the_year_is_marked_closed(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->closeEverything();

        $this->service()->close($this->year);

        $this->year->refresh();
        $this->assertTrue($this->year->is_closed);
        $this->assertFalse($this->year->is_active);
    }

    public function test_a_year_with_open_periods_cannot_be_closed(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        // Period deliberately left open.

        $this->expectExceptionMessage('All periods must be closed');
        $this->service()->close($this->year);
    }

    public function test_a_year_with_unposted_entries_cannot_be_closed(): void
    {
        $period = AccountingPeriod::where('fiscal_year_id', $this->year->id)->first();
        JournalEntry::create([
            'branch_id' => $this->branch->id,
            'entry_number' => 'JE-2026-9000', 'entry_date' => '2026-01-15',
            'fiscal_year_id' => $this->year->id, 'accounting_period_id' => $period->id,
            'journal_type' => 'general', 'description' => 'Draft', 'is_posted' => false,
        ]);
        $this->closeEverything();

        $this->expectExceptionMessage('All entries in the year must be posted');
        $this->service()->close($this->year);
    }

    public function test_an_already_closed_year_cannot_be_closed_again(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->closeEverything();
        $this->service()->close($this->year);

        $this->expectExceptionMessage('already closed');
        $this->service()->close($this->year->fresh());
    }

    public function test_a_year_with_nothing_posted_is_refused(): void
    {
        $this->closeEverything();

        $this->expectExceptionMessage('nothing to close');
        $this->service()->close($this->year);
    }

    public function test_reopening_reverses_the_closing_entry(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->postEntry($this->salaries, $this->cash, 60000);
        $this->closeEverything();

        $this->service()->close($this->year);
        $this->assertEqualsWithDelta(40000, (float) $this->retained->postedBalance(), 0.01);

        $this->service()->reopen($this->year->fresh());

        // Equity is restored and the P&L balances come back.
        $this->assertEqualsWithDelta(0, (float) $this->retained->postedBalance(), 0.01);
        $this->assertEqualsWithDelta(100000, (float) $this->tuition->postedBalance(), 0.01);
        $this->assertFalse($this->year->fresh()->is_closed);
    }

    public function test_the_preview_screen_renders(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);

        $this->get(route('admin.fiscal-years.closing', $this->year))
            ->assertSuccessful()
            ->assertSee('Tuition Revenue', false);
    }

    public function test_closing_through_the_screen_reports_the_entry(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        $this->closeEverything();

        $this->post(route('admin.fiscal-years.close', $this->year))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, JournalEntry::where('journal_type', 'closing')->count());
    }

    public function test_a_failed_close_reports_the_reason_rather_than_erroring(): void
    {
        $this->postEntry($this->cash, $this->tuition, 100000);
        // Periods left open on purpose.

        $this->post(route('admin.fiscal-years.close', $this->year))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($this->year->fresh()->is_closed);
        $this->assertSame(0, JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('journal_type', 'closing'))->count());
    }
}
