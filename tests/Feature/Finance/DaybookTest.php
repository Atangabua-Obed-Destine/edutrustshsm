<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
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
            ->assertSuccessful()
            ->assertSee(__('Month by month'));

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
}
