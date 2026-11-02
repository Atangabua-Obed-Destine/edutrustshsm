<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A teacher could not log in at all — "no portal is available for your
 * role" — so every mark and every register was typed by an admin. Now a
 * teacher logs in to their own classes, marks only what they teach, keeps
 * only their own register, and cannot approve their own marks.
 */
class TeacherAccessTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $teacher;
    private AcademicSession $session;
    private Term $term;
    private Sequence $seq;
    private ClassSection $p3;
    private ClassSection $p4;
    private Subject $maths;
    private Subject $english;
    private StudentEnrollment $p3Pupil;
    private StudentEnrollment $p4Pupil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'both', 'pass_mark' => 10, 'max_mark' => 20]);

        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);

        // Created the way the HR screen creates staff: role name only, no role record attached.
        $this->teacher = User::create(['first_name' => 'Tom', 'last_name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password', 'role' => 'teacher', 'is_active' => true]);
        $this->teacher->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $this->seq = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1]);
        $batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);

        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true]);

        foreach (['Primary 3' => 3, 'Primary 4' => 4] as $name => $order) {
            $form = Form::create(['branch_id' => $this->branch->id, 'name' => $name, 'short_name' => 'P'.$order, 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => $order, 'is_active' => true]);
            $section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P'.$order.'A', 'max_students' => 40, 'is_active' => true]);
            foreach ([$this->maths, $this->english] as $s) {
                DB::table('form_subject')->insert(['form_id' => $form->id, 'subject_id' => $s->id, 'coefficient' => 1, 'type' => 'core']);
            }
            DB::table('form_sequence')->insert(['form_id' => $form->id, 'term_id' => $this->term->id, 'sequence_id' => $this->seq->id]);

            $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS'.$order, 'batch_id' => $batch->id, 'first_name' => 'Pupil', 'last_name' => $name, 'date_of_birth' => '2017-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);
            $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id, 'term_id' => $this->term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);
            foreach ([$this->maths, $this->english] as $s) {
                StudentSubject::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'subject_id' => $s->id, 'coefficient' => 1]);
            }

            if ($order === 3) {
                [$this->p3, $this->p3Pupil] = [$section, $enrollment];
            } else {
                [$this->p4, $this->p4Pupil] = [$section, $enrollment];
            }
        }

        // Class teacher of P3; teaches nothing in P4.
        $this->p3->update(['class_teacher_id' => $this->teacher->id]);
    }

    private function marks(ClassSection $section, Subject $subject, StudentEnrollment $pupil, float $score)
    {
        return $this->post(route('admin.marks.save'), [
            'class_section_id' => $section->id, 'subject_id' => $subject->id, 'sequence_id' => $this->seq->id,
            'marks' => [['enrollment_id' => $pupil->id, 'score' => $score]],
        ]);
    }

    public function test_a_teacher_logs_in_to_their_own_classes(): void
    {
        $this->post('/login', ['email' => 'teacher@example.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertSuccessful()
            ->assertSee('P3A')->assertDontSee('P4A')
            ->assertSee(__("Today's register"))
            ->assertSee('Mathematics');
    }

    public function test_the_teacher_menu_holds_their_work_and_nothing_else(): void
    {
        $html = $this->actingAs($this->teacher)->get(route('admin.dashboard'))->assertSuccessful()->getContent();

        $this->assertStringContainsString(route('admin.marks.index'), $html);
        $this->assertStringContainsString(route('admin.attendance.index'), $html);
        $this->assertStringNotContainsString(route('admin.collect-fees.index'), $html);
        $this->assertStringNotContainsString(route('admin.students.create'), $html);
    }

    public function test_a_class_teacher_at_primary_marks_every_subject_of_their_class_only(): void
    {
        $this->actingAs($this->teacher);

        $this->marks($this->p3, $this->maths, $this->p3Pupil, 14)->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->marks($this->p3, $this->english, $this->p3Pupil, 12)->assertSessionMissing('error');
        $this->assertSame(2, Mark::count());

        $this->marks($this->p4, $this->maths, $this->p4Pupil, 9)->assertForbidden();
        $this->assertSame(2, Mark::count());

        // Nor smuggle a P4 pupil into a P3 batch.
        $this->marks($this->p3, $this->maths, $this->p4Pupil, 3)->assertSessionHas('error');
    }

    public function test_a_subject_teacher_marks_only_the_subject_assigned(): void
    {
        TeacherSubjectAssignment::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'user_id' => $this->teacher->id, 'subject_id' => $this->maths->id, 'class_section_id' => $this->p4->id]);
        $this->actingAs($this->teacher);

        $this->marks($this->p4, $this->maths, $this->p4Pupil, 11)->assertSessionMissing('error');
        $this->marks($this->p4, $this->english, $this->p4Pupil, 11)->assertForbidden();
    }

    public function test_a_teacher_submits_but_cannot_approve_their_own_marks(): void
    {
        $this->actingAs($this->teacher);
        $this->marks($this->p3, $this->maths, $this->p3Pupil, 14);
        $submission = MarksSubmission::firstOrFail();

        $this->post(route('admin.marks.submit', $submission))->assertSessionMissing('error');
        $this->assertSame('submitted', $submission->fresh()->status);

        $this->post(route('admin.marks.approve', $submission))->assertForbidden();
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_a_teacher_keeps_only_their_own_register(): void
    {
        $this->actingAs($this->teacher);

        // One class: the register opens on it.
        $this->get(route('admin.attendance.index'))->assertSuccessful()->assertSee('P3A')->assertDontSee('P4A');

        $this->post(route('admin.attendance.store'), ['class_section_id' => $this->p3->id, 'date' => now()->toDateString(), 'attendance' => [$this->p3Pupil->id => ['status' => 'present']]])->assertSessionMissing('error');
        $this->post(route('admin.attendance.store'), ['class_section_id' => $this->p4->id, 'date' => now()->toDateString(), 'attendance' => [$this->p4Pupil->id => ['status' => 'present']]])->assertForbidden();
    }

    public function test_admin_screens_stay_closed_to_a_teacher(): void
    {
        $this->actingAs($this->teacher);

        $this->get(route('admin.collect-fees.index'))->assertForbidden();
        $this->get(route('admin.students.create'))->assertForbidden();
        $this->post(route('admin.report-cards.publish'), ['class_section_id' => $this->p3->id, 'term_id' => $this->term->id])->assertForbidden();
    }

    public function test_the_head_assigns_who_teaches_what(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.teaching-assignments.index'))->assertSuccessful()->assertSee('P4A')->assertSee(__('Who Teaches What'));

        $this->put(route('admin.teaching-assignments.update', $this->p4), [
            'class_teacher_id' => $this->teacher->id,
            'subjects' => [$this->maths->id => $this->teacher->id, $this->english->id => ''],
        ])->assertSessionHas('success');

        $this->assertSame($this->teacher->id, $this->p4->fresh()->class_teacher_id);
        $this->assertSame(1, TeacherSubjectAssignment::where('class_section_id', $this->p4->id)->count());
    }
}
