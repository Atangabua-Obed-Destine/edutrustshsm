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
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountType;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentCreditRefund;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentAccountService;
use App\Services\PaymentRecorder;
use App\Services\PaymentReversalService;
use App\Services\StudentCreditRefundService;
use App\Services\StudentCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Money held on a student's account could only ever be spent on fees — a family
 * leaving the school had no way to get it back. The lifecycle follows the
 * reference system: request, approve or reject, then process.
 */
class StudentCreditRefundTest extends TestCase
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

        $this->cash = $this->account('571', 5, 'debit', 'Cash Box');
        $this->revenue = $this->account('706', 7, 'credit', 'Fee Revenue');
        $this->advances = $this->account('419', 4, 'credit', 'Student Advances');

        $this->map('fee_payment', $this->cash, $this->revenue);
        $this->map('student_credit', $this->cash, $this->advances);
        $this->map('credit_applied', $this->advances, $this->revenue);
        $this->map('credit_refund', $this->advances, $this->cash);

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

    private function pay(float $amount): Payment
    {
        return app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);
    }

    /** A 5,000 credit from paying 15,000 against a 10,000 fee. */
    private function credit(): StudentCredit
    {
        $this->fee(10000);
        $this->pay(15000);

        return StudentCredit::firstOrFail();
    }

    private function refunds(): StudentCreditRefundService
    {
        return app(StudentCreditRefundService::class);
    }

    private function approved(StudentCredit $credit, float $amount): StudentCreditRefund
    {
        return $this->refunds()->approve($this->refunds()->request($credit, $amount, 'Student left the school'));
    }

    private function cashAccount(float $balance): PaymentAccount
    {
        $type = PaymentAccountType::firstOrCreate(
            ['slug' => 'cash'], ['branch_id' => $this->branch->id, 'title' => 'Cash', 'status' => true]
        );
        $account = PaymentAccount::create([
            'branch_id' => $this->branch->id, 'title' => 'Main Cash', 'account_type_id' => $type->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => true,
        ]);

        if ($balance > 0) {
            app(PaymentAccountService::class)->credit($account, $balance, ['reference_type' => 'deposit']);
        }

        return $account->fresh();
    }

    private function net(ChartOfAccount $account): float
    {
        return round((float) JournalEntryLine::where('account_id', $account->id)->sum('debit')
            - (float) JournalEntryLine::where('account_id', $account->id)->sum('credit'), 2);
    }

    // ------------------------------------------------------------ request

    public function test_a_refund_is_requested(): void
    {
        $credit = $this->credit();

        $refund = $this->refunds()->request($credit, 5000, 'Student left the school');

        $this->assertSame('requested', $refund->status);
        $this->assertEquals(5000, (float) $refund->amount);
        // Requesting moves no money.
        $this->assertEquals(5000, (float) $credit->fresh()->balance);
    }

    public function test_more_than_the_credit_cannot_be_requested(): void
    {
        $credit = $this->credit();

        $this->expectExceptionMessage('can still be refunded');
        $this->refunds()->request($credit, 6000, 'Too much');
    }

    public function test_open_requests_reserve_their_amount(): void
    {
        $credit = $this->credit();
        $this->refunds()->request($credit, 3000, 'First part');

        // Otherwise two people could each request the whole balance and both
        // be approved.
        $this->assertEquals(2000, $this->refunds()->refundable($credit->fresh()));

        $this->expectException(RuntimeException::class);
        $this->refunds()->request($credit->fresh(), 3000, 'Second part');
    }

    // ------------------------------------------------------------ review

    public function test_only_a_requested_refund_can_be_approved(): void
    {
        $refund = $this->approved($this->credit(), 5000);

        $this->expectExceptionMessage('Only a requested refund');
        $this->refunds()->approve($refund);
    }

    public function test_rejecting_releases_the_reserved_amount(): void
    {
        $credit = $this->credit();
        $refund = $this->refunds()->request($credit, 5000, 'Student left');

        $this->refunds()->reject($refund, 'Family is staying next year');

        $refund->refresh();
        $this->assertSame('rejected', $refund->status);
        $this->assertSame('Family is staying next year', $refund->rejection_reason);
        $this->assertEquals(5000, $this->refunds()->refundable($credit->fresh()));
        $this->assertEquals(5000, (float) $credit->fresh()->balance);
    }

    // ------------------------------------------------------------ process

    public function test_an_unapproved_refund_cannot_be_paid(): void
    {
        $refund = $this->refunds()->request($this->credit(), 5000, 'Student left');

        $this->expectExceptionMessage('has to be approved');
        $this->refunds()->process($refund, 'cash');
    }

    public function test_paying_a_refund_reduces_the_credit(): void
    {
        $credit = $this->credit();
        $refund = $this->approved($credit, 2000);

        $result = $this->refunds()->process($refund, 'cash', 'RCPT-9');

        $credit->refresh();
        $this->assertSame('processed', $result['refund']->status);
        $this->assertEquals(2000, (float) $credit->refunded_amount);
        $this->assertEquals(3000, (float) $credit->balance);
    }

    public function test_the_refund_clears_the_liability_against_cash(): void
    {
        $credit = $this->credit();

        $this->refunds()->process($this->approved($credit, 5000), 'cash');

        // 15,000 received, 5,000 handed back: the advances liability is gone and
        // cash holds only what was earned.
        $this->assertEquals(0.0, $this->net($this->advances));
        $this->assertEquals(10000.0, $this->net($this->cash));
        $this->assertEquals(-10000.0, $this->net($this->revenue));
    }

    public function test_the_money_leaves_the_named_payment_account(): void
    {
        $credit = $this->credit();
        $account = $this->cashAccount(20000);

        $this->refunds()->process($this->approved($credit, 5000), 'cash', null, $account->id);

        $this->assertEquals(15000, (float) $account->fresh()->current_balance);
        $this->assertSame(1, $account->transactions()->where('reference_type', 'credit_refund')->count());
    }

    public function test_an_account_that_cannot_cover_it_stops_the_whole_refund(): void
    {
        $credit = $this->credit();
        $account = $this->cashAccount(1000);
        $refund = $this->approved($credit, 5000);

        try {
            $this->refunds()->process($refund, 'cash', null, $account->id);
            $this->fail('A refund larger than the account balance was paid.');
        } catch (RuntimeException) {
            // expected
        }

        // Nothing half-done: the credit is untouched and the refund still waits.
        $this->assertEquals(5000, (float) $credit->fresh()->balance);
        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertEquals(1000, (float) $account->fresh()->current_balance);
    }

    public function test_credit_applied_after_approval_is_not_refunded_twice(): void
    {
        $credit = $this->credit();
        $refund = $this->approved($credit, 5000);

        // Meanwhile the credit pays a new 4,000 fee.
        $this->fee(4000);
        app(StudentCreditService::class)->apply($this->enrollment);

        $this->expectExceptionMessage('has been applied to fees since');
        $this->refunds()->process($refund, 'cash');
    }

    public function test_applying_credit_after_a_partial_refund_uses_only_what_is_left(): void
    {
        $credit = $this->credit();
        $this->refunds()->process($this->approved($credit, 3000), 'cash');

        $this->fee(4000);
        app(StudentCreditService::class)->apply($this->enrollment);

        $credit->refresh();
        $this->assertEquals(2000, (float) $credit->used_amount);
        $this->assertEquals(0, (float) $credit->balance);
    }

    public function test_a_refund_with_no_ledger_mapping_is_paid_but_reported(): void
    {
        DefaultAccountMapping::where('mapping_type', 'credit_refund')->delete();
        $credit = $this->credit();

        $result = $this->refunds()->process($this->approved($credit, 5000), 'cash');

        $this->assertFalse($result['posted']);
        $this->assertSame('processed', $result['refund']->status);
    }

    public function test_a_receipt_whose_credit_was_refunded_cannot_be_reversed(): void
    {
        $credit = $this->credit();
        $this->refunds()->process($this->approved($credit, 2000), 'cash');

        // Money already handed back to the family cannot be un-received.
        $this->expectExceptionMessage('already been refunded');
        app(PaymentReversalService::class)->reverse(Payment::firstOrFail(), 'Wrong student');
    }

    // ------------------------------------------------------------ the screens

    public function test_the_full_lifecycle_through_the_screens(): void
    {
        $credit = $this->credit();

        $this->get(route('admin.student-credits.show', $credit))->assertSuccessful();

        $this->post(route('admin.student-credits.refunds.request', $credit), [
            'amount' => 5000, 'reason' => 'Student left the school',
        ])->assertSessionHas('success');

        $refund = StudentCreditRefund::firstOrFail();
        $this->get(route('admin.student-credits.index'))->assertSuccessful()->assertSee('Refund pending');

        $this->post(route('admin.student-credits.refunds.approve', $refund))->assertSessionHas('success');

        $this->post(route('admin.student-credits.refunds.process', $refund), [
            'method' => 'bank_transfer', 'reference' => 'TRF-001',
        ])->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertSame('processed', $refund->fresh()->status);
        $this->get(route('admin.student-credits.show', $credit))->assertSuccessful()->assertSee('TRF-001');
    }

    public function test_the_screen_warns_when_the_refund_did_not_reach_the_ledger(): void
    {
        DefaultAccountMapping::where('mapping_type', 'credit_refund')->delete();
        $refund = $this->approved($this->credit(), 5000);

        $this->post(route('admin.student-credits.refunds.process', $refund), ['method' => 'cash'])
            ->assertSessionHas('success')
            ->assertSessionHas('error');
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $refund = $this->refunds()->request($this->credit(), 5000, 'Student left');

        $this->post(route('admin.student-credits.refunds.reject', $refund), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame('requested', $refund->fresh()->status);
    }

    public function test_approving_needs_the_permission(): void
    {
        $refund = $this->refunds()->request($this->credit(), 5000, 'Student left');

        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)->post(route('admin.student-credits.refunds.approve', $refund))->assertForbidden();
        $this->assertSame('requested', $refund->fresh()->status);
    }

    public function test_the_refund_rule_can_be_configured_on_the_mapping_screen(): void
    {
        $this->get(route('admin.account-mappings.index'))->assertSuccessful()->assertSee('credit_refund', false);
    }
}
