<?php

namespace Tests\Feature\Finance;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\RecurringJournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The recurring-entry generator and its daily schedule existed, but there was no
 * screen: a template could only be created by writing database rows. Actions
 * follow the reference system — pause, resume, skip next, run now, run all due,
 * duplicate.
 */
class RecurringEntryScreenTest extends TestCase
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

        $year = now()->year;
        $fy = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY'.$year,
            'start_date' => $year.'-01-01', 'end_date' => $year.'-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);
        foreach (range(1, 12) as $m) {
            AccountingPeriod::create([
                'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
                'name' => 'Month '.$m, 'period_number' => $m,
                'start_date' => sprintf('%d-%02d-01', $year, $m),
                'end_date' => now()->setDate($year, $m, 1)->endOfMonth()->toDateString(),
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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Monthly rent',
            'frequency' => 'monthly',
            'start_date' => today()->toDateString(),
            'auto_post' => '0',
            'lines' => [
                ['account_id' => $this->rent->id, 'debit' => 50000, 'credit' => null],
                ['account_id' => $this->bank->id, 'debit' => null, 'credit' => 50000],
            ],
        ], $overrides);
    }

    private function template(array $attrs = []): RecurringJournalEntry
    {
        $this->post(route('admin.recurring-entries.store'), $this->payload($attrs))->assertSessionHasNoErrors();

        return RecurringJournalEntry::latest('id')->firstOrFail();
    }

    public function test_the_list_and_form_render(): void
    {
        $this->template();

        $this->get(route('admin.recurring-entries.index'))->assertSuccessful()->assertSee('Monthly rent');
        $this->get(route('admin.recurring-entries.create'))->assertSuccessful();
    }

    public function test_a_template_is_created_with_its_lines(): void
    {
        $template = $this->template();

        $this->assertCount(2, $template->lines);
        $this->assertTrue($template->isBalanced());
        $this->assertTrue($template->is_active);
        $this->assertSame(today()->toDateString(), $template->next_run_date->toDateString());
    }

    public function test_an_unbalanced_template_is_refused(): void
    {
        $this->post(route('admin.recurring-entries.store'), $this->payload([
            'lines' => [
                ['account_id' => $this->rent->id, 'debit' => 50000],
                ['account_id' => $this->bank->id, 'credit' => 40000],
            ],
        ]))->assertSessionHasErrors('lines');

        // Refused at save time: an unbalanced template would otherwise fail
        // silently on every run.
        $this->assertSame(0, RecurringJournalEntry::count());
    }

    public function test_a_line_with_both_debit_and_credit_is_refused(): void
    {
        $this->post(route('admin.recurring-entries.store'), $this->payload([
            'lines' => [
                ['account_id' => $this->rent->id, 'debit' => 100, 'credit' => 100],
                ['account_id' => $this->bank->id, 'credit' => 0],
            ],
        ]))->assertSessionHasErrors('lines');
    }

    public function test_the_detail_page_renders(): void
    {
        $template = $this->template();

        $this->get(route('admin.recurring-entries.show', $template))->assertSuccessful()->assertSee('622');
    }

    public function test_run_now_generates_an_entry(): void
    {
        $template = $this->template();

        $this->post(route('admin.recurring-entries.process', $template))->assertSessionHas('success');

        $this->assertSame(1, JournalEntry::where('reference_type', 'recurring_entry')->count());
        $this->assertSame(1, $template->fresh()->runs_generated);
    }

    public function test_run_now_refuses_a_template_that_is_not_due(): void
    {
        $template = $this->template(['start_date' => today()->addMonth()->toDateString()]);

        $this->post(route('admin.recurring-entries.process', $template))->assertSessionHas('error');

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_run_all_due(): void
    {
        $this->template(['title' => 'Rent']);
        $this->template(['title' => 'Insurance']);
        $this->template(['title' => 'Later', 'start_date' => today()->addMonth()->toDateString()]);

        $this->post(route('admin.recurring-entries.process-all'))->assertSessionHas('success');

        $this->assertSame(2, JournalEntry::where('reference_type', 'recurring_entry')->count());
    }

    public function test_pausing_stops_generation(): void
    {
        $template = $this->template();

        $this->post(route('admin.recurring-entries.pause', $template))->assertSessionHas('success');
        $this->post(route('admin.recurring-entries.process-all'));

        $this->assertFalse($template->fresh()->is_active);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_resuming_never_leaves_the_next_run_in_the_past(): void
    {
        $template = $this->template(['start_date' => today()->subMonths(3)->toDateString()]);
        $this->post(route('admin.recurring-entries.pause', $template));

        // Otherwise a template paused for three months would wake up owing
        // three runs and generate them all at once.
        $this->post(route('admin.recurring-entries.resume', $template), [
            'next_run_date' => today()->subMonths(2)->toDateString(),
        ])->assertSessionHas('success');

        $template->refresh();
        $this->assertTrue($template->is_active);
        $this->assertSame(today()->toDateString(), $template->next_run_date->toDateString());
    }

    public function test_skip_next_moves_on_without_generating(): void
    {
        $template = $this->template();

        $this->post(route('admin.recurring-entries.skip-next', $template))->assertSessionHas('success');

        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $template->fresh()->next_run_date->toDateString());
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_skipping_the_last_run_finishes_the_template(): void
    {
        $template = $this->template(['end_date' => today()->addDays(5)->toDateString()]);

        $this->post(route('admin.recurring-entries.skip-next', $template));

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_duplicate_starts_paused_with_no_history(): void
    {
        $template = $this->template();
        $this->post(route('admin.recurring-entries.process', $template));

        $this->post(route('admin.recurring-entries.duplicate', $template))->assertRedirect();

        $copy = RecurringJournalEntry::latest('id')->firstOrFail();
        $this->assertNotSame($template->id, $copy->id);
        $this->assertFalse($copy->is_active);
        $this->assertSame(0, $copy->runs_generated);
        $this->assertCount(2, $copy->lines);
        $this->assertStringContainsString('Monthly rent', $copy->title);
    }

    public function test_editing_a_template_that_has_run_keeps_its_place(): void
    {
        $template = $this->template();
        $this->post(route('admin.recurring-entries.process', $template));
        $next = $template->fresh()->next_run_date->toDateString();

        $this->put(route('admin.recurring-entries.update', $template), $this->payload([
            'title' => 'Monthly rent (revised)',
            'start_date' => today()->subYear()->toDateString(),
        ]))->assertSessionHasNoErrors();

        // Moving the start date must not make it regenerate months it already did.
        $template->refresh();
        $this->assertSame('Monthly rent (revised)', $template->title);
        $this->assertSame($next, $template->next_run_date->toDateString());
    }

    public function test_a_template_that_has_run_cannot_be_deleted(): void
    {
        $template = $this->template();
        $this->post(route('admin.recurring-entries.process', $template));

        $this->delete(route('admin.recurring-entries.destroy', $template))->assertSessionHas('error');

        $this->assertNotNull($template->fresh());
    }

    public function test_a_template_that_never_ran_can_be_deleted(): void
    {
        $template = $this->template();

        $this->delete(route('admin.recurring-entries.destroy', $template))->assertRedirect(route('admin.recurring-entries.index'));

        $this->assertSame(0, RecurringJournalEntry::count());
    }

    public function test_the_screens_need_the_permission(): void
    {
        $template = $this->template();

        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)->get(route('admin.recurring-entries.index'))->assertForbidden();
        $this->actingAs($clerk)->post(route('admin.recurring-entries.process', $template))->assertForbidden();
    }
}
