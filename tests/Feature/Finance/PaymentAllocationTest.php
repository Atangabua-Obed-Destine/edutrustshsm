<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: PaymentController::store capped an allocation at the payment's
 * remaining amount but NOT at the fee's balance, so posting 100,000 against a
 * 10,000 fee drove `balance` to -90,000 and corrupted every SUM(balance).
 */
class PaymentAllocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private StudentEnrollment $enrollment;
    private StudentFee $fee;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($branch->id, ['is_default' => true]);

        $session = AcademicSession::create([
            'branch_id' => $branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $term = Term::create([
            'branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $form = Form::create([
            'branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $section = ClassSection::create([
            'branch_id' => $branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $student = Student::create([
            'branch_id' => $branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Sam', 'last_name' => 'Student',
            'date_of_birth' => '2012-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        $this->enrollment = StudentEnrollment::create([
            'branch_id' => $branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        $category = FeeCategory::create([
            'branch_id' => $branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true,
        ]);

        $this->fee = StudentFee::create([
            'branch_id' => $branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => 10000, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => 10000, 'paid_amount' => 0, 'balance' => 10000,
            'status' => 'unpaid',
        ]);
    }

    public function test_an_over_allocation_cannot_drive_a_fee_balance_negative(): void
    {
        $this->actingAs($this->admin)->post(route('admin.payments.store'), [
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
            'allocations' => [
                ['fee_id' => $this->fee->id, 'amount' => 100000],
            ],
        ]);

        $this->fee->refresh();

        $this->assertGreaterThanOrEqual(0, (float) $this->fee->balance, 'a fee balance must never go negative');
        $this->assertEquals(0, (float) $this->fee->balance);
        $this->assertEquals(10000, (float) $this->fee->paid_amount, 'only the outstanding amount may be applied');
        $this->assertSame('paid', $this->fee->status);
    }

    public function test_a_normal_partial_payment_still_works(): void
    {
        $this->actingAs($this->admin)->post(route('admin.payments.store'), [
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 4000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
            'allocations' => [
                ['fee_id' => $this->fee->id, 'amount' => 4000],
            ],
        ]);

        $this->fee->refresh();

        $this->assertEquals(4000, (float) $this->fee->paid_amount);
        $this->assertEquals(6000, (float) $this->fee->balance);
        $this->assertSame('partial', $this->fee->status);
    }
}
