<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\ParentPaymentSubmission;
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
 * Approving a parent's receipt goes through PaymentRecorder, which until now was
 * the only path that did NOT advance payment plans — so a parent paying an
 * instalment left the plan untouched.
 */
class ParentPaymentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentEnrollment $enrollment;
    private StudentFee $fee;
    private Guardian $guardian;
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

        $this->guardian = Guardian::create([
            'branch_id' => $this->branch->id,
            'guardian_name' => 'Pat Parent',
            'guardian_email' => 'parent@example.test',
            'guardian_phone' => '677000000',
            'emergency_contact_name' => 'Pat Parent',
            'emergency_contact_phone' => '677000000',
        ]);

        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Sam', 'last_name' => 'Student',
            'date_of_birth' => '2012-01-01', 'gender' => 'male',
            'guardian_id' => $this->guardian->id,
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        $this->enrollment = StudentEnrollment::create([
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
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => 10000, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => 10000, 'paid_amount' => 0, 'balance' => 10000,
            'status' => 'unpaid',
        ]);

        $this->plan = PaymentPlan::create([
            'branch_id' => $this->branch->id,
            'student_fee_id' => $this->fee->id,
            'total_amount' => 10000,
            'number_of_installments' => 2,
            'status' => 'active',
            'created_by' => $this->admin->id,
        ]);

        foreach ([1, 2] as $n) {
            PaymentPlanInstallment::create([
                'branch_id' => $this->branch->id,
                'payment_plan_id' => $this->plan->id,
                'installment_number' => $n,
                'amount' => 5000, 'paid_amount' => 0,
                'due_date' => '2026-0'.$n.'-01', 'status' => 'pending',
            ]);
        }
    }

    private function submission(): ParentPaymentSubmission
    {
        return ParentPaymentSubmission::create([
            'branch_id' => $this->branch->id,
            'guardian_id' => $this->guardian->id,
            'student_enrollment_id' => $this->enrollment->id,
            'student_fee_id' => $this->fee->id,
            'amount' => 5000,
            'payment_method' => 'mtn_momo',
            'payment_date' => '2026-01-15',
            'receipt_path' => 'receipts/test.jpg',
            'status' => 'pending',
        ]);
    }

    public function test_approving_a_parent_receipt_advances_the_payment_plan(): void
    {
        $submission = $this->submission();

        $this->actingAs($this->admin)
            ->post(route('admin.parent-payments.approve', $submission))
            ->assertRedirect();

        $this->fee->refresh();
        $first = $this->plan->installments()->where('installment_number', 1)->first();

        $this->assertEquals(5000, (float) $this->fee->paid_amount);
        // Regression: this path never advanced the plan at all.
        $this->assertEquals(5000, (float) $first->paid_amount);
        $this->assertSame('paid', $first->status);
    }

    public function test_approving_records_the_payment_and_links_the_submission(): void
    {
        $submission = $this->submission();

        $this->actingAs($this->admin)->post(route('admin.parent-payments.approve', $submission));

        $submission->refresh();

        $this->assertSame('approved', $submission->status);
        $this->assertNotNull($submission->payment_id);
        $this->assertSame(1, Payment::count());
    }

    public function test_a_submission_cannot_be_approved_twice(): void
    {
        $submission = $this->submission();

        $this->actingAs($this->admin)->post(route('admin.parent-payments.approve', $submission));
        $this->actingAs($this->admin)
            ->post(route('admin.parent-payments.approve', $submission))
            ->assertSessionHas('error');

        $this->fee->refresh();

        $this->assertSame(1, Payment::count(), 'a double approval must not record two receipts');
        $this->assertEquals(5000, (float) $this->fee->paid_amount);
    }
}
