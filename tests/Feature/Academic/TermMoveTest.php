<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Found walking a whole school year through the app: after the class was
 * moved into the Second Term, the marks grid failed for every subject, and a
 * boarder had become a day pupil.
 */
class TermMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_moving_into_the_next_term_marks_can_be_entered_and_boarders_stay_boarders(): void
    {
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $year = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        $t1 = Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => false]);
        $t2 = Term::create(['branch_id' => $branch->id, 'term_number' => 2, 'name' => 'Second Term', 'start_date' => '2027-01-04', 'end_date' => '2027-03-26', 'is_current' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Primary 2', 'short_name' => 'P2', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 5, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P2A', 'max_students' => 40, 'is_active' => true]);
        $maths = Subject::create(['branch_id' => $branch->id, 'name' => 'Mathematics', 'code' => 'MATH', 'is_active' => true]);
        DB::table('form_subject')->insert(['form_id' => $form->id, 'subject_id' => $maths->id, 'coefficient' => 1, 'type' => 'core']);
        $seq = Sequence::create(['branch_id' => $branch->id, 'name' => 'Third Sequence', 'sequence_number' => 3, 'weight' => 1]);
        DB::table('form_sequence')->insert(['form_id' => $form->id, 'term_id' => $t2->id, 'sequence_id' => $seq->id]);
        $batch = Batch::create(['branch_id' => $branch->id, 'name' => '2026', 'shortcode' => '26', 'is_active' => true]);
        $pupil = Student::create(['branch_id' => $branch->id, 'student_id' => 'TS1', 'batch_id' => $batch->id, 'first_name' => 'Kevin', 'last_name' => 'ACHU', 'date_of_birth' => '2019-02-12', 'gender' => 'male', 'status' => 'active', 'admission_date' => '2026-09-01']);
        $first = StudentEnrollment::create(['branch_id' => $branch->id, 'student_id' => $pupil->id, 'academic_session_id' => $year->id, 'term_id' => $t1->id, 'class_section_id' => $section->id, 'residence_type' => 'boarding', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $first->syncCoreSubjects();

        $this->post(route('admin.group-enrol.enrol'), [
            'student_ids' => [$pupil->id], 'target_session_id' => $year->id, 'target_form_id' => $form->id,
            'target_section_id' => $section->id, 'target_term_id' => $t2->id,
        ])->assertRedirect();

        $second = StudentEnrollment::where('term_id', $t2->id)->firstOrFail();
        $this->assertSame('boarding', $second->residence_type);
        $this->assertSame(0, DB::table('student_subjects')->whereNull('branch_id')->count());

        $this->get(route('admin.marks.index', [
            'academic_session_id' => $year->id, 'education_system' => 'english', 'form_id' => $form->id,
            'class_section_id' => $section->id, 'subject_id' => $maths->id, 'term_id' => $t2->id, 'sequence_id' => $seq->id,
        ]))->assertSuccessful()->assertViewHas('enrollments', fn ($e) => $e->count() === 1);
    }
}
