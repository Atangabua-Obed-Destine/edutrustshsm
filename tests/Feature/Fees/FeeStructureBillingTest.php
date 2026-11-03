<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found walking a school year through the app: promotion places pupils in
 * the new year before its fees are set, and setting them billed no one, so
 * every promoted pupil began the year owing nothing.
 */
class FeeStructureBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_fee_structure_bills_pupils_already_in_the_class_once(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $year = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2027/2028', 'start_date' => '2027-09-06', 'end_date' => '2028-07-01', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2027-09-06', 'end_date' => '2027-12-17', 'is_current' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 6, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        $tuition = FeeCategory::create(['branch_id' => $branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true]);
        $batch = Batch::create(['branch_id' => $branch->id, 'name' => '2026', 'shortcode' => '26', 'is_active' => true]);
        $pupil = Student::create(['branch_id' => $branch->id, 'student_id' => 'TS1', 'batch_id' => $batch->id, 'first_name' => 'Kevin', 'last_name' => 'ACHU', 'date_of_birth' => '2019-02-12', 'gender' => 'male', 'status' => 'active', 'admission_date' => '2026-09-01']);
        // Placed by promotion, before the year's fees existed.
        StudentEnrollment::create(['branch_id' => $branch->id, 'student_id' => $pupil->id, 'academic_session_id' => $year->id, 'term_id' => $term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2027-07-05', 'status' => 'active']);
        $this->assertSame(0, StudentFee::count());

        $save = fn () => $this->post(route('admin.fee-structures.store'), ['form_id' => $form->id, 'fees' => [['category_id' => $tuition->id, 'amount' => 120000]]]);
        $save()->assertSessionHas('success');
        $this->assertEquals(120000, (float) StudentFee::sole()->balance);

        // Saving again bills no one twice.
        $save();
        $this->assertSame(1, StudentFee::count());
    }
}
