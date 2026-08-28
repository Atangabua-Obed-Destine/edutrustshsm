<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recording a payment used to exist in three divergent copies — PaymentRecorder,
 * FeeCollectionController and PaymentController. Only one advanced payment
 * plans, and only one capped an allocation at the fee balance. These cover the
 * single path they now all share.
 */
class PaymentRecorderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentEnrollment $enrollment;
    private FeeCategory $tuition;
    private FeeCategory $boarding;

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

        $this->tuition = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true,
        ]);
        $this->boarding = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Boarding', 'code' => 'BRD', 'is_active' => true,
        ]);
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

    private function recorder(): PaymentRecorder
    {
        return app(PaymentRecorder::class);
    }

    public function test_a_payment_is_stamped_with_the_active_branch(): void
    {
        $this->fee($this->tuition, 10000);

        $payment = $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $this->assertSame($this->branch->id, $payment->branch_id);
    }

    public function test_a_targeted_payment_pays_that_fee_first_then_spills_over(): void
    {
        $tuition = $this->fee($this->tuition, 10000);
        $boarding = $this->fee($this->boarding, 8000);

        $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $boarding->id,
            'amount' => 12000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $boarding->refresh();
        $tuition->refresh();

        $this->assertEquals(0, (float) $boarding->balance, 'the targeted fee is settled first');
        $this->assertEquals(6000, (float) $tuition->balance, 'the remainder spills over');
    }

    public function test_no_fee_can_be_driven_negative(): void
    {
        $tuition = $this->fee($this->tuition, 10000);

        $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 50000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $tuition->refresh();
        $this->assertEquals(0, (float) $tuition->balance);
        $this->assertEquals(10000, (float) $tuition->paid_amount);
    }

    public function test_an_operator_split_is_honoured_and_the_remainder_still_lands(): void
    {
        $tuition = $this->fee($this->tuition, 10000);
        $boarding = $this->fee($this->boarding, 8000);

        // Explicitly send 3,000 to boarding; the other 9,000 must not vanish.
        $payment = $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 12000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
            'allocations' => [
                ['fee_id' => $boarding->id, 'amount' => 3000],
            ],
        ]);

        $boarding->refresh();
        $tuition->refresh();

        $this->assertEquals(3000, (float) $boarding->paid_amount);
        $this->assertEquals(9000, (float) $tuition->paid_amount, 'the rest of the payment must still be allocated');
        $this->assertEquals(12000, (float) $payment->allocations()->sum('amount'));
    }

    public function test_a_payment_plan_advances_by_what_its_own_fee_received(): void
    {
        $tuition = $this->fee($this->tuition, 10000);
        $this->fee($this->boarding, 8000);

        $plan = PaymentPlan::create([
            'branch_id' => $this->branch->id,
            'student_fee_id' => $tuition->id,
            'total_amount' => 10000,
            'number_of_installments' => 2,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        foreach ([1, 2] as $n) {
            PaymentPlanInstallment::create([
                'branch_id' => $this->branch->id,
                'payment_plan_id' => $plan->id,
                'installment_number' => $n,
                'amount' => 5000,
                'paid_amount' => 0,
                'due_date' => '2026-0'.$n.'-01',
                'status' => 'pending',
            ]);
        }

        // Target boarding, so only the 4,000 spill-over reaches the tuition fee.
        $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $this->boarding->id ? StudentFee::where('fee_category_id', $this->boarding->id)->value('id') : null,
            'amount' => 12000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $plan->refresh();
        $first = $plan->installments()->where('installment_number', 1)->first();
        $second = $plan->installments()->where('installment_number', 2)->first();

        // 12,000 - 8,000 boarding = 4,000 to tuition. The plan must be credited
        // 4,000, NOT the full 12,000 as the old collection screen did.
        $this->assertEquals(4000, (float) $first->paid_amount);
        $this->assertSame('partial', $first->status);
        $this->assertEquals(0, (float) $second->paid_amount);
        $this->assertSame('active', $plan->status);
    }

    public function test_a_plan_completes_only_when_its_instalments_are_paid(): void
    {
        $tuition = $this->fee($this->tuition, 10000);

        $plan = PaymentPlan::create([
            'branch_id' => $this->branch->id,
            'student_fee_id' => $tuition->id,
            'total_amount' => 10000,
            'number_of_installments' => 2,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        foreach ([1, 2] as $n) {
            PaymentPlanInstallment::create([
                'branch_id' => $this->branch->id,
                'payment_plan_id' => $plan->id,
                'installment_number' => $n,
                'amount' => 5000, 'paid_amount' => 0,
                'due_date' => '2026-0'.$n.'-01', 'status' => 'pending',
            ]);
        }

        $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $tuition->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $plan->refresh();
        $this->assertSame('completed', $plan->status);
        $this->assertSame(2, $plan->installments()->where('status', 'paid')->count());
    }

    public function test_an_all_cancelled_plan_is_not_treated_as_completed(): void
    {
        $tuition = $this->fee($this->tuition, 10000);

        $plan = PaymentPlan::create([
            'branch_id' => $this->branch->id,
            'student_fee_id' => $tuition->id,
            'total_amount' => 10000,
            'number_of_installments' => 1,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        PaymentPlanInstallment::create([
            'branch_id' => $this->branch->id,
            'payment_plan_id' => $plan->id,
            'installment_number' => 1,
            'amount' => 5000, 'paid_amount' => 0,
            'due_date' => '2026-01-01', 'status' => 'cancelled',
        ]);

        $this->recorder()->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $tuition->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $plan->refresh();

        // Counting "nothing outstanding" marked an all-cancelled plan complete.
        $this->assertSame('active', $plan->status);
    }

    public function test_receipt_numbers_are_sequential_and_unique(): void
    {
        $this->fee($this->tuition, 100000);

        $numbers = [];
        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->recorder()->record([
                'student_enrollment_id' => $this->enrollment->id,
                'amount' => 1000,
                'payment_method' => 'cash',
                'payment_date' => '2026-01-15',
            ])->receipt_number;
        }

        $this->assertSame($numbers, array_unique($numbers));
        $this->assertCount(5, Payment::all());
    }
}
