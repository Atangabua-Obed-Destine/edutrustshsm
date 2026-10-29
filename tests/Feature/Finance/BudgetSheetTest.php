<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Models\ClassSection;
use App\Models\DefaultAccountMapping;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Payroll;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\BudgetActualsService;
use App\Services\BudgetLineTemplate;
use App\Services\DaybookService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Income & Expenditure sheet, its lines, and the book that ties to it.
 *
 * Three things are being guaranteed: money reaches a line only through a
 * category (or payroll), money that cannot be placed is reported and never
 * dropped, and the itemised cash book analysed by line agrees with the
 * sheet summed by line.
 */
class BudgetSheetTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $session;
    private Term $term;
    private ExpenseCategory $fuel;
    private IncomeCategory $gifts;
    private FeeCategory $tuition;
    private FeeCategory $pta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary']);

        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);

        $this->fuel = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Fuel', 'slug' => 'fuel', 'status' => true]);
        $this->gifts = IncomeCategory::create(['branch_id' => $this->branch->id, 'title' => 'Donations', 'slug' => 'donations', 'status' => true]);
        $this->tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition Fee', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        $this->pta = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'PTA Levy', 'code' => 'PTA', 'is_tuition' => false, 'is_active' => true, 'is_mandatory' => true]);
    }

    private function template(): void
    {
        app(BudgetLineTemplate::class)->load();
    }

    private function line(string $code): BudgetLine
    {
        return BudgetLine::where('code', $code)->firstOrFail();
    }

    private function point(string $type, int $categoryId, string $code): void
    {
        DefaultAccountMapping::updateOrCreate(['mapping_type' => $type, 'category_id' => $categoryId], ['budget_line_id' => $this->line($code)->id, 'status' => 'active']);
    }

    private function sheet(string $start = '2025-09-01', string $end = '2026-07-31', string $status = 'draft'): Budget
    {
        return Budget::create([
            'branch_id' => $this->branch->id, 'title' => "I&E $start", 'type' => 'annual', 'is_institutional' => true,
            'fiscal_year' => substr($start, 0, 4), 'start_date' => $start, 'end_date' => $end,
            'total_amount' => 0, 'opening_balance' => 0, 'status' => $status, 'created_by' => $this->admin->id,
        ]);
    }

    private function pupilIn(string $level, string $formName): StudentEnrollment
    {
        $form = Form::firstOrCreate(['name' => $formName], ['branch_id' => $this->branch->id, 'short_name' => substr($formName, 0, 4), 'level' => $level, 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $section = ClassSection::firstOrCreate(['form_id' => $form->id, 'section' => 'A'], ['branch_id' => $this->branch->id, 'name' => $formName.'A', 'max_students' => 40, 'is_active' => true]);
        $batch = Batch::firstOrCreate(['name' => '2025'], ['branch_id' => $this->branch->id, 'shortcode' => '25', 'is_active' => true]);
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT), 'batch_id' => $batch->id, 'first_name' => 'Ada', 'last_name' => 'Pupil', 'date_of_birth' => '2018-04-11', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);

        return StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id, 'term_id' => $this->term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);
    }

    /** A verified receipt settling the given fees on the given date. @param array<int, array{0: FeeCategory, 1: float}> $fees */
    private function receipt(StudentEnrollment $enrollment, string $date, array $fees, float $extra = 0): Payment
    {
        $total = array_sum(array_column($fees, 1)) + $extra;
        $payment = Payment::create(['branch_id' => $this->branch->id, 'receipt_number' => 'R'.Payment::count() + 1, 'student_enrollment_id' => $enrollment->id, 'amount' => $total, 'payment_method' => 'cash', 'payment_date' => $date, 'verification_status' => 'verified', 'received_by' => $this->admin->id]);
        foreach ($fees as [$category, $amount]) {
            $fee = StudentFee::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'fee_category_id' => $category->id, 'original_amount' => $amount, 'net_amount' => $amount, 'paid_amount' => $amount, 'balance' => 0, 'status' => 'paid']);
            PaymentAllocation::create(['branch_id' => $this->branch->id, 'payment_id' => $payment->id, 'student_fee_id' => $fee->id, 'amount' => $amount]);
        }

        return $payment;
    }

    // ------------------------------------------------------------ the lines

    public function test_the_standard_sheet_loads_once_and_is_two_levels_deep(): void
    {
        $this->post(route('admin.budget-line.template'))->assertSessionHas('success');
        $count = BudgetLine::count();
        $this->assertGreaterThan(60, $count);

        $this->post(route('admin.budget-line.template'));
        $this->assertSame($count, BudgetLine::count(), 'create-only: a second load adds nothing');

        $this->assertSame($this->line('400')->id, $this->line('401')->parent_id);
        $this->assertNull($this->line('400')->parent_id);
        $this->assertNull($this->line('590')->parent_id, 'a top-level line stands on its own');
        $this->assertSame('nursery', $this->line('610')->school_level);
        $this->assertTrue($this->line('442')->is_payroll);

        $this->get(route('admin.budget-line.index'))->assertSuccessful()->assertSee('ADMINISTRATION')->assertSee(__('no category points here yet'));
    }

    public function test_a_category_is_pointed_at_a_line_and_can_be_created_from_it(): void
    {
        $this->template();

        $this->post(route('admin.budget-line.link', $this->line('461')), ['type' => 'expense', 'category_id' => $this->fuel->id])->assertSessionHas('success');
        $this->assertSame($this->line('461')->id, DefaultAccountMapping::where('mapping_type', 'expense')->where('category_id', $this->fuel->id)->value('budget_line_id'));

        // An expense category cannot feed an income line.
        $this->post(route('admin.budget-line.link', $this->line('621')), ['type' => 'expense', 'category_id' => $this->fuel->id])->assertSessionHas('error');

        $this->post(route('admin.budget-line.category', $this->line('404')), ['title' => 'Stationery'])->assertSessionHas('success');
        $created = ExpenseCategory::where('title', 'Stationery')->firstOrFail();
        $this->assertSame($this->line('404')->id, DefaultAccountMapping::where('mapping_type', 'expense')->where('category_id', $created->id)->value('budget_line_id'));

        $this->get(route('admin.budget-line.index'))->assertSuccessful()->assertSee('Stationery')->assertDontSee('Fuel · '.__('Expense category'));
    }

    public function test_a_line_in_use_is_retired_not_deleted_and_a_heading_with_lines_is_kept(): void
    {
        $this->template();
        $this->point('expense', $this->fuel->id, '461');

        $this->delete(route('admin.budget-line.destroy', $this->line('461')))->assertSessionHas('error');
        $this->assertNotNull($this->line('461'));

        $this->post(route('admin.budget-line.toggle', $this->line('460')))->assertSessionHas('error');
        $this->assertTrue($this->line('460')->status);

        $this->post(route('admin.budget-line.toggle', $this->line('461')))->assertSessionHas('success');
        $this->assertFalse($this->line('461')->status);

        $this->delete(route('admin.budget-line.destroy', $this->line('489')))->assertSessionHas('success');
        $this->assertNull(BudgetLine::where('code', '489')->first());
    }

    public function test_dragging_reorders_and_refiles_a_line(): void
    {
        $this->template();
        $fuel = $this->line('461');
        $admin = $this->line('400');

        $this->postJson(route('admin.budget-line.reorder'), [
            'section' => 'expenditure',
            'lines' => [
                ['id' => $admin->id, 'parent_id' => null],
                ['id' => $fuel->id, 'parent_id' => $admin->id],
                ['id' => $this->line('401')->id, 'parent_id' => $admin->id],
            ],
        ])->assertOk();

        $this->assertSame($admin->id, $fuel->fresh()->parent_id);
        $this->assertLessThan($this->line('401')->sort_order, $fuel->fresh()->sort_order);
    }

    // ------------------------------------------------------------ where money lands

    public function test_money_reaches_a_line_through_its_category_and_the_rest_is_reported(): void
    {
        $this->template();
        $this->point('expense', $this->fuel->id, '461');
        $this->point('income', $this->gifts->id, '621');
        $this->point('fee_payment', $this->pta->id, '615');
        $unmapped = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Mystery', 'slug' => 'mystery', 'status' => true]);

        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'title' => 'Diesel', 'amount' => 20000, 'date' => '2025-10-10', 'status' => true]);
        // One expense re-filed by hand off its category's line.
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'budget_line_id' => $this->line('463')->id, 'title' => 'Bus diesel', 'amount' => 5000, 'date' => '2025-10-11', 'status' => true]);
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $unmapped->id, 'title' => 'Unknown', 'amount' => 7000, 'date' => '2025-10-12', 'status' => true]);
        Income::create(['branch_id' => $this->branch->id, 'category_id' => $this->gifts->id, 'title' => 'Gift', 'amount' => 100000, 'date' => '2025-10-01', 'status' => true]);

        $nursery = $this->pupilIn('nursery', 'Nursery 1');
        $primary = $this->pupilIn('primary', 'Primary 3');
        $this->receipt($nursery, '2025-10-05', [[$this->tuition, 50000], [$this->pta, 5000]]);
        $this->receipt($primary, '2025-10-06', [[$this->tuition, 80000]], extra: 3000);

        Payroll::create(['branch_id' => $this->branch->id, 'user_id' => $this->admin->id, 'basic_salary' => 100000, 'gross_salary' => 120000, 'net_salary' => 110000, 'total_cost' => 125000, 'salary_month' => '2025-10', 'pay_date' => '2025-10-31', 'status' => Payroll::STATUS_PAID]);

        $result = app(BudgetActualsService::class)->forPeriod('2025-09-01', '2026-07-31');
        $lines = $result['lines'];

        $this->assertEquals(20000, $lines[$this->line('461')->id]);
        $this->assertEquals(5000, $lines[$this->line('463')->id], 'a line set on the row wins over the category');
        $this->assertEquals(100000, $lines[$this->line('621')->id]);
        $this->assertEquals(50000, $lines[$this->line('610')->id], 'nursery tuition by the pupil\'s level');
        $this->assertEquals(80000, $lines[$this->line('611')->id], 'primary tuition by the pupil\'s level');
        $this->assertEquals(5000, $lines[$this->line('615')->id]);
        $this->assertEquals(125000, $lines[$this->line('442')->id], 'salaries at full cost on the payroll line');

        $this->assertEquals(7000, $result['unallocated']['expenses']);
        $this->assertEquals(3000, $result['unallocated']['fees'], 'cash beyond any fee is reported, not dropped');
    }

    public function test_the_book_analysed_by_line_agrees_with_the_sheet(): void
    {
        $this->template();
        $this->point('expense', $this->fuel->id, '461');
        $this->point('fee_payment', $this->pta->id, '615');

        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'title' => 'Diesel', 'amount' => 20000, 'date' => '2025-10-10', 'status' => true]);
        $pupil = $this->pupilIn('primary', 'Primary 3');
        $this->receipt($pupil, '2025-10-05', [[$this->tuition, 50000], [$this->pta, 5000]], extra: 1000);

        $daybook = app(DaybookService::class);
        $rows = $daybook->analyse($daybook->rows('2025-10-01', '2025-10-31'));

        $byLine = [];
        $unanalysed = 0.0;
        foreach ($rows as $row) {
            foreach ($row['lines'] as $key => $amount) {
                if ($key === '') {
                    $unanalysed += $amount;
                } else {
                    $byLine[(int) $key] = ($byLine[(int) $key] ?? 0) + $amount;
                }
            }
        }

        $sheet = app(BudgetActualsService::class)->forPeriod('2025-10-01', '2025-10-31');

        $this->assertEquals($sheet['lines'], $byLine, 'the book and the sheet are the same money');
        $this->assertEquals(array_sum($sheet['unallocated']), $unanalysed);
        $this->assertEquals(1000, $unanalysed);

        $this->get(route('admin.daybook.index', ['month' => '2025-10']))->assertSuccessful()->assertSee('611')->assertSee(__('none'));
    }

    // ------------------------------------------------------------ the sheet

    public function test_a_sheet_is_started_filled_and_locked_once_it_leaves_draft(): void
    {
        $this->template();

        $this->post(route('admin.budget-sheet.store'), ['title' => 'I&E 2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31'])->assertRedirect();
        $sheet = Budget::where('is_institutional', true)->firstOrFail();
        $this->assertSame('draft', $sheet->status);

        // Overlap refused.
        $this->post(route('admin.budget-sheet.store'), ['title' => 'Again', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])->assertSessionHas('error');

        $this->post(route('admin.budget-sheet.figures', $sheet), [
            'opening_balance' => 250000,
            'amounts' => [$this->line('461')->id => 300000, $this->line('621')->id => 1000000, $this->line('400')->id => 999999],
        ])->assertRedirect();

        $this->assertEquals(300000, BudgetAllocation::where('budget_id', $sheet->id)->where('budget_line_id', $this->line('461')->id)->value('allocated_amount'));
        $this->assertNull(BudgetAllocation::where('budget_id', $sheet->id)->where('budget_line_id', $this->line('400')->id)->first(), 'a figure against a heading is ignored');
        $this->assertEquals(300000, (float) $sheet->fresh()->total_amount, 'the stored total is expenditure only');
        $this->assertEquals(250000, (float) $sheet->fresh()->opening_balance);

        $this->get(route('admin.budget-sheet.show', $sheet))->assertSuccessful()
            ->assertSee(__('Save figures'))->assertSee('1,000,000')->assertSee(__('Closing balance'));

        $sheet->update(['status' => 'active']);
        $this->post(route('admin.budget-sheet.figures', $sheet), ['amounts' => [$this->line('461')->id => 1]])->assertSessionHas('error');
        $this->assertEquals(300000, BudgetAllocation::where('budget_id', $sheet->id)->where('budget_line_id', $this->line('461')->id)->value('allocated_amount'));

        $this->get(route('admin.budget.show', $sheet))->assertRedirect(route('admin.budget-sheet.show', $sheet));
    }

    public function test_the_sheet_shows_actuals_variance_and_what_it_cannot_place(): void
    {
        $this->template();
        $this->point('expense', $this->fuel->id, '461');
        $sheet = $this->sheet();
        BudgetAllocation::create(['branch_id' => $this->branch->id, 'budget_id' => $sheet->id, 'budget_line_id' => $this->line('461')->id, 'title' => 'Fuel', 'allocated_amount' => 100000, 'period' => 'yearly']);

        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'title' => 'Diesel', 'amount' => 120000, 'date' => '2025-10-10', 'status' => true]);
        $stray = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Stray', 'slug' => 'stray', 'status' => true]);
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $stray->id, 'title' => 'Stray', 'amount' => 9000, 'date' => '2025-10-10', 'status' => true]);

        $this->get(route('admin.budget-sheet.show', $sheet))->assertSuccessful()
            ->assertSee('120,000')
            ->assertSee('-20,000')
            ->assertSee(__(':amount moved in this period but sits on no line.', ['amount' => '9,000']));

        $this->assertEquals(120000, (float) $sheet->fresh()->spent_amount, 'the budget record is kept in step with the sheet');

        $this->get(route('admin.budget-sheet.csv', $sheet))->assertSuccessful()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_a_new_sheet_starts_from_last_year_and_carries_the_closing_balance(): void
    {
        $this->template();
        $this->point('expense', $this->fuel->id, '461');
        $this->point('income', $this->gifts->id, '621');
        $last = $this->sheet('2024-09-01', '2025-07-31', 'closed');
        $last->update(['opening_balance' => 50000]);
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'title' => 'Diesel', 'amount' => 80000, 'date' => '2025-03-10', 'status' => true]);
        Income::create(['branch_id' => $this->branch->id, 'category_id' => $this->gifts->id, 'title' => 'Gift', 'amount' => 200000, 'date' => '2025-03-01', 'status' => true]);

        $this->post(route('admin.budget-sheet.store'), ['title' => 'I&E 2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'seed_from' => 'actual', 'uplift_percent' => 10])->assertRedirect()->assertSessionMissing('error')->assertSessionHasNoErrors();

        $next = Budget::where('is_institutional', true)->where('title', 'I&E 2025/2026')->firstOrFail();
        $this->assertEquals(170000, (float) $next->opening_balance, '50,000 + 200,000 − 80,000');
        $this->assertEquals(88000, BudgetAllocation::where('budget_id', $next->id)->where('budget_line_id', $this->line('461')->id)->value('allocated_amount'));
        $this->assertEquals(220000, BudgetAllocation::where('budget_id', $next->id)->where('budget_line_id', $this->line('621')->id)->value('allocated_amount'));
    }

    public function test_tuition_can_be_budgeted_from_headcount(): void
    {
        $this->template();
        $sheet = $this->sheet();
        $pupil = $this->pupilIn('primary', 'Primary 3');
        \Illuminate\Support\Facades\DB::table('fee_structures')->insert(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $pupil->classSection->form_id, 'fee_category_id' => $this->tuition->id, 'amount' => 150000, 'created_at' => now(), 'updated_at' => now()]);

        $this->get(route('admin.budget-sheet.show', $sheet))->assertSuccessful()->assertSee(__(':count pupils now, :rate per year', ['count' => 1, 'rate' => '150,000']));

        $this->post(route('admin.budget-sheet.forecast', $sheet), ['budget_line_id' => $this->line('611')->id, 'student_count' => 120, 'rate' => 150000])->assertRedirect();

        $this->assertEquals(18000000, BudgetAllocation::where('budget_id', $sheet->id)->where('budget_line_id', $this->line('611')->id)->value('allocated_amount'));
        $this->get(route('admin.budget-sheet.show', $sheet))->assertSee(__(':count pupils at :rate', ['count' => 120, 'rate' => '150,000']));
    }

    // ------------------------------------------------------------ the month

    public function test_the_monthly_summary_reads_budget_monthly_brought_forward_cumulative_balance(): void
    {
        $this->template();
        $this->point('expense', $this->fuel->id, '461');
        $sheet = $this->sheet(status: 'active');
        BudgetAllocation::create(['branch_id' => $this->branch->id, 'budget_id' => $sheet->id, 'budget_line_id' => $this->line('461')->id, 'title' => 'Fuel', 'allocated_amount' => 100000, 'period' => 'yearly']);
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'title' => 'Sept', 'amount' => 10000, 'date' => '2025-09-10', 'status' => true]);
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $this->fuel->id, 'title' => 'Oct', 'amount' => 25000, 'date' => '2025-10-05', 'status' => true]);

        $summary = app(DaybookService::class)->monthlySummary($sheet, Carbon::parse('2025-10-01'));
        $row = $summary['lines'][$this->line('461')->id];

        $this->assertSame(100000.0, $row['budget']);
        $this->assertSame(25000.0, $row['monthly']);
        $this->assertSame(10000.0, $row['brought']);
        $this->assertSame(35000.0, $row['cumulative']);
        $this->assertSame(65000.0, $row['balance']);
        // The heading carries its lines.
        $this->assertSame(35000.0, $summary['lines'][$this->line('460')->id]['cumulative']);
        $this->assertSame(35000.0, $summary['totals']['expenditure']['cumulative']);

        $first = app(DaybookService::class)->monthlySummary($sheet, Carbon::parse('2025-09-01'));
        $this->assertSame(0.0, $first['lines'][$this->line('461')->id]['brought'], 'the first month opens at zero');

        $this->get(route('admin.daybook.summary', ['budget' => $sheet->id, 'month' => '2025-10']))->assertSuccessful()
            ->assertSee(__('B/F'))->assertSee('65,000')->assertSee('TRANSPORT');
    }

    public function test_profit_centres_pair_an_income_and_an_expenditure_line(): void
    {
        $this->template();
        $canteenIn = IncomeCategory::create(['branch_id' => $this->branch->id, 'title' => 'Canteen sales', 'slug' => 'canteen-sales', 'status' => true]);
        $canteenOut = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Food', 'slug' => 'food', 'status' => true]);
        $this->point('income', $canteenIn->id, '625');
        $this->point('expense', $canteenOut->id, '472');
        $sheet = $this->sheet(status: 'active');
        Income::create(['branch_id' => $this->branch->id, 'category_id' => $canteenIn->id, 'title' => 'Sales', 'amount' => 90000, 'date' => '2025-10-02', 'status' => true]);
        Expense::create(['branch_id' => $this->branch->id, 'category_id' => $canteenOut->id, 'title' => 'Rice', 'amount' => 60000, 'date' => '2025-10-03', 'status' => true]);

        $summary = app(DaybookService::class)->monthlySummary($sheet, Carbon::parse('2025-10-01'));

        $this->assertSame(30000.0, $summary['profit_centres']['Canteen']['margin']);
        $this->assertSame(90000.0, $summary['totals']['income']['cumulative'], 'an activity is not a third section: nothing is added twice');
    }
}
