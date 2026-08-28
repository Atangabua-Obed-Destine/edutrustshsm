<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\ClassSection;
use App\Models\DefaultAccountMapping;
use App\Models\FeeCategory;
use App\Models\FiscalYear;
use App\Models\Form;
use App\Models\JournalEntryLine;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use App\Services\StudentCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Over-payment used to be dropped: the Payment recorded the full amount, the
 * allocations summed to less, and the difference existed nowhere.
 */
class StudentCreditTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private StudentEnrollment $enrollment;
    private ChartOfAccount $cash;
    private ChartOfAccount $revenue;
    private ChartOfAccount $advances;
    private int $seq = 0;

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
        AccountingPeriod::create([
            'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
            'name' => 'January 2026', 'period_number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'is_closed' => false,
        ]);

        $this->cash = $this->account('571', 5, 'debit', 'Cash Box');
        $this->revenue = $this->account('706', 7, 'credit', 'Fee Revenue');
        $this->advances = $this->account('419', 4, 'credit', 'Student Advances');

        $this->map('fee_payment', $this->cash, $this->revenue);
        $this->map('student_credit', $this->cash, $this->advances);
        // Applying a credit clears the liability into revenue; no cash moves.
        $this->map('credit_applied', $this->advances, $this->revenue);

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
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Sam', 'last_name' => 'Student',
            'date_of_birth' => '2012-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        $this->enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
    }

    private function account(string $code, int $class, string $normal, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code,
            'account_name' => $name, 'class_number' => $class,
            'account_type' => 'detail', 'normal_balance' => $normal, 'is_active' => true,
        ]);
    }

    private function map(string $type, ChartOfAccount $debit, ChartOfAccount $credit): void
    {
        DefaultAccountMapping::create([
            'branch_id' => $this->branch->id, 'mapping_type' => $type, 'category_id' => null,
            'debit_account_id' => $debit->id, 'credit_account_id' => $credit->id, 'status' => 'active',
        ]);
    }

    private function fee(float $amount): StudentFee
    {
        $this->seq++;
        $category = FeeCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Fee '.$this->seq, 'code' => 'F'.$this->seq, 'is_active' => true,
        ]);

        return StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'fine_amount' => 0, 'net_amount' => $amount, 'paid_amount' => 0,
            'balance' => $amount, 'status' => 'unpaid',
        ]);
    }

    private function pay(float $amount): \App\Models\Payment
    {
        return app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);
    }

    private function debited(ChartOfAccount $a): float
    {
        return (float) JournalEntryLine::where('account_id', $a->id)->sum('debit');
    }

    private function credited(ChartOfAccount $a): float
    {
        return (float) JournalEntryLine::where('account_id', $a->id)->sum('credit');
    }

    public function test_paying_more_than_is_owed_creates_a_credit(): void
    {
        $this->fee(10000);

        $payment = $this->pay(15000);

        $credit = StudentCredit::firstOrFail();
        $this->assertEquals(5000, (float) $credit->amount);
        $this->assertEquals(5000, (float) $credit->balance);
        $this->assertSame($payment->id, $credit->payment_id);
        $this->assertSame('overpayment', $credit->source);
    }

    public function test_paying_exactly_what_is_owed_creates_no_credit(): void
    {
        $this->fee(10000);

        $this->pay(10000);

        $this->assertSame(0, StudentCredit::count());
    }

    public function test_the_overpayment_is_a_liability_not_revenue(): void
    {
        $this->fee(10000);

        $this->pay(15000);

        // 10,000 earned, 5,000 held on the student's behalf.
        $this->assertEquals(10000, $this->credited($this->revenue));
        $this->assertEquals(5000, $this->credited($this->advances));
        $this->assertEquals(15000, $this->debited($this->cash), 'all the cash received is recorded');
    }

    public function test_applying_credit_settles_the_fee(): void
    {
        $this->fee(10000);
        $this->pay(15000);          // 5,000 credit
        $newFee = $this->fee(4000); // a later charge

        app(StudentCreditService::class)->apply($this->enrollment);

        $newFee->refresh();
        $this->assertEquals(0, (float) $newFee->balance);
        $this->assertEquals(4000, (float) $newFee->paid_amount);
    }

    public function test_applying_credit_does_not_record_the_cash_twice(): void
    {
        $this->fee(10000);
        $this->pay(15000);
        $this->fee(4000);

        app(StudentCreditService::class)->apply($this->enrollment);

        // Cash was received once, when the over-payment was made.
        $this->assertEquals(15000, $this->debited($this->cash));
        // The liability is cleared by what was applied...
        $this->assertEquals(4000, $this->debited($this->advances));
        // ...and becomes revenue.
        $this->assertEquals(14000, $this->credited($this->revenue));
    }

    public function test_the_credit_balance_is_drawn_down(): void
    {
        $this->fee(10000);
        $this->pay(15000);
        $this->fee(3000);

        app(StudentCreditService::class)->apply($this->enrollment);

        $credit = StudentCredit::firstOrFail();
        $this->assertEquals(3000, (float) $credit->used_amount);
        $this->assertEquals(2000, (float) $credit->balance);
    }

    public function test_applying_credit_never_creates_new_credit(): void
    {
        $this->fee(10000);
        $this->pay(20000);   // 10,000 credit
        $this->fee(3000);    // only 3,000 owed

        app(StudentCreditService::class)->apply($this->enrollment);

        // Applying is capped at what is owed, so the leftover stays as credit
        // rather than round-tripping into a second credit row.
        $this->assertSame(1, StudentCredit::count());
        $this->assertEquals(7000, (float) StudentCredit::firstOrFail()->balance);
    }

    public function test_a_student_with_no_credit_is_refused(): void
    {
        $this->fee(10000);

        $this->expectExceptionMessage('no credit available');
        app(StudentCreditService::class)->apply($this->enrollment);
    }

    public function test_credit_with_nothing_owed_is_refused(): void
    {
        $this->fee(10000);
        $this->pay(15000);

        $this->expectExceptionMessage('no outstanding fees');
        app(StudentCreditService::class)->apply($this->enrollment);
    }

    public function test_the_screen_renders_and_credit_can_be_applied_from_it(): void
    {
        $this->fee(10000);
        $this->pay(15000);
        $this->fee(2000);

        $this->get(route('admin.student-credits.index'))->assertSuccessful();

        $this->post(route('admin.student-credits.apply'), [
            'student_enrollment_id' => $this->enrollment->id,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(3000, (float) StudentCredit::firstOrFail()->balance);
    }
}
