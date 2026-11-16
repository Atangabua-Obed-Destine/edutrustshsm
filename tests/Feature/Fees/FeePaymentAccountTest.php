<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\ParentPaymentSubmission;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\PaymentAccountTransaction;
use App\Models\PaymentAccountType;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A fee is paid INTO something: the cash box or a bank account. Receipts used
 * to be recorded without one, so the cash book matched the till only after
 * someone linked each receipt by hand on the "unlinked" screen.
 */
class FeePaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private StudentEnrollment $enrollment;
    private StudentFee $fee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);

        $guardian = Guardian::create(['branch_id' => $this->branch->id, 'guardian_name' => 'Pat Parent', 'guardian_phone' => '677000000', 'emergency_contact_name' => 'Pat Parent', 'emergency_contact_phone' => '677000000']);
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS260001', 'first_name' => 'Bih', 'last_name' => 'NGWA', 'date_of_birth' => '2014-05-15', 'gender' => 'female', 'guardian_id' => $guardian->id, 'status' => 'active', 'admission_date' => '2026-09-07']);
        $this->enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $year->id, 'term_id' => $term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $this->fee = StudentFee::create([
            'branch_id' => $this->branch->id, 'student_enrollment_id' => $this->enrollment->id, 'fee_category_id' => $tuition->id,
            'original_amount' => 60000, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => 60000, 'paid_amount' => 0, 'balance' => 60000, 'status' => 'unpaid',
        ]);
    }

    private function account(string $title = 'Main Cash', bool $active = true): PaymentAccount
    {
        $type = PaymentAccountType::firstOrCreate(['slug' => 'cash'], ['branch_id' => $this->branch->id, 'title' => 'Cash', 'status' => true]);

        return PaymentAccount::create([
            'branch_id' => $this->branch->id, 'title' => $title, 'account_type_id' => $type->id,
            'opening_balance' => 0, 'current_balance' => 0, 'status' => $active,
        ]);
    }

    private function collect(array $extra = [])
    {
        return $this->postJson(route('admin.collect-fees.store-payment'), [
            'student_fee_id' => $this->fee->id, 'amount' => 25000,
            'payment_method' => 'cash', 'payment_date' => '2026-10-01',
        ] + $extra);
    }

    public function test_collect_fees_credits_the_account_it_was_paid_into(): void
    {
        $till = $this->account();

        $this->collect(['payment_account_id' => $till->id])->assertOk();

        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame($till->id, $payment->payment_account_id);
        $this->assertEquals(25000, (float) $till->fresh()->current_balance);

        $txn = PaymentAccountTransaction::where('payment_account_id', $till->id)->sole();
        $this->assertSame('credit', $txn->transaction_type);
        $this->assertSame(PaymentAccountTransaction::REF_FEE_PAYMENT, $txn->reference_type);
        $this->assertSame($payment->id, $txn->reference_id);
        $this->assertSame('2026-10-01', $txn->transaction_date->toDateString());

        // Nothing left for anyone to link by hand.
        $this->assertSame(0, Payment::whereNull('payment_account_id')->count());
    }

    public function test_the_account_must_be_named_once_the_school_keeps_accounts(): void
    {
        $this->account();

        $this->collect()->assertStatus(422)->assertJsonValidationErrors('payment_account_id');
        $this->assertSame(0, Payment::count());
        $this->assertEquals(60000, (float) $this->fee->fresh()->balance);
    }

    public function test_a_closed_account_cannot_be_chosen(): void
    {
        $this->account();
        $closed = $this->account('Old Bank', active: false);

        $this->collect(['payment_account_id' => $closed->id])->assertStatus(422);
        $this->assertSame(0, Payment::count());
    }

    public function test_a_school_without_accounts_still_takes_fees(): void
    {
        $this->collect()->assertOk();

        $this->assertNull(Payment::latest('id')->firstOrFail()->payment_account_id);
        $this->assertEquals(35000, (float) $this->fee->fresh()->balance);
    }

    public function test_collect_fees_offers_the_open_accounts_only(): void
    {
        $this->account('Main Cash');
        $this->account('Old Bank', active: false);

        $this->get(route('admin.collect-fees.index', ['session_id' => $this->enrollment->academic_session_id]))
            ->assertOk()
            ->assertSee('name="payment_account_id"', false)
            ->assertSee('Main Cash')
            ->assertDontSee('Old Bank');
    }

    public function test_reversing_the_receipt_takes_the_money_back_out(): void
    {
        $till = $this->account();
        $this->collect(['payment_account_id' => $till->id])->assertOk();

        app(PaymentReversalService::class)->reverse(Payment::latest('id')->firstOrFail(), 'Keyed against the wrong pupil');

        $this->assertEquals(0, (float) $till->fresh()->current_balance);
        $this->assertSame(2, $till->transactions()->count());
    }

    public function test_an_approved_parent_transfer_lands_in_the_chosen_account(): void
    {
        $bank = $this->account('Afriland Bank');
        $submission = ParentPaymentSubmission::create([
            'branch_id' => $this->branch->id, 'guardian_id' => $this->enrollment->student->guardian_id,
            'student_enrollment_id' => $this->enrollment->id, 'student_fee_id' => $this->fee->id,
            'amount' => 15000, 'payment_method' => 'mtn_momo', 'transaction_ref' => 'MP261002.0001',
            'payment_date' => '2026-10-02', 'receipt_path' => 'r.jpg', 'status' => 'pending',
        ]);

        $this->get(route('admin.parent-payments.show', $submission))->assertSee('Afriland Bank');

        $this->post(route('admin.parent-payments.approve', $submission))->assertSessionHasErrors('payment_account_id');
        $this->assertTrue($submission->fresh()->isPending());

        $this->post(route('admin.parent-payments.approve', $submission), ['payment_account_id' => $bank->id])
            ->assertSessionHas('success');

        $this->assertEquals(15000, (float) $bank->fresh()->current_balance);
        $this->assertSame($bank->id, $submission->fresh()->payment->payment_account_id);
    }
}
