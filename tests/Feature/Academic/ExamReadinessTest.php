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
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\ExamReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whether a term's results can honestly be published.
 *
 * Report cards could be generated from whatever marks happened to exist, so a
 * term missing two subjects looked exactly as finished as a complete one.
 * These tests pin down what counts as finished.
 */
class ExamReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Term $term;
    private Sequence $seq1;
    private ClassSection $class;
    private Subject $maths;
    private Subject $english;
    private Batch $batch;
    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both', 'pass_mark' => 10,
        ]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->seq1 = Sequence::create([
            'branch_id' => $this->branch->id, 'term_id' => $this->term->id,
            'sequence_number' => 1, 'name' => 'Sequence 1',
            'start_date' => '2025-09-01', 'end_date' => '2025-10-15',
            'weight' => 1, 'status' => 'active',
        ]);
        $this->batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true,
        ]);

        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => 1, 'is_active' => true,
        ]);
        $this->class = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true]);
    }

    /** @param array<int, Subject> $subjects */
    private function pupil(string $name, array $subjects): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => $name, 'last_name' => 'Pupil',
            'date_of_birth' => '2012-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        $enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->class->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);

        foreach ($subjects as $subject) {
            StudentSubject::create([
                'branch_id' => $this->branch->id,
                'student_enrollment_id' => $enrollment->id,
                'subject_id' => $subject->id,
                'coefficient' => 1,
            ]);
        }

        return $enrollment;
    }

    private function submission(Subject $subject, string $status, int $entered = 1, int $total = 1): MarksSubmission
    {
        return MarksSubmission::create([
            'branch_id' => $this->branch->id,
            'class_section_id' => $this->class->id,
            'subject_id' => $subject->id,
            'sequence_id' => $this->seq1->id,
            'teacher_id' => $this->admin->id,
            'status' => $status,
            'total_students' => $total,
            'marks_entered' => $entered,
        ]);
    }

    private function mark(StudentEnrollment $enrollment, Subject $subject, float $score): Mark
    {
        return Mark::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'sequence_id' => $this->seq1->id,
            'score' => $score,
            'is_absent' => false,
            'entered_by' => $this->admin->id,
            'status' => 'published',
        ]);
    }

    private function termResult(StudentEnrollment $enrollment, bool $published = true): TermResult
    {
        return TermResult::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'term_id' => $this->term->id,
            'total_weighted_score' => 24,
            'total_coefficient' => 2,
            'term_average' => 12,
            'is_published' => $published,
        ]);
    }

    private function report(): array
    {
        return app(ExamReadinessService::class)->report($this->term->id);
    }

    /** @param array<int, array<string, mixed>> $checks */
    private function check(array $checks, string $key): ?array
    {
        return collect($checks)->firstWhere('key', $key);
    }

    // ------------------------------------------------------------ blockers

    public function test_a_subject_with_no_marks_at_all_blocks_publishing(): void
    {
        $pupil = $this->pupil('Ada', [$this->maths, $this->english]);
        $this->mark($pupil, $this->maths, 12);
        $this->submission($this->maths, 'approved');
        // English was never touched.

        $report = $this->report();

        $this->assertSame(ExamReadinessService::BLOCKER, $report['verdict']);
        $this->assertNotNull($this->check($report['checks'], 'not_started'));
        $this->assertSame(['English'], $report['pending']->pluck('subject')->all());
        $this->assertSame('not_started', $report['pending'][0]['state']);
    }

    public function test_marks_entered_but_not_approved_block_publishing(): void
    {
        $pupil = $this->pupil('Ada', [$this->maths]);
        $this->mark($pupil, $this->maths, 12);
        // Submitted is waiting on someone; it does not count towards results.
        $this->submission($this->maths, 'submitted');

        $report = $this->report();

        $this->assertSame(ExamReadinessService::BLOCKER, $report['verdict']);
        $this->assertNotNull($this->check($report['checks'], 'unapproved'));
        $this->assertSame(__('Waiting for approval'), $report['pending'][0]['label']);
    }

    public function test_a_pupil_with_no_marks_is_named(): void
    {
        $ada = $this->pupil('Ada', [$this->maths]);
        $this->mark($ada, $this->maths, 12);
        $this->submission($this->maths, 'approved');

        // Admitted late, registered for nothing, marked in nothing.
        $this->pupil('Bih', []);

        $report = $this->report();

        $this->assertSame(ExamReadinessService::BLOCKER, $report['verdict']);
        $this->assertNotNull($this->check($report['checks'], 'no_marks'));
        $this->assertSame(['Bih Pupil'], $report['students_without_marks']->pluck('student')->all());
    }

    public function test_a_missing_result_blocks_publishing(): void
    {
        $pupil = $this->pupil('Ada', [$this->maths]);
        $this->mark($pupil, $this->maths, 12);
        $this->submission($this->maths, 'approved');
        // No term result generated.

        $report = $this->report();

        $this->assertSame(ExamReadinessService::BLOCKER, $report['verdict']);
        $this->assertSame(ExamReadinessService::BLOCKER, $this->check($report['checks'], 'results')['status']);
    }

    public function test_a_term_with_no_sequences_cannot_be_averaged(): void
    {
        $this->seq1->delete();
        $this->pupil('Ada', [$this->maths]);

        $report = $this->report();

        $this->assertSame(ExamReadinessService::BLOCKER, $report['verdict']);
        $this->assertSame(ExamReadinessService::BLOCKER, $this->check($report['checks'], 'sequences')['status']);
    }

    // ------------------------------------------------------------ warnings

    public function test_generated_but_unpublished_results_are_a_loose_end_not_a_blocker(): void
    {
        $pupil = $this->pupil('Ada', [$this->maths]);
        $this->mark($pupil, $this->maths, 12);
        $this->submission($this->maths, 'approved');
        $this->termResult($pupil, published: false);

        $report = $this->report();

        $this->assertSame(ExamReadinessService::WARNING, $report['verdict']);
        $this->assertSame(ExamReadinessService::WARNING, $this->check($report['checks'], 'publishing')['status']);
    }

    public function test_an_unweighted_sequence_is_flagged_because_it_silently_counts_as_one(): void
    {
        $this->seq1->update(['weight' => 0]);

        $pupil = $this->pupil('Ada', [$this->maths]);
        $this->mark($pupil, $this->maths, 12);
        $this->submission($this->maths, 'approved');
        $this->termResult($pupil);

        $report = $this->report();

        $this->assertSame(ExamReadinessService::WARNING, $this->check($report['checks'], 'weights')['status']);
    }

    // ------------------------------------------------------------ ready

    public function test_a_complete_term_reads_as_ready(): void
    {
        $pupil = $this->pupil('Ada', [$this->maths, $this->english]);
        $this->mark($pupil, $this->maths, 12);
        $this->mark($pupil, $this->english, 14);
        $this->submission($this->maths, 'approved');
        $this->submission($this->english, 'published');
        $this->termResult($pupil);

        $report = $this->report();

        $this->assertSame(ExamReadinessService::READY, $report['verdict']);
        $this->assertSame(0, $report['totals']['pending']);
        $this->assertSame(2, $report['totals']['expected']);
    }

    public function test_expected_work_follows_what_pupils_are_registered_to_study(): void
    {
        // English exists but nobody takes it, so no English marks are expected.
        $this->pupil('Ada', [$this->maths]);

        $report = $this->report();

        $this->assertSame(1, $report['totals']['expected']);
        $this->assertSame(['Mathematics'], $report['pending']->pluck('subject')->all());
    }

    // ------------------------------------------------------------ per class

    public function test_each_class_reports_its_own_progress(): void
    {
        $pupil = $this->pupil('Ada', [$this->maths, $this->english]);
        $this->mark($pupil, $this->maths, 12);
        $this->submission($this->maths, 'approved');

        $class = $this->report()['classes'][0];

        $this->assertSame('Form 1A', $class['name']);
        $this->assertSame(2, $class['expected']);
        $this->assertSame(1, $class['done']);
        $this->assertSame(50.0, $class['percent']);
        $this->assertSame(['English'], $class['subjects_outstanding']);
    }

    // ------------------------------------------------------------ the screen

    public function test_the_screen_states_the_verdict(): void
    {
        $this->pupil('Ada', [$this->maths]);

        $this->get(route('admin.analytics.readiness', ['term_id' => $this->term->id]))
            ->assertSuccessful()
            ->assertSee(__('Not ready to publish'));
    }
}
