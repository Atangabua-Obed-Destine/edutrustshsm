<?php

namespace Tests\Feature\Finance;

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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fees are billed for the year, and a pupil gets a new enrolment row each
 * term. Moving a class into Second Term used to bill the year's tuition a
 * second time, and left First Term's unpaid balance on an enrolment the
 * collection screen no longer shows.
 */
class TermMoveFeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_moving_into_the_next_term_keeps_the_years_fees_and_bills_nothing_twice(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary']);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $session = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $t1 = Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $t2 = Term::create(['branch_id' => $branch->id, 'term_number' => 2, 'name' => 'Second Term', 'start_date' => '2026-01-05', 'end_date' => '2026-03-31', 'is_current' => false]);
        $batch = Batch::create(['branch_id' => $branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 3, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        $tuition = FeeCategory::create(['branch_id' => $branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        DB::table('fee_structures')->insert(['branch_id' => $branch->id, 'academic_session_id' => $session->id, 'form_id' => $form->id, 'fee_category_id' => $tuition->id, 'amount' => 150000, 'created_at' => now(), 'updated_at' => now()]);

        $student = Student::create(['branch_id' => $branch->id, 'student_id' => 'TS1', 'batch_id' => $batch->id, 'first_name' => 'Bih', 'last_name' => 'Ngwa', 'date_of_birth' => '2017-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);
        $first = StudentEnrollment::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'academic_session_id' => $session->id, 'term_id' => $t1->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);

        $fee = StudentFee::where('student_enrollment_id', $first->id)->firstOrFail();
        $fee->update(['paid_amount' => 60000]);
        $fee->recalculate()->save();

        $this->post(route('admin.group-enrol.enrol'), [
            'student_ids' => [$student->id], 'target_session_id' => $session->id, 'target_form_id' => $form->id,
            'target_section_id' => $section->id, 'target_term_id' => $t2->id,
        ])->assertSessionMissing('error');

        $second = StudentEnrollment::where('student_id', $student->id)->where('term_id', $t2->id)->firstOrFail();

        $this->assertSame(1, StudentFee::count(), 'the year is billed once');
        $this->assertSame($second->id, $fee->fresh()->student_enrollment_id, 'the year\'s fees follow the pupil into the new term');
        $this->assertEquals(90000, (float) $fee->fresh()->balance, 'the unpaid balance is still there to collect');
    }
}
