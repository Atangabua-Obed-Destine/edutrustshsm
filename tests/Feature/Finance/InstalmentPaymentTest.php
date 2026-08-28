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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * There was no way to pay a specific instalment: instalments only advanced as a
 * side effect of a payment recorded elsewhere.
 */
class InstalmentPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentFee $fee;
    private PaymentPlan $plan;

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
        $enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
        $category = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true,
        ]);

        $this->fee = StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => 30000, 'discount_amount' => 0, 'waiver_amount' => 0,
            'fine_amount' => 0, 'net_amount' => 30000, 'paid_amount' => 0,
            'balance' => 30000, 'status' => 'unpaid',
        ]);

        $this->plan = PaymentPlan::create([
            'branch_id' => $this->branch->id,
            'student_fee_id' => $this->fee->id,
            'total_amount' => 30000,
            'number_of_installments' => 3,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        foreach ([1, 2, 3] as $n) {
            PaymentPlanInstallment::create([
                'branch_id' => $this->branch->id,
                'payment_plan_id' => $this->plan->id,
                'installment_number' => $n,
                'amount' => 10000, 'paid_amount' => 0,
                'due_date' => '2026-0'.$n.'-01', 'status' => 'pending',
            ]);
        }
    }

    private function instalment(int $number): PaymentPlanInstallment
    {
        return $this->plan->installments()->where('installment_number', $number)->firstOrFail();
    }

    private function payInstalment(int $number, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.payment-plans.pay', $this->plan), [
            'installment_id' => $this->instalment($number)->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);
    }

    public function test_an_instalment_can_be_paid(): void
    {
        $this->payInstalment(1, 10000)->assertRedirect()->assertSessionHas('success');

        $this->assertSame('paid', $this->instalment(1)->status);
        $this->assertEquals(10000, (float) $this->instalment(1)->paid_amount);
        $this->assertSame(1, Payment::count());
    }

    public function test_paying_reduces_the_underlying_fee(): void
    {
        $this->payInstalment(1, 10000);

        $this->fee->refresh();
        $this->assertEquals(10000, (float) $this->fee->paid_amount);
        $this->assertEquals(20000, (float) $this->fee->balance);
    }

    public function test_instalments_are_settled_in_order(): void
    {
        // Paying more than one instalment's worth flows on to the next.
        $this->payInstalment(1, 15000);

        $this->assertSame('paid', $this->instalment(1)->status);
        $this->assertSame('partial', $this->instalment(2)->status);
        $this->assertEquals(5000, (float) $this->instalment(2)->paid_amount);
    }

    public function test_paying_every_instalment_completes_the_plan(): void
    {
        $this->payInstalment(1, 30000);

        $this->assertSame('completed', $this->plan->fresh()->status);
        $this->assertSame(3, $this->plan->installments()->where('status', 'paid')->count());
    }

    public function test_an_already_paid_instalment_is_refused(): void
    {
        $this->payInstalment(1, 10000);
        $this->payInstalment(1, 10000)->assertSessionHas('error');

        $this->assertSame(1, Payment::count());
    }

    public function test_an_instalment_from_another_plan_is_refused(): void
    {
        $otherFee = StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->fee->student_enrollment_id,
            'fee_category_id' => FeeCategory::create([
                'branch_id' => $this->branch->id, 'name' => 'Boarding', 'code' => 'BRD', 'is_active' => true,
            ])->id,
            'original_amount' => 5000, 'discount_amount' => 0, 'waiver_amount' => 0,
            'fine_amount' => 0, 'net_amount' => 5000, 'paid_amount' => 0,
            'balance' => 5000, 'status' => 'unpaid',
        ]);
        $otherPlan = PaymentPlan::create([
            'branch_id' => $this->branch->id, 'student_fee_id' => $otherFee->id,
            'total_amount' => 5000, 'number_of_installments' => 1,
            'status' => 'active', 'created_by' => $this->admin->id,
        ]);
        $foreign = PaymentPlanInstallment::create([
            'branch_id' => $this->branch->id, 'payment_plan_id' => $otherPlan->id,
            'installment_number' => 1, 'amount' => 5000, 'paid_amount' => 0,
            'due_date' => '2026-01-01', 'status' => 'pending',
        ]);

        $this->post(route('admin.payment-plans.pay', $this->plan), [
            'installment_id' => $foreign->id,
            'amount' => 5000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ])->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }

    public function test_a_cancelled_plan_cannot_be_paid(): void
    {
        $this->plan->update(['status' => 'cancelled']);

        $this->payInstalment(1, 10000)->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }
}
