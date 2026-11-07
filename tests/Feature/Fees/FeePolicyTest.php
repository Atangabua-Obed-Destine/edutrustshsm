<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicSession;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\ClassSection;
use App\Models\DefaultAccountMapping;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeCategory;
use App\Models\FeeDiscount;
use App\Models\FiscalYear;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\JournalEntryLine;
use App\Models\ParentPaymentSubmission;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The fee policy of the reference system, end to end: last year's debt is
 * still owed after promotion and is collected first; what a pupil owes is
 * changed only by rules, each change with a reason; a reused payment
 * reference is caught before it counts twice.
 */
class FeePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Student $student;
    private StudentEnrollment $lastYear;
    private StudentEnrollment $thisYear;
    private FeeCategory $tuition;
    private FeeCategory $pta;
    private StudentFee $arrear;
    private StudentFee $tuitionFee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $old = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'completed', 'is_current' => false]);
        $year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $p2 = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary 2', 'short_name' => 'P2', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 2, 'is_active' => true]);
        $p3 = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 3, 'is_active' => true]);
        $p2a = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $p2->id, 'section' => 'A', 'name' => 'P2A', 'max_students' => 40, 'is_active' => true]);
        $p3a = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $p3->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        $this->tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        $this->pta = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'PTA', 'code' => 'PTA', 'is_active' => true]);

        $guardian = Guardian::create(['branch_id' => $this->branch->id, 'guardian_name' => 'Pat Parent', 'guardian_phone' => '677000000', 'emergency_contact_name' => 'Pat Parent', 'emergency_contact_phone' => '677000000']);
        $this->student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS250001', 'batch_id' => $batch->id, 'first_name' => 'Bih', 'last_name' => 'NGWA', 'date_of_birth' => '2018-05-15', 'gender' => 'female', 'guardian_id' => $guardian->id, 'status' => 'active', 'admission_date' => '2025-09-08']);

        // Last year in P2, promoted with 20,000 of tuition unpaid.
        $this->lastYear = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'academic_session_id' => $old->id, 'term_id' => $term->id, 'class_section_id' => $p2a->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-08', 'status' => 'promoted']);
        $this->arrear = $this->fee($this->lastYear, $this->tuition, 50000, 30000);

        $this->thisYear = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'academic_session_id' => $year->id, 'term_id' => $term->id, 'class_section_id' => $p3a->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $this->tuitionFee = $this->fee($this->thisYear, $this->tuition, 60000);
    }

    private function fee(StudentEnrollment $e, FeeCategory $c, float $amount, float $paid = 0): StudentFee
    {
        return StudentFee::create([
            'branch_id' => $this->branch->id, 'student_enrollment_id' => $e->id, 'fee_category_id' => $c->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $amount, 'paid_amount' => $paid, 'balance' => $amount - $paid,
            'status' => $paid > 0 ? 'partial' : 'unpaid',
        ]);
    }

    private function pay(float $amount, ?StudentFee $target = null, string $method = 'cash'): Payment
    {
        return app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->thisYear->id,
            'target_fee_id' => $target?->id,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => '2026-10-01',
        ]);
    }

    // ── Arrears ─────────────────────────────────────────────────────────

    public function test_last_years_debt_is_paid_first_after_promotion(): void
    {
        $this->pay(30000);

        $this->assertEquals(0, (float) $this->arrear->fresh()->balance, 'the 20,000 arrear is settled first');
        $this->assertEquals(50000, (float) $this->tuitionFee->fresh()->balance, 'the other 10,000 goes to this year');
    }

    public function test_every_screen_shows_the_arrear(): void
    {
        $this->get(route('admin.student-fees', $this->student))->assertSuccessful()
            ->assertSee(__('Arrears from earlier years'))->assertViewHas('totalArrears', 20000.0);

        $this->get(route('admin.collect-fees.index', ['session_id' => $this->thisYear->academic_session_id]))->assertSuccessful()
            ->assertViewHas('studentFees', fn ($fees) => $fees->contains('id', $this->arrear->id))
            ->assertViewHas('outstandingByEnrollment', fn ($owed) => (float) $owed[$this->thisYear->id] === 80000.0);

        $receipt = $this->pay(5000, $this->tuitionFee);
        $this->get(route('admin.collect-fees.receipt', $receipt))->assertSuccessful()
            ->assertSee(__('Arrears from earlier years still owed'));
    }

    public function test_a_pupil_who_owes_only_last_year_is_a_defaulter(): void
    {
        $this->pay(60000, $this->tuitionFee);

        $this->get(route('admin.fee-reports.defaulters'))->assertSuccessful()
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1
                && $rows[0]['arrears'] === 20000.0 && $rows[0]['balance'] === 20000.0);
    }

    public function test_an_arrear_paid_at_the_counter_goes_on_this_years_receipt(): void
    {
        $this->postJson(route('admin.collect-fees.store-payment'), [
            'student_fee_id' => $this->arrear->id, 'amount' => 20000,
            'payment_method' => 'cash', 'payment_date' => '2026-10-01',
        ])->assertOk();

        $payment = Payment::latest('id')->first();
        $this->assertSame($this->thisYear->id, $payment->student_enrollment_id);
        $this->assertEquals(0, (float) $this->arrear->fresh()->balance);
    }

    // ── Adjusting what is owed ─────────────────────────────────────────

    public function test_an_amount_is_raised_freely_but_lowered_only_while_unpaid(): void
    {
        $this->put(route('admin.assignment-history.amount', $this->tuitionFee), ['amount' => 65000, 'reason' => 'Fee rise voted by the PTA'])
            ->assertSessionHas('success');
        $fee = $this->tuitionFee->fresh();
        $this->assertEquals(65000, (float) $fee->balance);
        $this->assertStringContainsString('Fee rise voted by the PTA', $fee->notes);

        $this->pay(10000, $this->tuitionFee);
        $this->put(route('admin.assignment-history.amount', $this->tuitionFee), ['amount' => 40000, 'reason' => 'Mistake'])
            ->assertSessionHas('error');
        $this->assertEquals(65000, (float) $this->tuitionFee->fresh()->original_amount);

        // No reason, no change.
        $this->put(route('admin.assignment-history.amount', $this->tuitionFee), ['amount' => 70000])->assertSessionHasErrors('reason');
    }

    public function test_a_fee_with_money_on_it_cannot_be_removed(): void
    {
        $this->pay(10000, $this->tuitionFee);

        $this->delete(route('admin.assignment-history.destroy', $this->tuitionFee), ['reason' => 'Billed twice'])->assertSessionHas('error');
        $this->assertNotNull($this->tuitionFee->fresh());
    }

    public function test_a_double_billing_is_put_right_by_moving_the_money_then_removing(): void
    {
        // The first bill is settled; a second one for the same year took
        // 30,000 before anyone noticed.
        // Billed again on a first-term enrolment row of the same year, the
        // way the live school's pupil was before the term-move fix.
        $this->pay(80000, $this->tuitionFee);
        $earlierTerm = Term::create(['branch_id' => $this->branch->id, 'term_number' => 3, 'name' => 'Earlier Term', 'start_date' => '2026-09-01', 'end_date' => '2026-09-06', 'is_current' => false]);
        $firstTerm = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $this->student->id, 'academic_session_id' => $this->thisYear->academic_session_id, 'term_id' => $earlierTerm->id, 'class_section_id' => $this->thisYear->class_section_id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'completed']);
        $twin = $this->fee($firstTerm, $this->tuition, 60000);
        $this->pay(30000, $twin);
        $this->assertEquals(30000, (float) $twin->fresh()->paid_amount);

        $this->get(route('admin.assignment-history.show', $twin))->assertSuccessful()
            ->assertSee(__('This pupil was billed :fee more than once for :year.', ['fee' => 'Tuition', 'year' => '2026/2027']));

        $this->post(route('admin.assignment-history.transfer', $twin), ['target' => 'credit', 'amount' => 30000, 'reason' => 'Tuition billed twice'])
            ->assertSessionHas('success');
        $this->assertEquals(0, (float) $twin->fresh()->paid_amount);
        $this->assertEquals(30000, StudentCredit::availableFor($this->thisYear->id));

        $this->delete(route('admin.assignment-history.destroy', $twin), ['reason' => 'Tuition billed twice'])->assertSessionHas('success');
        $this->assertNull($twin->fresh());
        $this->assertSame(1, AuditLog::where('action', 'fee_removed')->count());
    }

    public function test_money_moved_between_fees_moves_the_revenue_too(): void
    {
        $fy = FiscalYear::create(['branch_id' => $this->branch->id, 'name' => 'FY2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true, 'is_closed' => false]);
        AccountingPeriod::create(['branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id, 'name' => 'October 2026', 'period_number' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'is_closed' => false]);
        $account = fn ($code, $class, $normal) => ChartOfAccount::create(['branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => $code, 'class_number' => $class, 'account_type' => 'detail', 'normal_balance' => $normal, 'is_active' => true]);
        $cash = $account('571', 5, 'debit');
        $tuitionRevenue = $account('7061', 7, 'credit');
        $ptaRevenue = $account('7068', 7, 'credit');
        foreach ([[$this->tuition, $tuitionRevenue], [$this->pta, $ptaRevenue]] as [$cat, $rev]) {
            DefaultAccountMapping::create(['branch_id' => $this->branch->id, 'mapping_type' => 'fee_payment', 'category_id' => $cat->id, 'debit_account_id' => $cash->id, 'credit_account_id' => $rev->id, 'status' => 'active']);
        }
        $ptaFee = $this->fee($this->thisYear, $this->pta, 5000);

        $this->pay(10000, $this->tuitionFee);
        $this->post(route('admin.assignment-history.transfer', $this->tuitionFee), ['target' => $ptaFee->id, 'amount' => 4000, 'reason' => 'Parent meant the PTA levy'])
            ->assertSessionHasNoErrors()->assertSessionMissing('error')->assertSessionHas('success');

        $net = fn ($acc, $side) => (float) JournalEntryLine::where('account_id', $acc->id)->sum($side) - (float) JournalEntryLine::where('account_id', $acc->id)->sum($side === 'credit' ? 'debit' : 'credit');
        $this->assertEquals(6000, $net($tuitionRevenue, 'credit'));
        $this->assertEquals(4000, $net($ptaRevenue, 'credit'));
        $this->assertEquals(10000, $net($cash, 'debit'), 'no cash came or went');
        $this->assertEquals(4000, (float) $ptaFee->fresh()->paid_amount);
        $this->assertEquals(54000, (float) $this->tuitionFee->fresh()->balance);
    }

    public function test_quick_assign_override_follows_the_same_rules(): void
    {
        $this->pay(10000, $this->tuitionFee);

        $this->post(route('admin.quick-assign.store'), [
            'student_enrollment_id' => $this->thisYear->id, 'fee_category_id' => $this->tuition->id,
            'amount' => 30000, 'override' => 1,
        ])->assertSessionHas('error');
        $this->assertEquals(60000, (float) $this->tuitionFee->fresh()->original_amount);
    }

    public function test_removing_one_of_two_discounts_leaves_the_other_exact(): void
    {
        foreach ([['percentage', 15], ['fixed', 5000]] as [$type, $value]) {
            $this->post(route('admin.fee-discounts.store'), [
                'student_enrollment_id' => $this->thisYear->id, 'discount_type' => $type, 'value' => $value,
                'reason' => 'sibling', 'apply_to' => 'specific_category', 'fee_category_id' => $this->tuition->id,
            ])->assertSessionHasNoErrors();
        }
        $this->assertEquals(14000, (float) $this->tuitionFee->fresh()->discount_amount);

        $this->delete(route('admin.fee-discounts.destroy', FeeDiscount::where('discount_type', 'fixed')->first()));
        $this->assertEquals(9000, (float) $this->tuitionFee->fresh()->discount_amount);
    }

    // ── Verification ───────────────────────────────────────────────────

    public function test_a_reference_already_paid_must_be_checked_before_approval(): void
    {
        app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->thisYear->id, 'amount' => 10000, 'payment_method' => 'mtn_momo',
            'payment_date' => '2026-10-01', 'transaction_ref' => 'MP261001.1234',
        ]);
        $submission = ParentPaymentSubmission::create([
            'branch_id' => $this->branch->id, 'guardian_id' => $this->student->guardian_id,
            'student_enrollment_id' => $this->thisYear->id, 'amount' => 10000, 'payment_method' => 'mtn_momo',
            'transaction_ref' => 'MP261001.1234', 'payment_date' => '2026-10-02', 'receipt_path' => 'r.jpg', 'status' => 'pending',
        ]);

        $this->get(route('admin.parent-payments.show', $submission))->assertSee(__('This reference has already been used:'));
        $this->post(route('admin.parent-payments.approve', $submission))->assertSessionHas('error');
        $this->assertTrue($submission->fresh()->isPending());

        $this->post(route('admin.parent-payments.approve', $submission), ['reference_checked' => 1])->assertSessionHas('success');
        $this->assertFalse($submission->fresh()->isPending());
    }

    // ── Expenses ───────────────────────────────────────────────────────

    public function test_a_category_with_expenses_cannot_be_deleted(): void
    {
        $category = ExpenseCategory::create(['title' => 'Chalk', 'slug' => 'chalk', 'status' => true]);
        Expense::create(['category_id' => $category->id, 'title' => 'Chalk box', 'amount' => 2500, 'date' => '2026-10-01', 'status' => true]);

        $this->delete(route('admin.account.expense-category.destroy', $category))->assertSessionHas('error');
        $this->assertNotNull($category->fresh());
        $this->assertSame(1, Expense::count());
    }
}
