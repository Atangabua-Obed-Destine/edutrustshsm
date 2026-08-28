<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\RecurringJournalEntry;
use App\Models\User;
use App\Services\RecurringEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringEntryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private ChartOfAccount $rent;
    private ChartOfAccount $bank;

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

        $fy = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);

        foreach (range(1, 6) as $m) {
            AccountingPeriod::create([
                'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
                'name' => 'Month '.$m, 'period_number' => $m,
                'start_date' => sprintf('2026-%02d-01', $m),
                'end_date' => sprintf('2026-%02d-28', $m),
                'is_closed' => false,
            ]);
        }

        $this->rent = ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => '622', 'account_name' => 'Rent',
            'class_number' => 6, 'account_type' => 'detail', 'normal_balance' => 'debit', 'is_active' => true,
        ]);
        $this->bank = ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => '521', 'account_name' => 'Bank',
            'class_number' => 5, 'account_type' => 'detail', 'normal_balance' => 'debit', 'is_active' => true,
        ]);
    }

    private function template(array $attrs = [], bool $balanced = true): RecurringJournalEntry
    {
        $template = RecurringJournalEntry::create(array_merge([
            'branch_id' => $this->branch->id,
            'title' => 'Monthly rent',
            'frequency' => 'monthly',
            'start_date' => '2026-01-01',
            'next_run_date' => '2026-01-01',
            'auto_post' => false,
            'is_active' => true,
        ], $attrs));

        $template->lines()->create([
            'account_id' => $this->rent->id, 'line_number' => 1,
            'debit' => 50000, 'credit' => 0, 'description' => 'Rent',
        ]);
        $template->lines()->create([
            'account_id' => $this->bank->id, 'line_number' => 2,
            'debit' => 0, 'credit' => $balanced ? 50000 : 30000, 'description' => 'Rent',
        ]);

        return $template->fresh('lines');
    }

    private function service(): RecurringEntryService
    {
        return app(RecurringEntryService::class);
    }

    public function test_a_due_template_generates_an_entry(): void
    {
        $this->template();

        $result = $this->service()->runDue('2026-01-15');

        $this->assertSame(1, $result['generated']);
        $this->assertSame(1, JournalEntry::where('reference_type', 'recurring_entry')->count());
    }

    public function test_the_generated_entry_copies_the_template_lines(): void
    {
        $this->template();

        $this->service()->runDue('2026-01-15');
        $entry = JournalEntry::where('reference_type', 'recurring_entry')->with('lines')->firstOrFail();

        $this->assertCount(2, $entry->lines);
        $this->assertEquals(50000, (float) $entry->lines->sum('debit'));
        $this->assertEquals(50000, (float) $entry->lines->sum('credit'));
    }

    public function test_generating_twice_for_the_same_run_does_not_duplicate(): void
    {
        $template = $this->template();

        $this->service()->generate($template, '2026-01-15');
        // Re-running after a failure, or the scheduler firing twice, must not
        // book the same charge again.
        $this->service()->generate($template->fresh('lines'), '2026-01-15');

        $this->assertSame(1, JournalEntry::where('reference_type', 'recurring_entry')->count());
    }

    public function test_the_schedule_advances_by_the_frequency(): void
    {
        $template = $this->template(['frequency' => 'monthly', 'next_run_date' => '2026-01-31']);

        $this->service()->generate($template, '2026-02-01');

        // addMonthNoOverflow: the 31st must not slide into early March.
        $this->assertSame('2026-02-28', $template->fresh()->next_run_date->toDateString());
    }

    public function test_a_quarterly_template_advances_three_months(): void
    {
        $template = $this->template(['frequency' => 'quarterly']);

        $this->service()->generate($template, '2026-01-15');

        $this->assertSame('2026-04-01', $template->fresh()->next_run_date->toDateString());
    }

    public function test_a_template_that_is_not_yet_due_generates_nothing(): void
    {
        $this->template(['next_run_date' => '2026-06-01']);

        $result = $this->service()->runDue('2026-01-15');

        $this->assertSame(0, $result['generated']);
        $this->assertSame(0, JournalEntry::where('reference_type', 'recurring_entry')->count());
    }

    public function test_an_unbalanced_template_is_refused(): void
    {
        $this->template([], balanced: false);

        $result = $this->service()->runDue('2026-01-15');

        $this->assertSame(0, $result['generated']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_one_bad_template_does_not_stop_the_others(): void
    {
        $this->template([], balanced: false);
        $this->template(['title' => 'Good one']);

        $result = $this->service()->runDue('2026-01-15');

        $this->assertSame(1, $result['generated']);
        $this->assertCount(1, $result['errors']);
    }

    public function test_entries_are_left_as_drafts_unless_auto_post_is_set(): void
    {
        $this->template(['auto_post' => false]);

        $this->service()->runDue('2026-01-15');

        $this->assertFalse(JournalEntry::where('reference_type', 'recurring_entry')->firstOrFail()->is_posted);
    }

    public function test_auto_post_posts_the_entry(): void
    {
        $this->template(['auto_post' => true]);

        $this->service()->runDue('2026-01-15');

        $this->assertTrue(JournalEntry::where('reference_type', 'recurring_entry')->firstOrFail()->is_posted);
    }

    public function test_a_template_stops_after_its_end_date(): void
    {
        $template = $this->template(['end_date' => '2026-01-31']);

        $this->service()->generate($template, '2026-01-15');

        // Next run would be February, past the end date, so it deactivates
        // rather than piling up work that will never be wanted.
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_the_command_runs(): void
    {
        $this->template();

        $this->artisan('accounting:recurring-entries', ['--as-of' => '2026-01-15'])->assertSuccessful();

        $this->assertSame(1, JournalEntry::where('reference_type', 'recurring_entry')->count());
    }
}
