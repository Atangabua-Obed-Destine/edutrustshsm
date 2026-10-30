<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\BudgetSummaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A budget that sees the money by itself.
 *
 * Budgets only ever counted expenses someone tagged by hand, and the expense
 * form never offered the tag — so every budget read zero spent, forever. Now
 * the line is derived from the category and the date, spending from before
 * activation is taken in, the month is summarised the way the paper form is,
 * and a new year can start from the last one.
 */
class BudgetFlowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private ExpenseCategory $chalk;
    private ExpenseCategory $fuel;
    private Budget $budget;
    private BudgetAllocation $chalkLine;

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

        $this->chalk = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Teaching materials', 'slug' => 'teaching', 'status' => true]);
        $this->fuel = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Fuel', 'slug' => 'fuel', 'status' => true]);

        $this->budget = $this->makeBudget('2025/2026', '2025-09-01', '2026-07-31', 'active');
        $this->chalkLine = $this->line($this->budget, $this->chalk, 120000);
    }

    private function makeBudget(string $year, string $start, string $end, string $status): Budget
    {
        return Budget::create([
            'branch_id' => $this->branch->id, 'title' => "School budget {$year}", 'type' => 'annual',
            'fiscal_year' => $year, 'start_date' => $start, 'end_date' => $end,
            'total_amount' => 500000, 'status' => $status, 'created_by' => $this->admin->id,
        ]);
    }

    private function line(Budget $budget, ExpenseCategory $category, float $amount): BudgetAllocation
    {
        $line = BudgetAllocation::create([
            'branch_id' => $this->branch->id, 'budget_id' => $budget->id,
            'expense_category_id' => $category->id, 'title' => $category->title,
            'allocated_amount' => $amount, 'remaining_amount' => $amount, 'period' => 'yearly',
        ]);
        $budget->calculateAllocatedAmount();

        return $line;
    }

    private function recordExpense(ExpenseCategory $category, string $date, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.account.expense.store'), [
            'category_id' => $category->id, 'title' => 'Boxes of chalk',
            'amount' => $amount, 'date' => $date,
        ]);
    }

    // ------------------------------------------------------------ matching

    public function test_an_expense_lands_on_its_budget_line_by_itself(): void
    {
        $this->recordExpense($this->chalk, '2025-10-10', 15000)->assertRedirect();

        $expense = Expense::firstOrFail();
        $this->assertSame($this->budget->id, $expense->budget_id);
        $this->assertSame($this->chalkLine->id, $expense->budget_allocation_id);

        $this->assertEquals(15000, (float) $this->chalkLine->fresh()->spent_amount);
        $this->assertEquals(15000, (float) $this->budget->fresh()->spent_amount);
    }

    public function test_an_expense_outside_the_period_or_with_no_line_stays_untagged(): void
    {
        $this->recordExpense($this->chalk, '2025-08-10', 15000);  // before the year
        $this->recordExpense($this->fuel, '2025-10-10', 15000);   // no line for fuel

        $this->assertSame(0, Expense::whereNotNull('budget_id')->count());
        $this->assertEquals(0, (float) $this->budget->fresh()->spent_amount);
    }

    public function test_a_derived_line_never_refuses_money_already_spent(): void
    {
        // Twice the allocation. Recording it is a fact; the budget shows the overrun.
        $this->recordExpense($this->chalk, '2025-10-10', 250000)->assertSessionHas('success');

        $this->assertEquals(250000, (float) $this->chalkLine->fresh()->spent_amount);
        $this->assertTrue($this->chalkLine->fresh()->utilization_percentage > 100);
    }

    public function test_editing_the_date_moves_the_expense_off_the_budget(): void
    {
        $this->recordExpense($this->chalk, '2025-10-10', 15000);
        $expense = Expense::firstOrFail();

        $this->put(route('admin.account.expense.update', $expense), [
            'category_id' => $this->chalk->id, 'title' => 'Boxes of chalk',
            'amount' => 15000, 'date' => '2025-08-10',
        ])->assertRedirect();

        $this->assertNull($expense->fresh()->budget_id);
        $this->assertEquals(0, (float) $this->chalkLine->fresh()->spent_amount);
    }

    public function test_activating_a_budget_takes_in_what_was_already_spent(): void
    {
        $next = $this->makeBudget('2026/2027', '2026-09-01', '2027-07-31', 'approved');
        $this->line($next, $this->fuel, 90000);

        Expense::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->fuel->id,
            'title' => 'Diesel', 'amount' => 20000, 'date' => '2026-09-15', 'status' => true,
        ]);

        $this->post(route('admin.budget.activate', $next))
            ->assertSessionHas('success', __('Budget activated. :count expenses already recorded in its period have been taken in.', ['count' => 1]));

        $this->assertEquals(20000, (float) $next->fresh()->spent_amount);
        $this->assertSame($next->id, Expense::where('title', 'Diesel')->firstOrFail()->budget_id);
    }

    // ------------------------------------------------------------ the month

    public function test_the_month_is_summarised_the_way_the_paper_form_is(): void
    {
        $this->recordExpense($this->chalk, '2025-09-10', 10000);
        $this->recordExpense($this->chalk, '2025-10-05', 25000);
        $this->recordExpense($this->chalk, '2025-10-20', 5000);
        $this->recordExpense($this->fuel, '2025-10-11', 7000);   // no line: outside

        $summary = app(BudgetSummaryService::class)->monthly($this->budget, Carbon::parse('2025-10-01'));

        $line = $summary['lines'][0];
        $this->assertSame(120000.0, $line['budget']);
        $this->assertSame(30000.0, $line['monthly']);
        $this->assertSame(10000.0, $line['brought']);
        $this->assertSame(40000.0, $line['cumulative']);
        $this->assertSame(80000.0, $line['balance']);

        $this->assertCount(1, $summary['outside']);
        $this->assertSame('Fuel', $summary['outside'][0]['heading']);
        $this->assertSame(7000.0, $summary['outside'][0]['cumulative']);
    }

    public function test_the_first_month_brings_nothing_forward(): void
    {
        $this->recordExpense($this->chalk, '2025-09-10', 10000);

        $summary = app(BudgetSummaryService::class)->monthly($this->budget, Carbon::parse('2025-09-01'));

        $this->assertSame(0.0, $summary['lines'][0]['brought']);
        $this->assertSame(10000.0, $summary['lines'][0]['cumulative']);
    }

    // ------------------------------------------------------------ pacing

    public function test_pacing_says_whether_spending_is_ahead_of_the_year(): void
    {
        $service = app(BudgetSummaryService::class);

        // Half the year gone (Jan 15 of a Sep–Jul year ≈ 41%), nothing spent.
        $this->budget->update(['spent_amount' => 0]);
        $this->assertSame('good', $service->pacing($this->budget, Carbon::parse('2026-01-15'))['status']);

        // 41% of the year gone, 70% of the money spent.
        $this->budget->update(['spent_amount' => 350000]);
        $pacing = $service->pacing($this->budget, Carbon::parse('2026-01-15'));
        $this->assertSame('critical', $pacing['status']);
        $this->assertGreaterThan(500000, $pacing['projected']);

        // No total: nothing to pace against.
        $this->budget->update(['total_amount' => 0]);
        $this->assertSame('none', $service->pacing($this->budget, Carbon::parse('2026-01-15'))['status']);
    }

    // ------------------------------------------------------------ carrying forward

    public function test_a_new_budget_can_start_from_last_year_s_actual_spend_with_an_uplift(): void
    {
        $this->recordExpense($this->chalk, '2025-10-10', 80000);
        $this->line($this->budget, $this->fuel, 50000); // planned, never spent

        $this->post(route('admin.budget.store'), [
            'title' => 'School budget 2026/2027', 'type' => 'annual', 'fiscal_year' => '2026/2027',
            'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'total_amount' => 0,
            'seed_from' => 'actual', 'seed_budget_id' => $this->budget->id, 'uplift_percent' => 10,
        ])->assertRedirect();

        $next = Budget::where('fiscal_year', '2026/2027')->firstOrFail();

        // Only the line that spent is carried, at 80,000 + 10%.
        $this->assertCount(1, $next->allocations);
        $this->assertEquals(88000, (float) $next->allocations->first()->allocated_amount);
        $this->assertSame($this->chalk->id, $next->allocations->first()->expense_category_id);
        // The envelope grew to hold the lines.
        $this->assertEquals(88000, (float) $next->total_amount);
        $this->assertSame('draft', $next->status);
    }

    public function test_a_new_budget_can_copy_last_year_s_plan(): void
    {
        $this->line($this->budget, $this->fuel, 50000);

        $this->post(route('admin.budget.store'), [
            'title' => 'School budget 2026/2027', 'type' => 'annual', 'fiscal_year' => '2026/2027',
            'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'total_amount' => 900000,
            'seed_from' => 'budget', 'seed_budget_id' => $this->budget->id,
        ])->assertRedirect();

        $next = Budget::where('fiscal_year', '2026/2027')->firstOrFail();

        $this->assertEqualsCanonicalizing([120000.0, 50000.0], $next->allocations->map(fn ($a) => (float) $a->allocated_amount)->all());
        // A total already big enough is left alone.
        $this->assertEquals(900000, (float) $next->total_amount);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_screens_render(): void
    {
        $this->recordExpense($this->chalk, '2025-10-10', 15000);

        $this->get(route('admin.budget.show', $this->budget))
            ->assertSuccessful()
            ->assertSee(__('Monthly Summary'))
            ->assertSee(__('Year'));

        $this->get(route('admin.budget.monthly', ['budget' => $this->budget, 'month' => '2025-10']))
            ->assertSuccessful()
            ->assertSee('Teaching materials')
            ->assertSee(__('Brought forward'));

        $this->get(route('admin.budget.create'))
            ->assertSuccessful()
            ->assertSee(__('Start from last year'));
    }
}
