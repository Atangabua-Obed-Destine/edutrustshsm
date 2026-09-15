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
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ParentPaymentSubmission;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountType;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentAccountService;
use App\Services\PaymentRecorder;
use App\Services\PaymentReversalService;
use App\Services\StudentCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A payment recorded in error could not be undone. Recording one moves money
 * through the fee, any payment plan, any over-payment credit, the payment
 * account and the ledger — and there was no way to put all of that back
 * together. Behaviour mirrors the reference system's PaymentReversalService.
 */
class PaymentReversalTest extends TestCase
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

    private function pay(float $amount, ?int $feeId = null): Payment
    {
        return app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $feeId,
            'amount' => $amount,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);
    }

    private function reverse(Payment $payment, string $reason = 'Recorded against the wrong student'): Payment
    {
        return app(PaymentReversalService::class)->reverse($payment, $reason);
    }

    /** Net movement on an account: debits minus credits. */
    private function net(ChartOfAccount $account): float
    {
        return round((float) JournalEntryLine::where('account_id', $account->id)->sum('debit')
            - (float) JournalEntryLine::where('account_id', $account->id)->sum('credit'), 2);
    }

    // ------------------------------------------------------------ the fee

    public function test_the_fee_goes_back_to_what_it_was(): void
    {
        $fee = $this->fee(10000);
        $payment = $this->pay(10000);

        $this->assertSame('paid', $fee->fresh()->status);

        $this->reverse($payment);

        $fee->refresh();
        $this->assertEquals(0, (float) $fee->paid_amount);
        $this->assertEquals(10000, (float) $fee->balance);
        $this->assertSame('unpaid', $fee->status);
    }

    public function test_other_payments_on_the_same_fee_are_untouched(): void
    {
        $fee = $this->fee(10000);
        $this->pay(3000);
        $mistake = $this->pay(4000);

        $this->reverse($mistake);

        // Recomputed from what remains, not by subtraction.
        $fee->refresh();
        $this->assertEquals(3000, (float) $fee->paid_amount);
        $this->assertEquals(7000, (float) $fee->balance);
        $this->assertSame('partial', $fee->status);
    }

    public function test_a_payment_split_across_fees_restores_every_fee(): void
    {
        $tuition = $this->fee(6000);
        $levy = $this->fee(4000);
        $payment = $this->pay(10000);

        $this->reverse($payment);

        $this->assertEquals(6000, (float) $tuition->fresh()->balance);
        $this->assertEquals(4000, (float) $levy->fresh()->balance);
    }

    // ------------------------------------------------------------ the record

    public function test_the_payment_is_kept_and_marked_not_deleted(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);

        $this->reverse($payment, 'Cheque bounced');

        $payment->refresh();
        $this->assertSame('reversed', $payment->verification_status);
        $this->assertSame('Cheque bounced', $payment->reversal_reason);
        $this->assertNotNull($payment->reversed_at);
        $this->assertSame(auth()->id(), $payment->reversed_by);
        $this->assertSame(1, $payment->allocations()->count(), 'allocations survive as history');
    }

    public function test_a_payment_cannot_be_reversed_twice(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);
        $this->reverse($payment);

        $this->expectExceptionMessage('already been reversed');
        $this->reverse($payment->fresh());
    }

    // ------------------------------------------------------------ the ledger

    public function test_the_ledger_nets_to_zero_and_keeps_the_original_entry(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);

        $entriesBefore = JournalEntry::count();

        $this->reverse($payment);

        $this->assertEquals(0.0, $this->net($this->cash));
        $this->assertEquals(0.0, $this->net($this->revenue));

        // An opposing entry, not a deletion — the audit trail survives.
        $this->assertSame($entriesBefore + 1, JournalEntry::count());
        $this->assertTrue((bool) JournalEntry::whereNull('reversed_entry_id')->firstOrFail()->is_reversed);
    }

    public function test_a_split_payment_reverses_every_allocation_posting(): void
    {
        $this->fee(6000);
        $this->fee(4000);
        $payment = $this->pay(10000);

        $this->reverse($payment);

        $this->assertEquals(0.0, $this->net($this->cash));
        $this->assertEquals(0.0, $this->net($this->revenue));
    }

    // ------------------------------------------------------------ credit

    public function test_an_unspent_overpayment_credit_is_cancelled(): void
    {
        $this->fee(10000);
        $payment = $this->pay(15000);

        $this->assertEquals(5000, StudentCredit::availableFor($this->enrollment->id));

        $this->reverse($payment);

        $this->assertEquals(0, StudentCredit::availableFor($this->enrollment->id));
        // The liability it created is gone from the ledger too.
        $this->assertEquals(0.0, $this->net($this->advances));
        $this->assertEquals(0.0, $this->net($this->cash));
    }

    public function test_a_payment_whose_credit_was_already_spent_is_refused(): void
    {
        $this->fee(10000);
        $overpaid = $this->pay(15000);     // 5,000 credit
        $this->fee(4000);
        app(StudentCreditService::class)->apply($this->enrollment); // spends 4,000 of it

        // Cancelling the credit would leave the 4,000 fee paid with money that
        // no longer exists.
        $this->expectExceptionMessage('Reverse those receipts first');
        $this->reverse($overpaid);
    }

    public function test_reversing_a_credit_application_gives_the_credit_back(): void
    {
        $this->fee(10000);
        $this->pay(15000);                 // 5,000 credit
        $later = $this->fee(4000);

        $applied = app(StudentCreditService::class)->apply($this->enrollment);
        $this->assertEquals(1000, StudentCredit::availableFor($this->enrollment->id));

        $this->reverse($applied);

        $this->assertEquals(5000, StudentCredit::availableFor($this->enrollment->id));
        $this->assertEquals(4000, (float) $later->fresh()->balance);
        // The DR 419 / CR revenue posting is undone; the original cash stands.
        $this->assertEquals(-5000.0, $this->net($this->advances));
        $this->assertEquals(15000.0, $this->net($this->cash));
    }

    // ------------------------------------------------------------ plans

    public function test_a_payment_plan_is_rewound(): void
    {
        $fee = $this->fee(9000);

        $plan = PaymentPlan::create([
            'branch_id' => $this->branch->id, 'student_fee_id' => $fee->id,
            'total_amount' => 9000, 'number_of_installments' => 3, 'status' => 'active',
        ]);
        foreach ([1, 2, 3] as $n) {
            PaymentPlanInstallment::create([
                'branch_id' => $this->branch->id, 'payment_plan_id' => $plan->id,
                'installment_number' => $n, 'amount' => 3000,
                'due_date' => now()->addMonths($n)->toDateString(), 'status' => 'pending',
            ]);
        }

        $this->pay(3000, $fee->id);        // instalment 1
        $mistake = $this->pay(4500, $fee->id); // instalment 2 + half of 3

        $this->reverse($mistake);

        $rows = $plan->installments()->orderBy('installment_number')->get();
        $this->assertSame('paid', $rows[0]->status);
        $this->assertEquals(3000, (float) $rows[0]->paid_amount);
        $this->assertSame('pending', $rows[1]->status);
        $this->assertEquals(0, (float) $rows[1]->paid_amount);
        $this->assertSame('pending', $rows[2]->status);
    }

    public function test_a_completed_plan_is_reopened(): void
    {
        $fee = $this->fee(6000);

        $plan = PaymentPlan::create([
            'branch_id' => $this->branch->id, 'student_fee_id' => $fee->id,
            'total_amount' => 6000, 'number_of_installments' => 2, 'status' => 'active',
        ]);
        foreach ([1, 2] as $n) {
            PaymentPlanInstallment::create([
                'branch_id' => $this->branch->id, 'payment_plan_id' => $plan->id,
                'installment_number' => $n, 'amount' => 3000,
                'due_date' => now()->addMonths($n)->toDateString(), 'status' => 'pending',
            ]);
        }

        $payment = $this->pay(6000, $fee->id);
        $this->assertSame('completed', $plan->fresh()->status);

        $this->reverse($payment);

        $this->assertSame('active', $plan->fresh()->status);
    }

    // ------------------------------------------------------------ treasury

    public function test_a_linked_payment_account_is_debited_back(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);

        $type = PaymentAccountType::firstOrCreate(
            ['slug' => 'cash'], ['branch_id' => $this->branch->id, 'title' => 'Cash', 'status' => true]
        );
        $account = PaymentAccount::create([
            'branch_id' => $this->branch->id, 'title' => 'Main Cash', 'account_type_id' => $type->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => true,
        ]);

        // The receipt was linked to the account the usual way.
        app(PaymentAccountService::class)->credit($account, 10000, [
            'reference_type' => 'fee_payment', 'reference_id' => $payment->id,
        ]);
        $payment->update(['payment_account_id' => $account->id]);

        $this->reverse($payment->fresh());

        $this->assertEquals(0, (float) $account->fresh()->current_balance);
        // A new debit row, not a deletion: the account book shows both.
        $this->assertSame(2, $account->transactions()->count());
    }

    // ------------------------------------------------------------ parent portal

    public function test_a_parent_submission_is_marked_reversed(): void
    {
        $fee = $this->fee(10000);
        $payment = $this->pay(10000);

        $guardian = Guardian::create([
            'branch_id' => $this->branch->id, 'guardian_name' => 'Pat Parent',
            'guardian_email' => 'parent@example.test', 'guardian_phone' => '677000000',
            'emergency_contact_name' => 'Pat Parent', 'emergency_contact_phone' => '677000000',
        ]);
        $submission = ParentPaymentSubmission::create([
            'branch_id' => $this->branch->id, 'guardian_id' => $guardian->id,
            'student_enrollment_id' => $this->enrollment->id, 'student_fee_id' => $fee->id,
            'amount' => 10000, 'payment_method' => 'mtn_momo', 'payment_date' => '2026-01-15',
            'receipt_path' => 'receipts/test.jpg', 'status' => 'approved', 'payment_id' => $payment->id,
        ]);

        $this->reverse($payment, 'Mobile money transfer was never received');

        $submission->refresh();
        $this->assertSame('reversed', $submission->status);
        $this->assertStringContainsString('never received', (string) $submission->review_notes);
    }

    // ------------------------------------------------------------ the screen

    public function test_reversing_from_the_screen(): void
    {
        $fee = $this->fee(10000);
        $payment = $this->pay(10000);

        $this->post(route('admin.payments.reverse', $payment), ['reason' => 'Duplicate entry'])
            ->assertRedirect(route('admin.payments.show', $payment))
            ->assertSessionHas('success');

        $this->assertTrue($payment->fresh()->isReversed());
        $this->assertEquals(10000, (float) $fee->fresh()->balance);

        $this->get(route('admin.payments.show', $payment))
            ->assertSuccessful()
            ->assertSee('Duplicate entry');
    }

    public function test_the_screen_requires_a_reason(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);

        $this->post(route('admin.payments.reverse', $payment), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($payment->fresh()->isReversed());
    }

    public function test_the_screen_reports_a_refusal_instead_of_crashing(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);
        $this->reverse($payment);

        $this->post(route('admin.payments.reverse', $payment), ['reason' => 'Trying again'])
            ->assertSessionHas('error');
    }

    public function test_reversing_needs_the_permission(): void
    {
        $this->fee(10000);
        $payment = $this->pay(10000);

        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)
            ->post(route('admin.payments.reverse', $payment), ['reason' => 'No authority'])
            ->assertForbidden();

        $this->assertFalse($payment->fresh()->isReversed());
    }
}
