<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
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
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\DaybookService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The cash book: every franc in and out, in date order, from what is already
 * recorded. The rules that matter are which money is NOT in it — credit
 * settlements, unapproved receipts, reversals, unpaid salaries — because a
 * cash book that counts money that never moved is worse than none.
 */
class DaybookTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentEnrollment $enrollment;
    private FeeCategory $tuition;
    private FeeCategory $pta;
    private IncomeCategory $canteen;
    private ExpenseCategory $chalk;

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

        $session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Primary 3', 'short_name' => 'P3',
            'level' => 'primary', 'school_level' => 'nursery_primary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Primary 3A', 'max_students' => 40, 'is_active' => true,
        ]);
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Bih', 'last_name' => 'Pupil',
            'date_of_birth' => '2016-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        $this->enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        $this->tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);
        $this->pta = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'PTA Levy', 'code' => 'PTA', 'is_active' => true]);
        $this->canteen = IncomeCategory::create(['branch_id' => $this->branch->id, 'title' => 'Canteen', 'slug' => 'canteen', 'status' => true]);
        $this->chalk = ExpenseCategory::create(['branch_id' => $this->branch->id, 'title' => 'Teaching materials', 'slug' => 'teaching', 'status' => true]);
    }

    private function fee(FeeCategory $category, float $amount): StudentFee
    {
        return StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount,
            'status' => 'unpaid',
        ]);
    }

    /** @param array<int, array{0: StudentFee, 1: float}> $allocations */
    private function receipt(string $date, float $amount, array $allocations = [], string $method = 'cash', string $status = 'verified'): Payment
    {
        $payment = Payment::create([
            'branch_id' => $this->branch->id,
            'receipt_number' => 'RCP-'.str_pad((string) (Payment::count() + 1), 4, '0', STR_PAD_LEFT),
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => $amount, 'payment_method' => $method, 'payment_date' => $date,
            'payer_name' => 'Mama Bih', 'verification_status' => $status,
        ]);

        foreach ($allocations as [$fee, $part]) {
            PaymentAllocation::create([
                'branch_id' => $this->branch->id, 'payment_id' => $payment->id,
                'student_fee_id' => $fee->id, 'amount' => $part,
            ]);
        }

        return $payment;
    }

    private function income(string $date, float $amount, string $title = 'Canteen takings'): Income
    {
        return Income::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->canteen->id,
            'title' => $title, 'amount' => $amount, 'date' => $date, 'status' => true,
        ]);
    }

    private function expense(string $date, float $amount, string $title = 'Chalk', string $approval = 'approved'): Expense
    {
        return Expense::create([
            'branch_id' => $this->branch->id, 'category_id' => $this->chalk->id,
            'title' => $title, 'amount' => $amount, 'date' => $date, 'status' => true,
            'approval_status' => $approval,
        ]);
    }

    private function salary(?string $payDate, float $net, int $status = Payroll::STATUS_PAID, string $month = '2025-10'): Payroll
    {
        $staff = User::firstOrCreate(['email' => 'staff@example.test'], [
            'first_name' => 'Sam', 'last_name' => 'Teacher', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);

        return Payroll::create([
            'branch_id' => $this->branch->id, 'user_id' => $staff->id,
            'basic_salary' => $net + 10000, 'gross_salary' => $net + 10000,
            'tax' => 10000, 'net_salary' => $net, 'total_cost' => $net + 10000,
            'salary_month' => $month, 'pay_date' => $payDate, 'status' => $status,
        ]);
    }

    private function book(string $month): array
    {
        $service = app(DaybookService::class);
        $date = Carbon::createFromFormat('Y-m-d', $month.'-01');

        return $service->book($date, $service->yearFor($date)['start']);
    }

    // ------------------------------------------------------------ the rows

    public function test_the_four_sources_meet_in_one_book_in_date_order(): void
    {
        $this->receipt('2025-10-03', 20000, [[$this->fee($this->tuition, 50000), 20000]]);
        $this->income('2025-10-01', 5000);
        $this->expense('2025-10-02', 3000);
        $this->salary('2025-10-31', 80000);

        $book = $this->book('2025-10');

        $this->assertSame(['income', 'expense', 'payment', 'payroll'], $book['rows']->pluck('source')->all());
        $this->assertSame(25000.0, $book['total_in']);
        $this->assertSame(83000.0, $book['total_out']);
        $this->assertSame(-58000.0, $book['closing']);
    }

    public function test_the_balance_runs_down_the_page(): void
    {
        $this->income('2025-10-01', 5000);
        $this->expense('2025-10-02', 3000);
        $this->income('2025-10-03', 1000);

        $this->assertSame([5000.0, 2000.0, 3000.0], $this->book('2025-10')['rows']->pluck('balance')->all());
    }

    public function test_last_month_is_brought_forward_from_the_start_of_the_year(): void
    {
        $this->income('2025-09-15', 10000);
        $this->expense('2025-10-05', 4000);
        // Before the year opened: not this year's money.
        $this->income('2025-08-20', 999999);

        $book = $this->book('2025-10');

        $this->assertSame(10000.0, $book['brought_forward']);
        $this->assertSame(6000.0, $book['closing']);
    }

    // ------------------------------------------------------------ what is NOT cash

    public function test_a_receipt_settled_from_credit_moved_no_cash(): void
    {
        $this->receipt('2025-10-03', 20000, [], 'student_credit');

        $this->assertTrue($this->book('2025-10')['rows']->isEmpty());
    }

    public function test_a_receipt_awaiting_approval_or_reversed_is_not_in_the_book(): void
    {
        $this->receipt('2025-10-03', 20000, [], 'cash', 'pending');
        $this->receipt('2025-10-04', 20000, [], 'cash', 'reversed');
        $this->receipt('2025-10-05', 20000, [], 'cash', 'rejected');

        $this->assertTrue($this->book('2025-10')['rows']->isEmpty());
    }

    public function test_an_unpaid_salary_has_not_left_the_cash(): void
    {
        $this->salary(null, 80000, Payroll::STATUS_UNPAID, '2025-09');
        $this->salary('2025-10-31', 70000);

        $book = $this->book('2025-10');

        $this->assertCount(1, $book['rows']);
        // Net, not gross: the tax has not been remitted yet.
        $this->assertSame(70000.0, $book['total_out']);
    }

    public function test_a_rejected_expense_is_not_spending(): void
    {
        $this->expense('2025-10-02', 3000, 'Chalk', 'rejected');

        $this->assertTrue($this->book('2025-10')['rows']->isEmpty());
    }

    // ------------------------------------------------------------ the headings

    public function test_one_receipt_is_one_row_but_is_analysed_across_the_fees_it_paid(): void
    {
        $this->receipt('2025-10-03', 25000, [
            [$this->fee($this->tuition, 50000), 20000],
            [$this->fee($this->pta, 5000), 5000],
        ]);

        $book = $this->book('2025-10');

        $this->assertCount(1, $book['rows']);
        $this->assertSame(25000.0, $book['total_in']);
        $this->assertEquals(['Tuition' => 20000.0, 'PTA Levy' => 5000.0], $book['headings']['in']);
    }

    public function test_a_receipt_not_yet_allocated_still_arrived_as_cash(): void
    {
        $this->receipt('2025-10-03', 15000, [[$this->fee($this->tuition, 50000), 10000]]);

        $headings = $this->book('2025-10')['headings']['in'];

        $this->assertEquals(10000.0, $headings['Tuition']);
        $this->assertEquals(5000.0, $headings[__('School fees')]);
    }

    // ------------------------------------------------------------ the year

    public function test_the_year_is_read_month_by_month_with_a_running_balance(): void
    {
        $this->income('2025-09-10', 10000);
        $this->expense('2025-10-10', 4000);
        $this->income('2025-11-10', 1000);

        $service = app(DaybookService::class);
        $year = $service->yearFor(Carbon::parse('2025-10-01'));
        $analysis = $service->year($year['start'], $year['end']);

        $this->assertSame('2025/2026', $year['label']);
        $this->assertCount(11, $analysis['months']);
        $this->assertSame([10000.0, 6000.0, 7000.0], array_slice(array_column($analysis['months'], 'balance'), 0, 3));
        $this->assertSame('Canteen', $analysis['top_in'][0]['heading']);
        $this->assertSame(100.0, $analysis['top_in'][0]['share']);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_screens_render_and_export(): void
    {
        $this->income('2025-10-01', 5000);
        $this->expense('2025-10-02', 3000);

        $this->get(route('admin.daybook.index', ['month' => '2025-10']))
            ->assertSuccessful()
            ->assertSee('Canteen takings')
            ->assertSee(__('Balance brought forward'));

        $this->get(route('admin.daybook.year', ['month' => '2025-10']))
            ->assertRedirect(route('admin.daybook.analysis', ['month' => '2025-10']));
        $this->get(route('admin.daybook.analysis', ['month' => '2025-10']))
            ->assertSuccessful()
            ->assertSee(__('Cash position, month by month'))
            ->assertSee(__('Who has paid, and who has not'));

        $csv = $this->get(route('admin.daybook.csv', ['month' => '2025-10']))->assertSuccessful();
        $this->assertStringContainsString('Canteen takings', $csv->streamedContent());

        $this->get(route('admin.daybook.pdf', ['month' => '2025-10']))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_bad_month_falls_back_to_this_one(): void
    {
        $this->get(route('admin.daybook.index', ['month' => 'garbage']))
            ->assertSuccessful()
            ->assertSee(now()->translatedFormat('F Y'));
    }

    // ------------------------------------------------------------ trends & analysis

    private function analysis(?Budget $sheet = null): array
    {
        return app(DaybookService::class)->analysis(Carbon::parse('2025-09-01'), Carbon::parse('2026-07-31')->endOfDay(), $sheet);
    }

    private function sheet(float $opening = 0): Budget
    {
        return Budget::create([
            'branch_id' => $this->branch->id, 'title' => 'I&E 2025/2026', 'type' => 'annual', 'is_institutional' => true,
            'fiscal_year' => '2025', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'total_amount' => 0, 'opening_balance' => $opening, 'status' => 'draft', 'created_by' => $this->admin->id,
        ]);
    }

    private function pupil(string $name): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'first_name' => $name, 'last_name' => 'Pupil', 'date_of_birth' => '2016-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->enrollment->academic_session_id, 'term_id' => $this->enrollment->term_id,
            'class_section_id' => $this->enrollment->class_section_id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
    }

    private function billed(StudentEnrollment $enrollment, FeeCategory $category, float $net, float $paid): StudentFee
    {
        return StudentFee::create([
            'branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'fee_category_id' => $category->id,
            'original_amount' => $net, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $net, 'paid_amount' => $paid, 'balance' => $net - $paid,
            'status' => $paid <= 0 ? 'unpaid' : ($paid >= $net ? 'paid' : 'partial'),
        ]);
    }

    private function line(string $code): int
    {
        return (int) BudgetLine::where('code', $code)->value('id');
    }

    public function test_the_cash_curve_starts_from_the_sheets_opening_balance(): void
    {
        $this->income('2025-09-10', 50000);
        $this->expense('2025-10-05', 80000);
        $this->income('2025-11-03', 10000);

        $a = $this->analysis($this->sheet(opening: 100000));
        $months = collect($a['months'])->keyBy('month');

        $this->assertSame(150000.0, $months['2025-09']['balance']);
        $this->assertSame(70000.0, $months['2025-10']['balance']);
        $this->assertSame(80000.0, $months['2025-11']['balance']);
        $this->assertSame(70000.0, $a['lowest']['balance']);
        $this->assertSame(80000.0, $a['closing']);
    }

    public function test_fees_are_shown_apart_inside_the_money_in(): void
    {
        $this->receipt('2025-09-15', 30000, [[$this->fee($this->tuition, 30000), 30000]]);
        $this->income('2025-09-20', 5000);

        $september = collect($this->analysis()['months'])->firstWhere('month', '2025-09');

        $this->assertSame(35000.0, $september['in']);
        $this->assertSame(30000.0, $september['fees']);
    }

    public function test_what_changed_is_measured_against_the_last_month_that_moved(): void
    {
        $this->income('2025-09-10', 40000);
        // October is quiet; November is compared with September, not with nothing.
        $this->income('2025-11-10', 10000);

        $movers = $this->analysis()['movers'];

        $this->assertSame(Carbon::parse('2025-09-01')->translatedFormat('M Y'), $movers['from']);
        $this->assertSame(Carbon::parse('2025-11-01')->translatedFormat('M Y'), $movers['to']);
        $this->assertSame(-30000.0, $movers['rows'][0]['change']);
    }

    public function test_spending_ahead_of_the_year_is_said_before_the_money_runs_out(): void
    {
        app(\App\Services\BudgetLineTemplate::class)->load();
        DefaultAccountMapping::updateOrCreate(['mapping_type' => 'expense', 'category_id' => $this->chalk->id], ['budget_line_id' => $this->line('461'), 'status' => 'active']);
        $sheet = $this->sheet();
        BudgetAllocation::create(['branch_id' => $this->branch->id, 'budget_id' => $sheet->id, 'budget_line_id' => $this->line('461'), 'title' => 'Materials', 'allocated_amount' => 100000, 'period' => 'yearly']);

        $this->expense('2025-09-20', 60000);
        Carbon::setTestNow('2025-11-01');

        $pacing = $this->analysis($sheet)['pacing'];

        // Two months of eleven gone, sixty per cent of the budget gone.
        $this->assertEqualsWithDelta(18.0, $pacing['elapsed'], 1);
        $this->assertSame(60.0, $pacing['consumed']);
        $this->assertSame('critical', $pacing['status']);
        $this->assertSame(100.0, $pacing['coverage']);

        Carbon::setTestNow();
    }

    public function test_a_sheet_nobody_finished_is_not_called_an_overspend(): void
    {
        app(\App\Services\BudgetLineTemplate::class)->load();
        $sheet = $this->sheet();
        // One small figure on one line, and the spending on a line with none.
        BudgetAllocation::create(['branch_id' => $this->branch->id, 'budget_id' => $sheet->id, 'budget_line_id' => $this->line('461'), 'title' => 'Fuel', 'allocated_amount' => 1000, 'period' => 'yearly']);
        DefaultAccountMapping::updateOrCreate(['mapping_type' => 'expense', 'category_id' => $this->chalk->id], ['budget_line_id' => $this->line('413'), 'status' => 'active']);
        $this->expense('2025-09-20', 60000);

        $this->assertSame('incomplete', $this->analysis($sheet)['pacing']['status']);
    }

    public function test_who_has_paid_counts_pupils_not_fees(): void
    {
        // Bih, the fixture pupil: tuition paid, PTA owed. One pupil, paid in part.
        $this->billed($this->enrollment, $this->tuition, 50000, 50000);
        $this->billed($this->enrollment, $this->pta, 5000, 0);
        $this->billed($this->pupil('Ewane'), $this->tuition, 50000, 50000);
        $this->billed($this->pupil('Kum'), $this->tuition, 50000, 0);
        // Money paid beyond one fee does not settle another that is owed.
        $tabe = $this->pupil('Tabe');
        $this->billed($tabe, $this->tuition, 50000, 60000);
        $this->billed($tabe, $this->pta, 5000, 0);

        $c = app(\App\Services\FeeCollectionService::class)->forSession(AcademicSession::first());

        $this->assertSame(4, $c['pupils']['pupils']);
        $this->assertSame(1, $c['pupils']['full']);
        $this->assertSame(2, $c['pupils']['part']);
        $this->assertSame(1, $c['pupils']['nothing']);
        $this->assertSame(150000.0, $c['pupils']['collected']);
        $this->assertSame(60000.0, $c['pupils']['outstanding']);
        $this->assertSame('Kum Pupil', $c['debtors'][0]['name']);
    }

    public function test_the_checks_say_what_is_not_in_the_book_yet(): void
    {
        $this->receipt('2025-10-01', 20000, [], 'cash', 'pending');
        $this->income('2025-10-02', 5000);

        $a = $this->analysis();
        $list = app(\App\Services\BookConfidence::class)->checks(Carbon::parse('2025-09-01'), Carbon::parse('2026-07-31'), null, $a['unanalysed']);
        $checks = collect($list)->keyBy('key');

        $this->assertFalse($checks['waiting']['ok']);
        $this->assertStringContainsString('20,000', $checks['waiting']['detail']);
        $this->assertFalse($checks['unanalysed']['ok']);
        $this->assertFalse($checks['sheet']['ok']);
        $this->assertTrue($checks['ledger']['ok']);
        $this->assertFalse($list[0]['ok'], 'what needs doing comes first');
    }

    public function test_the_meeting_pack_carries_the_charts_and_refuses_anything_else(): void
    {
        $this->income('2025-10-02', 5000);
        $png = 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('c.png', 20, 10)->get());

        $this->post(route('admin.daybook.analysis.pdf', ['month' => '2025-10']), [
            'charts' => ['cash' => $png, 'monthly' => 'javascript:alert(1)', 'other' => $png],
        ])->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_workbook_has_the_month_the_year_and_the_summary(): void
    {
        $this->sheet();
        $this->income('2025-10-02', 5000);
        $this->expense('2025-10-05', 3000);

        $response = $this->get(route('admin.daybook.excel', ['month' => '2025-10']))->assertSuccessful();
        $path = tempnam(sys_get_temp_dir(), 'book').'.xlsx';
        file_put_contents($path, file_get_contents($response->baseResponse->getFile()->getPathname()));

        $book = IOFactory::load($path);
        $this->assertCount(3, $book->getAllSheets());
        $this->assertSame(Carbon::parse('2025-10-01')->translatedFormat('F Y'), $book->getSheet(0)->getTitle());

        $canteen = collect($book->getSheet(0)->toArray())->first(fn ($r) => in_array('Canteen takings', $r, true));
        $this->assertNotNull($canteen);
        $this->assertSame(5000.0, (float) str_replace(',', '', (string) $canteen[7]));

        @unlink($path);
    }

    public function test_someone_who_may_view_but_not_export_gets_the_page_without_the_pack(): void
    {
        $role = \App\Models\Role::create(['name' => 'cash-viewer', 'display_name' => 'Cash viewer', 'is_system' => false]);
        $role->permissions()->sync([\App\Models\Permission::firstOrCreate(['name' => 'daybook.view'], ['display_name' => 'daybook.view', 'group_name' => 'Daybook'])->id]);
        // An accountant by title, held back to viewing by the role actually given.
        $viewer = User::create(['first_name' => 'Vi', 'last_name' => 'Ewer', 'email' => 'viewer@example.test', 'password' => 'password', 'role' => 'accountant', 'is_active' => true]);
        $viewer->roles()->sync([$role->id]);
        $viewer->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->income('2025-10-02', 5000);

        $this->actingAs($viewer)->get(route('admin.daybook.analysis', ['month' => '2025-10']))
            ->assertSuccessful()->assertDontSee(__('Meeting pack (PDF)'));
        $this->post(route('admin.daybook.analysis.pdf', ['month' => '2025-10']))->assertForbidden();
        $this->get(route('admin.daybook.excel', ['month' => '2025-10']))->assertForbidden();
    }

    public function test_a_year_that_opens_mid_month_still_owns_that_months_book(): void
    {
        AcademicSession::query()->update(['is_current' => false]);
        AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => false]);
        AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2027/2028', 'start_date' => '2027-09-06', 'end_date' => '2028-07-01', 'status' => 'active', 'is_current' => true]);

        // Asked for on the 1st, before the year's first day: still that year's book.
        $this->assertSame('2026/2027', app(DaybookService::class)->yearFor(Carbon::parse('2026-09-01'))['label']);
        $this->assertSame('2027/2028', app(DaybookService::class)->yearFor(Carbon::parse('2027-09-01'))['label']);
    }
}
