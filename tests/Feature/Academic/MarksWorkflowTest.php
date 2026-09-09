<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\MarksWorkflowLog;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\MarksWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * The workflow rules lived as a map inside one bulk action and as ad-hoc `if`
 * checks elsewhere, while submit, approve and returnMarks had NO state check at
 * all — a draft could be approved directly and a published submission
 * re-approved. Now that report cards only count approved marks, that matters.
 */
class MarksWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private ClassSection $section;
    private Subject $subject;
    private Sequence $sequence;
    private StudentEnrollment $enrollment;
    private Term $term;

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
        $this->term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->subject = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Maths', 'code' => 'MTH', 'is_active' => true,
        ]);
        DB::table('form_subject')->insert([
            'form_id' => $form->id, 'subject_id' => $this->subject->id,
            'coefficient' => 1, 'type' => 'core',
        ]);
        $this->sequence = Sequence::create([
            'branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1,
        ]);
        DB::table('form_sequence')->insert([
            'form_id' => $form->id, 'term_id' => $this->term->id, 'sequence_id' => $this->sequence->id,
        ]);

        GradeScale::create([
            'branch_id' => $this->branch->id, 'grade' => 'A',
            'min_mark' => 0, 'max_mark' => 20, 'description' => 'Pass', 'display_order' => 1,
        ]);

        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Ann', 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        $this->enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
        StudentSubject::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id, 'coefficient' => 1,
        ]);
    }

    private function submission(string $status = 'draft'): MarksSubmission
    {
        $submission = MarksSubmission::create([
            'branch_id' => $this->branch->id,
            'class_section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'sequence_id' => $this->sequence->id,
            'teacher_id' => $this->admin->id,
            'status' => $status,
        ]);

        Mark::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'subject_id' => $this->subject->id,
            'sequence_id' => $this->sequence->id,
            'score' => 15, 'is_absent' => false, 'status' => $status,
        ]);

        return $submission;
    }

    private function workflow(): MarksWorkflowService
    {
        return app(MarksWorkflowService::class);
    }

    public function test_the_happy_path_runs_draft_to_published(): void
    {
        $submission = $this->submission('draft');

        $this->workflow()->apply($submission, 'submitted');
        $this->assertSame('submitted', $submission->fresh()->status);

        $this->workflow()->apply($submission->fresh(), 'approved');
        $this->assertSame('approved', $submission->fresh()->status);

        $this->workflow()->apply($submission->fresh(), 'published');
        $this->assertSame('published', $submission->fresh()->status);
    }

    public function test_a_draft_cannot_be_approved_directly(): void
    {
        $submission = $this->submission('draft');

        // Regression: approve() had no state check at all.
        $this->expectException(RuntimeException::class);
        $this->workflow()->apply($submission, 'approved');
    }

    public function test_a_published_submission_cannot_be_reapproved(): void
    {
        $submission = $this->submission('published');

        $this->expectException(RuntimeException::class);
        $this->workflow()->apply($submission, 'approved');
    }

    public function test_marks_move_with_their_submission(): void
    {
        $submission = $this->submission('draft');

        $this->workflow()->apply($submission, 'submitted');
        $this->workflow()->apply($submission->fresh(), 'approved');

        // The two used to be able to disagree about the state of a class.
        $this->assertSame('approved', Mark::firstOrFail()->status);
        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_returning_requires_a_reason(): void
    {
        $submission = $this->submission('submitted');

        $this->expectExceptionMessage('Say what needs correcting');
        $this->workflow()->apply($submission, 'returned');
    }

    public function test_returned_marks_can_be_resubmitted(): void
    {
        $submission = $this->submission('submitted');

        $this->workflow()->apply($submission, 'returned', 'Two scores are missing');
        $this->assertSame('returned', $submission->fresh()->status);

        $this->workflow()->apply($submission->fresh(), 'submitted');
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_unpublishing_steps_back_to_approved(): void
    {
        $submission = $this->submission('published');

        $this->workflow()->apply($submission, 'unpublished');

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame('approved', Mark::firstOrFail()->status);
    }

    public function test_every_transition_is_logged(): void
    {
        $submission = $this->submission('draft');

        $this->workflow()->apply($submission, 'submitted');
        $this->workflow()->apply($submission->fresh(), 'returned', 'Check question 4');
        $this->workflow()->apply($submission->fresh(), 'submitted');

        $log = MarksWorkflowLog::orderBy('id')->get();

        $this->assertCount(3, $log);
        $this->assertSame('draft', $log[0]->from_status);
        $this->assertSame('returned', $log[1]->to_status);
        $this->assertSame('Check question 4', $log[1]->note);
        $this->assertSame($this->admin->id, $log[2]->user_id);
    }

    public function test_marks_cannot_be_edited_once_approved(): void
    {
        $submission = $this->submission('approved');

        $this->post(route('admin.marks.save'), [
            'class_section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'sequence_id' => $this->sequence->id,
            'marks' => [
                ['enrollment_id' => $this->enrollment->id, 'score' => 3],
            ],
        ])->assertSessionHas('error');

        // Regression: saving overwrote approved marks AND reset them to draft,
        // leaving the submission reading "approved" while its marks did not.
        $mark = Mark::firstOrFail();
        $this->assertEquals(15, (float) $mark->score);
        $this->assertSame('approved', $mark->status);
        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_marks_can_be_edited_while_returned(): void
    {
        $this->submission('returned');

        $this->post(route('admin.marks.save'), [
            'class_section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
            'sequence_id' => $this->sequence->id,
            'marks' => [
                ['enrollment_id' => $this->enrollment->id, 'score' => 11],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(11, (float) Mark::firstOrFail()->score);
    }

    public function test_a_bulk_transition_skips_and_names_what_it_cannot_move(): void
    {
        $ready = $this->submission('submitted');

        $other = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Art', 'code' => 'ART', 'is_active' => true,
        ]);
        $notReady = MarksSubmission::create([
            'branch_id' => $this->branch->id,
            'class_section_id' => $this->section->id,
            'subject_id' => $other->id,
            'sequence_id' => $this->sequence->id,
            'teacher_id' => $this->admin->id,
            'status' => 'draft',
        ]);

        $result = $this->workflow()->applyMany(
            MarksSubmission::with('subject')->whereIn('id', [$ready->id, $notReady->id])->get(),
            'approved'
        );

        $this->assertSame(1, $result['applied']);
        $this->assertSame(['Art'], $result['skipped']);
        $this->assertSame('approved', $ready->fresh()->status);
        $this->assertSame('draft', $notReady->fresh()->status);
    }

    public function test_the_controller_reports_an_invalid_transition(): void
    {
        $submission = $this->submission('draft');

        $this->post(route('admin.marks.approve', $submission))
            ->assertSessionHas('error');

        $this->assertSame('draft', $submission->fresh()->status);
    }

    public function test_publishing_from_the_screen_requires_approval_first(): void
    {
        $submission = $this->submission('submitted');

        $this->post(route('admin.exam-publishing.publish-subject'), [
            'submission_id' => $submission->id,
        ])->assertSessionHas('error');

        $this->assertSame('submitted', $submission->fresh()->status);
    }
}
