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
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use App\Services\PaymentReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counter, as the reference system runs it: the bursar sees where a lump
 * sum goes before it is recorded, money beyond what is owed becomes credit
 * only when the family agrees, a reversed receipt can never pass for money
 * received, and anyone holding a receipt can check it with the school.
 */
class FeeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentEnrollment $enrollment;
    private StudentFee $tuition;
    private StudentFee $pta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        $pta = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'PTA Levy', 'code' => 'PTA', 'is_tuition' => false, 'is_active' => true, 'is_mandatory' => true]);

        $guardian = Guardian::create(['branch_id' => $this->branch->id, 'guardian_name' => 'Pat Parent', 'guardian_phone' => '677000000', 'emergency_contact_name' => 'Pat Parent', 'emergency_contact_phone' => '677000000']);
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS260001', 'first_name' => 'Bih', 'last_name' => 'NGWA', 'date_of_birth' => '2014-05-15', 'gender' => 'female', 'guardian_id' => $guardian->id, 'status' => 'active', 'admission_date' => '2026-09-07']);
        $this->enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $year->id, 'term_id' => $term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);

        $fee = fn (FeeCategory $category, int $amount) => StudentFee::create([
            'branch_id' => $this->branch->id, 'student_enrollment_id' => $this->enrollment->id, 'fee_category_id' => $category->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount, 'status' => 'unpaid',
        ]);
        $this->tuition = $fee($tuition, 50000);
        $this->pta = $fee($pta, 10000);
    }

    private function collect(int $amount, array $extra = [])
    {
        return $this->postJson(route('admin.collect-fees.store-payment'), [
            'student_fee_id' => $this->pta->id, 'amount' => $amount,
            'payment_method' => 'cash', 'payment_date' => '2026-10-01',
        ] + $extra);
    }

    // ── Where the money goes ──────────────────────────────────────────────

    public function test_the_split_is_shown_before_anything_is_recorded(): void
    {
        $this->getJson(route('admin.collect-fees.split-preview', ['student_fee_id' => $this->pta->id, 'amount' => 30000]))
            ->assertOk()
            ->assertJsonPath('lines.0.fee', 'PTA Levy')
            ->assertJsonPath('lines.0.amount', 10000)
            ->assertJsonPath('lines.0.after', 0)
            ->assertJsonPath('lines.1.fee', 'Tuition')
            ->assertJsonPath('lines.1.amount', 20000)
            ->assertJsonPath('lines.1.after', 30000)
            ->assertJsonPath('credit', 0);

        $this->assertSame(0, Payment::count());
        $this->assertEquals(10000, (float) $this->pta->fresh()->balance);
    }

    public function test_what_is_recorded_is_what_was_shown(): void
    {
        $shown = app(PaymentRecorder::class)->plan($this->enrollment->id, 30000, $this->pta->id);

        $data = $this->collect(30000)->assertOk()->json();

        $payment = Payment::with('allocations')->findOrFail($data['payment_id']);
        $this->assertSame(
            collect($shown['lines'])->map(fn ($l) => [$l['fee']->id, $l['amount']])->all(),
            $payment->allocations->map(fn ($a) => [$a->student_fee_id, (float) $a->amount])->all()
        );

        // Both fees it reached come back, so the screen redraws both rows.
        $this->assertEqualsCanonicalizing([$this->pta->id, $this->tuition->id], array_column($data['fees'], 'id'));
        $this->assertEquals(30000, (float) $this->tuition->fresh()->balance);
    }

    // ── Credit only when the family agrees ────────────────────────────────

    public function test_money_beyond_what_is_owed_is_refused_until_the_family_agrees(): void
    {
        $this->collect(70000)
            ->assertStatus(422)
            ->assertJsonValidationErrors('accept_credit');
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, StudentCredit::count());

        $this->collect(70000, ['accept_credit' => '1'])->assertOk();

        $credit = StudentCredit::sole();
        $this->assertEquals(10000, (float) $credit->amount);
        $this->assertEquals(0, (float) $this->tuition->fresh()->balance);
    }

    public function test_an_exact_payment_needs_no_agreement(): void
    {
        $this->collect(60000)->assertOk();
        $this->assertSame(0, StudentCredit::count());
    }

    public function test_the_payments_screen_asks_for_agreement_after_refusing(): void
    {
        $this->from(route('admin.payments.create', ['student_id' => $this->enrollment->student_id]))
            ->post(route('admin.payments.store'), [
                'student_enrollment_id' => $this->enrollment->id, 'amount' => 65000,
                'payment_method' => 'cash', 'payment_date' => '2026-10-01',
            ])
            ->assertSessionHasErrors('accept_credit');
        $this->assertSame(0, Payment::count());

        $this->post(route('admin.payments.store'), [
            'student_enrollment_id' => $this->enrollment->id, 'amount' => 65000,
            'payment_method' => 'cash', 'payment_date' => '2026-10-01', 'accept_credit' => '1',
        ])->assertRedirect();
        $this->assertEquals(5000, (float) StudentCredit::sole()->amount);
    }

    public function test_approving_a_parent_payment_that_is_too_much_needs_the_agreement(): void
    {
        $submission = ParentPaymentSubmission::create([
            'branch_id' => $this->branch->id, 'guardian_id' => $this->enrollment->student->guardian_id,
            'student_enrollment_id' => $this->enrollment->id, 'student_fee_id' => $this->pta->id,
            'amount' => 64000, 'payment_method' => 'mtn_momo', 'transaction_ref' => 'MP261002.0001',
            'payment_date' => '2026-10-02', 'receipt_path' => 'r.jpg', 'status' => 'pending',
        ]);

        $this->get(route('admin.parent-payments.show', $submission))
            ->assertOk()
            ->assertSee('name="accept_credit"', false)
            ->assertSee('4 000');

        $this->post(route('admin.parent-payments.approve', $submission))->assertSessionHasErrors('accept_credit');
        $this->assertTrue($submission->fresh()->isPending());

        $this->post(route('admin.parent-payments.approve', $submission), ['accept_credit' => '1'])->assertRedirect();
        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertEquals(4000, (float) StudentCredit::sole()->amount);
    }

    // ── Receipts that cannot lie ──────────────────────────────────────────

    public function test_every_receipt_carries_a_check_code(): void
    {
        $payment = Payment::findOrFail($this->collect(20000)->json('payment_id'));

        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{12}$/', $payment->verify_code);
        $this->get(route('admin.collect-fees.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->verifyCodeForPrint())
            ->assertSee(route('receipt.verify'))
            ->assertDontSee(__('REVERSED'));
    }

    public function test_the_receipt_qr_code_opens_its_check_page(): void
    {
        $payment = Payment::findOrFail($this->collect(20000)->json('payment_id'));

        $html = $this->get(route('admin.collect-fees.receipt', $payment))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, '<span class="verify-qr" aria-hidden="true"><svg'), 'one QR on each copy');

        // Drawn as an image with the same settings and read back by a
        // scanner, the code gives the receipt's own check address.
        $png = (new \chillerlan\QRCode\QRCode(new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\Output\QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
            'outputBase64' => false,
            'scale' => 6,
        ])))->render($payment->verifyUrl());
        $read = (new \chillerlan\QRCode\QRCode())->readFromBlob($png);

        $this->assertSame($payment->verifyUrl(), (string) $read);
        $this->assertStringContainsString(\App\Support\QrCode::svg($payment->verifyUrl()), $html);
    }

    public function test_an_older_receipt_gets_its_code_when_printed_again(): void
    {
        $payment = Payment::findOrFail($this->collect(20000)->json('payment_id'));
        $payment->forceFill(['verify_code' => null])->saveQuietly();

        $this->get(route('admin.collect-fees.receipt', $payment))->assertOk();

        $this->assertNotNull($payment->fresh()->verify_code);
    }

    public function test_a_reversed_receipt_prints_as_reversed(): void
    {
        $payment = Payment::findOrFail($this->collect(20000)->json('payment_id'));
        app(PaymentReversalService::class)->reverse($payment, 'Keyed against the wrong pupil');

        $this->get(route('admin.collect-fees.receipt', $payment))
            ->assertOk()
            ->assertSee(__('REVERSED on :date', ['date' => now()->format('d/m/Y')]))
            ->assertSee(__('this receipt no longer counts as payment.'))
            ->assertSee('Keyed against the wrong pupil')
            ->assertSee('is-reversed', false);
    }

    public function test_anyone_can_check_a_receipt_without_signing_in(): void
    {
        $payment = Payment::findOrFail($this->collect(20000)->json('payment_id'));
        auth()->logout();

        $this->get(route('receipt.verify', $payment->verify_code))
            ->assertOk()
            ->assertSee(__('Genuine receipt, recorded by the school.'))
            ->assertSee($payment->receipt_number)
            ->assertSee('20 000')
            ->assertSee('Bih');

        // Typed in by hand, with dashes and small letters.
        $this->get(route('receipt.verify').'?code='.urlencode(' '.strtolower($payment->verifyCodeForPrint())))
            ->assertRedirect(route('receipt.verify', $payment->verify_code));
        $this->get(route('receipt.verify', strtolower($payment->verifyCodeForPrint())))
            ->assertOk()->assertSee($payment->receipt_number);

        $this->get(route('receipt.verify', 'ZZZZZZZZZZZZ'))
            ->assertNotFound()
            ->assertSee(__('No receipt has this code. Check the code and try again, or ask the school.'));

        $this->get(route('receipt.verify'))->assertOk()->assertSee('name="code"', false);
    }

    public function test_the_check_says_a_reversed_receipt_no_longer_counts_but_not_why(): void
    {
        $payment = Payment::findOrFail($this->collect(20000)->json('payment_id'));
        app(PaymentReversalService::class)->reverse($payment, 'Cheque bounced at the bank');
        auth()->logout();

        $this->get(route('receipt.verify', $payment->verify_code))
            ->assertOk()
            ->assertSee(__('It no longer counts as payment.'), false)
            ->assertDontSee(__('Genuine receipt, recorded by the school.'))
            ->assertDontSee('Cheque bounced');
    }

    // ── Several at once ───────────────────────────────────────────────────

    public function test_receipts_ticked_on_the_list_print_together(): void
    {
        $first = Payment::findOrFail($this->collect(5000)->json('payment_id'));
        $second = Payment::findOrFail($this->collect(5000)->json('payment_id'));

        $this->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('data-bulk-scope', false)
            ->assertSee('value="'.$first->id.'" data-bulk-item', false)
            ->assertSee(route('admin.collect-fees.receipts'), false);

        $this->get(route('admin.collect-fees.receipts', ['ids' => [$first->id, $second->id]]))
            ->assertOk()
            ->assertSeeInOrder([$first->receipt_number, $second->receipt_number])
            ->assertSee($first->verifyCodeForPrint())
            ->assertSee($second->verifyCodeForPrint());

        $this->get(route('admin.collect-fees.receipts'))->assertNotFound();
    }
}
