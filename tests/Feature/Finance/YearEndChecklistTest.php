<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\RecurringJournalEntry;
use App\Models\User;
use App\Models\YearEndClosing;
use App\Services\YearEndChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Closing a year was one button: it posted the closing entry and left no record
 * of what was checked, who closed it, or why it was later reopened. The
 * reference system gates closing behind a checklist and keeps that record.
 */
class YearEndChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private FiscalYear $year;
    private ChartOfAccount $cash;
    private ChartOfAccount $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

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
        $this->account('120', 1, 'credit', 'Income Summary');
        $this->account('110', 1, 'credit', 'Retained Earnings');
    }

    private function account(string $code, int $class, string $normal, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => $name,
            'class_number' => $class, 'account_type' => 'detail', 'account_category' => 'detail',
            'normal_balance' => $normal, 'is_active' => true,
        ]);
    }

    private function postRevenue(float $amount = 100000, bool $post = true): JournalEntry
    {
        $entry = JournalEntry::create([
            'branch_id' => $this->branch->id, 'entry_number' => JournalEntry::generateEntryNumber(),
            'entry_date' => '2026-01-15', 'fiscal_year_id' => $this->year->id,
            'accounting_period_id' => AccountingPeriod::first()->id,
            'journal_type' => 'general', 'description' => 'Fees',
        ]);
        $entry->lines()->create(['account_id' => $this->cash->id, 'line_number' => 1, 'debit' => $amount, 'credit' => 0]);
        $entry->lines()->create(['account_id' => $this->tuition->id, 'line_number' => 2, 'debit' => 0, 'credit' => $amount]);

        if ($post) {
            $entry->post();
        }

        return $entry;
    }

    private function closePeriods(): void
    {
        AccountingPeriod::where('fiscal_year_id', $this->year->id)->update(['is_closed' => true]);
    }

    private function confirmAll(): void
    {
        foreach (array_keys(YearEndChecklistService::MANUAL) as $item) {
            $this->post(route('admin.fiscal-years.closing.confirm', $this->year), ['item' => $item, 'confirmed' => 1]);
        }
    }

    /** @return array<string, bool> */
    private function automatic(): array
    {
        return collect(app(YearEndChecklistService::class)->automatic($this->year->fresh()))
            ->mapWithKeys(fn ($c) => [$c['key'] => $c['passed']])->all();
    }

    // ------------------------------------------------------------ automatic checks

    public function test_open_periods_fail_the_check(): void
    {
        $this->postRevenue();

        $this->assertFalse($this->automatic()['periods_closed']);

        $this->closePeriods();
        $this->assertTrue($this->automatic()['periods_closed']);
    }

    public function test_a_draft_entry_fails_the_check(): void
    {
        $this->postRevenue(post: false);

        $this->assertFalse($this->automatic()['entries_posted']);
    }

    public function test_a_recurring_entry_left_due_fails_the_check(): void
    {
        RecurringJournalEntry::create([
            'branch_id' => $this->branch->id, 'title' => 'Rent', 'frequency' => 'monthly',
            'start_date' => '2026-12-01', 'next_run_date' => '2026-12-01', 'is_active' => true,
        ]);

        $this->assertFalse($this->automatic()['recurring_generated']);
    }

    public function test_a_clean_year_passes_every_automatic_check(): void
    {
        $this->postRevenue();
        $this->closePeriods();

        $this->assertSame([true], array_values(array_unique($this->automatic())));
    }

    // ------------------------------------------------------------ the gate

    public function test_the_year_cannot_close_until_every_confirmation_is_made(): void
    {
        $this->postRevenue();
        $this->closePeriods();

        $this->post(route('admin.fiscal-years.close', $this->year))->assertSessionHas('error');
        $this->assertFalse($this->year->fresh()->is_closed);

        $this->confirmAll();

        $this->post(route('admin.fiscal-years.close', $this->year))->assertSessionHas('success');
        $this->assertTrue($this->year->fresh()->is_closed);
    }

    public function test_a_failing_automatic_check_blocks_closing_even_when_confirmed(): void
    {
        $this->postRevenue();
        $this->confirmAll();
        // Periods left open.

        $this->post(route('admin.fiscal-years.close', $this->year))->assertSessionHas('error');
        $this->assertFalse($this->year->fresh()->is_closed);
    }

    public function test_confirmations_record_who_made_them_and_can_be_undone(): void
    {
        $this->post(route('admin.fiscal-years.closing.confirm', $this->year), ['item' => 'cash_counted', 'confirmed' => 1]);

        $closing = YearEndClosing::currentFor($this->year);
        $this->assertSame(YearEndClosing::STATUS_IN_PROGRESS, $closing->status);
        $this->assertSame($this->admin->id, $closing->confirmations['cash_counted']['by']);
        $this->assertSame($this->admin->id, $closing->started_by);

        $this->post(route('admin.fiscal-years.closing.confirm', $this->year), ['item' => 'cash_counted', 'confirmed' => 0]);
        $this->assertFalse($closing->fresh()->isConfirmed('cash_counted'));
    }

    // ------------------------------------------------------------ the record

    public function test_closing_records_the_result_and_who_closed_it(): void
    {
        $this->postRevenue(250000);
        $this->closePeriods();
        $this->confirmAll();

        $this->post(route('admin.fiscal-years.close', $this->year));

        $closing = YearEndClosing::firstOrFail();
        $this->assertSame(YearEndClosing::STATUS_CLOSED, $closing->status);
        $this->assertEquals(250000, (float) $closing->net_result);
        $this->assertSame($this->admin->id, $closing->closed_by);
        $this->assertNotNull($closing->closing_entry_id);
    }

    public function test_reopening_needs_a_reason_and_keeps_the_history(): void
    {
        $this->postRevenue();
        $this->closePeriods();
        $this->confirmAll();
        $this->post(route('admin.fiscal-years.close', $this->year));

        $this->post(route('admin.fiscal-years.reopen', $this->year), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertTrue($this->year->fresh()->is_closed);

        $this->post(route('admin.fiscal-years.reopen', $this->year), ['reason' => 'Late supplier invoice'])->assertSessionHas('success');

        $this->assertFalse($this->year->fresh()->is_closed);
        $closing = YearEndClosing::firstOrFail();
        $this->assertSame(YearEndClosing::STATUS_REVERSED, $closing->status);
        $this->assertSame('Late supplier invoice', $closing->reversal_reason);

        // Closing again starts a fresh checklist rather than reusing the old one.
        $this->assertNull(YearEndClosing::currentFor($this->year->fresh()));
    }

    public function test_the_closing_screen_shows_the_checklist(): void
    {
        $this->postRevenue();

        $this->get(route('admin.fiscal-years.closing', $this->year))
            ->assertSuccessful()
            ->assertSee(__('Every accounting period is closed'))
            ->assertSee(__('Cash on hand counted and agreed to the cash accounts'));
    }
}
