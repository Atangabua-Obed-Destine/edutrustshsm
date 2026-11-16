<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\Mark;
use App\Models\MarkCorrection;
use App\Models\MarksEntryDeadline;
use App\Models\MarksSubmission;
use App\Models\ResultHold;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\TermResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What the school keeps in hand once results leave the teacher, as the
 * reference system has it: who may send report cards to parents and take
 * them back, holding one pupil's card back, correcting a mark after approval
 * without sending the sheet back, and a date after which teachers can no
 * longer enter marks.
 */
class ResultsControlTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $teacher;
    private AcademicSession $year;
    private Term $term;
    private ClassSection $section;
    private Subject $maths;
    private Sequence $sequence;
    private StudentEnrollment $ann;
    private StudentEnrollment $ben;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->teacher = User::create(['first_name' => 'Tom', 'last_name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password', 'role' => 'teacher', 'is_active' => true]);
        $this->teacher->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $this->section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Maths', 'code' => 'MTH', 'is_active' => true]);
        DB::table('form_subject')->insert(['form_id' => $form->id, 'subject_id' => $this->maths->id, 'coefficient' => 1, 'type' => 'core']);
        $this->sequence = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'First Sequence', 'sequence_number' => 1, 'weight' => 1]);
        DB::table('form_sequence')->insert(['form_id' => $form->id, 'term_id' => $this->term->id, 'sequence_id' => $this->sequence->id]);
        GradeScale::create(['branch_id' => $this->branch->id, 'grade' => 'B', 'min_mark' => 10, 'max_mark' => 20, 'description' => 'Pass', 'display_order' => 1]);
        GradeScale::create(['branch_id' => $this->branch->id, 'grade' => 'D', 'min_mark' => 0, 'max_mark' => 9.99, 'description' => 'Fail', 'display_order' => 2]);

        $this->ann = $this->pupil('Ann', 'TS260001');
        $this->ben = $this->pupil('Ben', 'TS260002');
        TeacherSubjectAssignment::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->year->id, 'user_id' => $this->teacher->id, 'subject_id' => $this->maths->id, 'class_section_id' => $this->section->id]);
    }

    private function pupil(string $name, string $matricule): StudentEnrollment
    {
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => $matricule, 'first_name' => $name, 'last_name' => 'TEST', 'date_of_birth' => '2014-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2026-09-07']);
        $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->year->id, 'term_id' => $this->term->id, 'class_section_id' => $this->section->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        StudentSubject::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'subject_id' => $this->maths->id, 'coefficient' => 1]);

        return $enrollment;
    }

    /** An approved Maths sheet: Ann 8, Ben 14. */
    private function approvedSheet(string $status = 'approved'): MarksSubmission
    {
        $submission = MarksSubmission::create(['branch_id' => $this->branch->id, 'class_section_id' => $this->section->id, 'academic_session_id' => $this->year->id, 'subject_id' => $this->maths->id, 'sequence_id' => $this->sequence->id, 'teacher_id' => $this->teacher->id, 'status' => $status, 'marks_entered' => 2]);
        foreach ([[$this->ann, 8], [$this->ben, 14]] as [$enrollment, $score]) {
            Mark::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'subject_id' => $this->maths->id, 'sequence_id' => $this->sequence->id, 'score' => $score, 'is_absent' => false, 'status' => $status]);
        }

        return $submission;
    }

    private function cards(): void
    {
        app(TermResultCalculator::class)->persist($this->section, $this->term, $this->year->id);
    }

    private function cardOf(StudentEnrollment $enrollment): TermResult
    {
        return TermResult::where('student_enrollment_id', $enrollment->id)->where('term_id', $this->term->id)->firstOrFail();
    }

    private function parentOf(StudentEnrollment $enrollment): Guardian
    {
        $guardian = Guardian::create(['branch_id' => $this->branch->id, 'portal_access' => true, 'emergency_contact_name' => 'Mami', 'emergency_contact_phone' => '677000111']);
        $guardian->students()->attach($enrollment->student_id, ['relationship' => 'Mother', 'is_primary' => true, 'can_access_portal' => true]);

        return $guardian;
    }

    private function classFilter(): array
    {
        return ['class_section_id' => $this->section->id, 'term_id' => $this->term->id, 'academic_session_id' => $this->year->id];
    }

    // ── Publishing ────────────────────────────────────────────────────────

    public function test_a_teacher_cannot_publish_report_cards_to_parents(): void
    {
        $this->approvedSheet();
        $this->cards();

        $this->actingAs($this->teacher)->post(route('admin.report-cards.publish'), $this->classFilter())->assertForbidden();
        $this->assertSame(0, TermResult::where('is_published', true)->count());

        // A teacher who also works the front desk passes the office screens'
        // door, and holds "view report cards" as a teacher. Viewing is not
        // publishing.
        $this->teacher->roles()->attach(\App\Models\Role::where('name', 'receptionist')->value('id'));
        $deskTeacher = User::find($this->teacher->id);
        $this->assertTrue($deskTeacher->holdsRole('receptionist'));
        $this->actingAs($deskTeacher)->post(route('admin.report-cards.publish'), $this->classFilter())->assertForbidden();
        $this->assertSame(0, TermResult::where('is_published', true)->count());

        $this->actingAs($this->admin)->post(route('admin.report-cards.publish'), $this->classFilter())->assertRedirect();
        $this->assertSame(2, TermResult::where('is_published', true)->count());
    }

    public function test_report_cards_are_taken_back_with_a_reason(): void
    {
        $this->approvedSheet();
        $this->cards();
        TermResult::query()->update(['is_published' => true]);

        $this->post(route('admin.report-cards.unpublish'), $this->classFilter())->assertSessionHasErrors('reason');
        $this->assertSame(2, TermResult::where('is_published', true)->count());

        $this->post(route('admin.report-cards.unpublish'), $this->classFilter() + ['reason' => 'Maths marks being re-checked'])->assertRedirect();
        $this->assertSame(0, TermResult::where('is_published', true)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'unpublished', 'model_type' => 'report-cards']);
    }

    // ── Withholding ───────────────────────────────────────────────────────

    public function test_a_withheld_card_is_kept_from_the_parent_until_released(): void
    {
        $this->approvedSheet();
        $this->cards();
        TermResult::query()->update(['is_published' => true]);
        $card = $this->cardOf($this->ann);
        $parent = $this->parentOf($this->ann);

        $this->post(route('admin.report-cards.withhold'), ['term_result_ids' => [$card->id]])->assertSessionHasErrors('reason');
        $this->post(route('admin.report-cards.withhold'), ['term_result_ids' => [$card->id], 'reason' => 'Second-term fees unpaid'])->assertRedirect();
        $hold = ResultHold::sole();
        $this->assertSame($this->admin->id, $hold->held_by);

        // The school still sees it, marked.
        $this->get(route('admin.report-cards.index', ['academic_session_id' => $this->year->id, 'form_id' => $this->section->form_id] + $this->classFilter()))
            ->assertOk()->assertSee('data-withheld', false)->assertSee(__('Release'));

        // The family sees that it exists and is withheld, and no figures.
        $this->actingAs($parent, 'guardians')
            ->get(route('parent.report-cards.index', $this->ann->student_id))
            ->assertOk()->assertSee(__('Withheld by the school'))->assertDontSee(number_format($card->term_average, 2));
        $this->actingAs($parent, 'guardians')
            ->get(route('parent.report-cards.show', [$this->ann->student_id, $card]))
            ->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.report-cards.release', $hold), ['release_reason' => 'Paid in full'])->assertRedirect();
        $this->assertNotNull($hold->fresh()->released_at);

        $this->actingAs($parent, 'guardians')
            ->get(route('parent.report-cards.show', [$this->ann->student_id, $card]))
            ->assertOk();
    }

    public function test_a_teacher_cannot_withhold_results(): void
    {
        $this->approvedSheet();
        $this->cards();

        $this->actingAs($this->teacher)
            ->post(route('admin.report-cards.withhold'), ['term_result_ids' => [$this->cardOf($this->ann)->id], 'reason' => 'Not allowed'])
            ->assertForbidden();
        $this->assertSame(0, ResultHold::count());
    }

    // ── Correcting one mark ───────────────────────────────────────────────

    public function test_a_mark_is_corrected_after_approval_with_a_reason_and_the_cards_follow(): void
    {
        $submission = $this->approvedSheet('published');
        $this->cards();
        TermResult::query()->update(['is_published' => true]);
        $this->assertEquals(8, (float) $this->cardOf($this->ann)->term_average);

        $this->post(route('admin.marks.correct', $submission), ['enrollment_id' => $this->ann->id, 'score' => 12, 'reason' => 'too short'])
            ->assertSessionHas('error');
        $this->assertSame(0, MarkCorrection::count());

        $this->post(route('admin.marks.correct', $submission), [
            'enrollment_id' => $this->ann->id, 'score' => 12, 'reason' => 'Script re-marked: question 4 had not been added in.',
        ])->assertSessionHas('success');

        $mark = Mark::where('student_enrollment_id', $this->ann->id)->sole();
        $this->assertEquals(12, (float) $mark->score);
        $this->assertSame('B', $mark->grade);
        $this->assertSame('published', $mark->status);
        $this->assertSame('published', $submission->fresh()->status, 'the sheet does not go back to the teacher');
        $this->assertEquals(13, (float) $submission->fresh()->class_average);

        $correction = MarkCorrection::sole();
        $this->assertEquals(8, (float) $correction->old_score);
        $this->assertEquals(12, (float) $correction->new_score);
        $this->assertSame($this->admin->id, $correction->corrected_by);

        $card = $this->cardOf($this->ann);
        $this->assertEquals(12, (float) $card->term_average, 'the report card is recalculated');
        $this->assertTrue((bool) $card->is_published, 'and stays published');

        $this->get(route('admin.marks.index', ['academic_session_id' => $this->year->id, 'form_id' => $this->section->form_id, 'class_section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'term_id' => $this->term->id, 'sequence_id' => $this->sequence->id]))
            ->assertOk()->assertSee('data-corrections', false)->assertSee('question 4 had not been added in');
    }

    public function test_a_draft_sheet_is_not_corrected_this_way_and_a_teacher_cannot_correct(): void
    {
        $submission = $this->approvedSheet('draft');

        $this->post(route('admin.marks.correct', $submission), ['enrollment_id' => $this->ann->id, 'score' => 12, 'reason' => 'Script re-marked properly'])
            ->assertSessionHas('error', __('These marks have not been approved yet. Change them on the sheet itself.'));

        $submission->update(['status' => 'approved']);
        $this->actingAs($this->teacher)
            ->post(route('admin.marks.correct', $submission), ['enrollment_id' => $this->ann->id, 'score' => 12, 'reason' => 'Script re-marked properly'])
            ->assertForbidden();
        $this->assertSame(0, MarkCorrection::count());
    }

    public function test_a_pupil_can_be_corrected_to_absent(): void
    {
        $submission = $this->approvedSheet();

        $this->post(route('admin.marks.correct', $submission), ['enrollment_id' => $this->ben->id, 'is_absent' => '1', 'reason' => 'Was sick that day; another pupil\'s script was entered.'])
            ->assertSessionHas('success');

        $mark = Mark::where('student_enrollment_id', $this->ben->id)->sole();
        $this->assertTrue($mark->is_absent);
        $this->assertNull($mark->score);
        $this->assertSame(2, (int) $submission->fresh()->marks_entered, 'still two entered: one mark, one absence');
    }

    // ── The mark-entry deadline ───────────────────────────────────────────

    public function test_after_the_deadline_a_teacher_cannot_enter_or_submit_but_the_principal_can(): void
    {
        $this->post(route('admin.sequences.deadline', $this->sequence), ['closes_on' => now()->subDay()->toDateString()])->assertRedirect();
        $deadline = MarksEntryDeadline::sole();
        $this->assertSame($this->year->id, $deadline->academic_session_id);

        $marks = ['class_section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'sequence_id' => $this->sequence->id,
            'marks' => [['enrollment_id' => $this->ann->id, 'score' => 11]]];

        $this->actingAs($this->teacher)->post(route('admin.marks.save'), $marks)
            ->assertSessionHas('error', fn ($m) => str_contains($m, __('Ask the principal or deputy to enter these marks or to move the deadline.')));
        $this->assertSame(0, Mark::count());

        $this->actingAs($this->admin)->post(route('admin.marks.save'), $marks)->assertSessionHas('success');
        $this->assertSame(1, Mark::count());

        $submission = MarksSubmission::sole();
        $this->actingAs($this->teacher)->post(route('admin.marks.submit', $submission))->assertSessionHas('error');
        $this->assertSame('draft', $submission->fresh()->status);
    }

    public function test_the_deadline_day_itself_is_still_open_and_a_cleared_deadline_closes_nothing(): void
    {
        $this->post(route('admin.sequences.deadline', $this->sequence), ['closes_on' => now()->toDateString()]);
        $marks = ['class_section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'sequence_id' => $this->sequence->id,
            'marks' => [['enrollment_id' => $this->ann->id, 'score' => 11]]];

        $this->actingAs($this->teacher)->post(route('admin.marks.save'), $marks)->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('admin.sequences.deadline', $this->sequence), ['closes_on' => ''])->assertRedirect();
        $this->assertSame(0, MarksEntryDeadline::count());
    }

    public function test_a_teacher_cannot_move_the_deadline(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('admin.sequences.deadline', $this->sequence), ['closes_on' => '2030-01-01'])
            ->assertForbidden();
    }

    public function test_the_new_permissions_reach_admins_but_not_teachers(): void
    {
        $admin = User::create(['first_name' => 'Deputy', 'last_name' => 'Head', 'email' => 'deputy@example.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);

        foreach (['results-control.withhold', 'results-control.correct', 'results-control.deadline'] as $permission) {
            $this->assertTrue($admin->can($permission), $permission);
            $this->assertFalse($this->teacher->can($permission), $permission);
        }
    }
}
