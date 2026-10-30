<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\AcademicAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading the term's results back to the school.
 *
 * The figures must agree with the report cards by construction, which means
 * only published results count, and the screen must say how much of the term
 * it is actually drawing on.
 */
class AcademicAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Term $term1;
    private Term $term2;
    private ClassSection $classA;
    private ClassSection $classB;
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
        $this->term1 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => false,
        ]);
        $this->term2 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term',
            'start_date' => '2026-01-05', 'end_date' => '2026-03-30', 'is_current' => true,
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

        $this->classA = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->classB = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'B', 'name' => 'Form 1B', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true]);

        foreach ([['A', 0, 9.9], ['B', 10, 20]] as [$grade, $min, $max]) {
            GradeScale::create([
                'branch_id' => $this->branch->id, 'grade' => $grade,
                'min_mark' => $min, 'max_mark' => $max,
                'description' => $grade, 'display_order' => $min,
            ]);
        }
    }

    private function pupil(string $name, ClassSection $section): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => $name, 'last_name' => 'Pupil',
            'date_of_birth' => '2012-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term1->id,
            'class_section_id' => $section->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);
    }

    /** @param array<string, array{0: float, 1: string}> $subjects  name => [score, teacher] */
    private function recordResult(StudentEnrollment $enrollment, Term $term, float $average, array $subjects = [], bool $published = true): TermResult
    {
        $result = TermResult::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'term_id' => $term->id,
            'total_weighted_score' => $average * 2,
            'total_coefficient' => 2,
            'term_average' => $average,
            'is_published' => $published,
        ]);

        foreach ($subjects as $subjectName => [$score, $teacher]) {
            $subject = $subjectName === 'Mathematics' ? $this->maths : $this->english;

            SubjectTermResult::create([
                'branch_id' => $this->branch->id,
                'term_result_id' => $result->id,
                'subject_id' => $subject->id,
                'coefficient' => 1,
                'term_average' => $score,
                'weighted_score' => $score,
                'teacher_name' => $teacher,
            ]);
        }

        return $result;
    }

    private function report(?int $formId = null, ?int $classSectionId = null): array
    {
        return app(AcademicAnalyticsService::class)->report($this->term1->id, $formId, $classSectionId);
    }

    // ------------------------------------------------------------ headline

    public function test_the_headline_is_computed_from_published_results_only(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 14);
        $this->recordResult($this->pupil('Bih', $this->classA), $this->term1, 8);
        // A draft is the school's working copy; averaging it in would report a
        // figure nobody has stood behind.
        $this->recordResult($this->pupil('Che', $this->classA), $this->term1, 2, [], false);

        $headline = $this->report()['headline'];

        $this->assertSame(2, $headline['students']);
        $this->assertSame(11.0, $headline['average']);
        $this->assertSame(50.0, $headline['pass_rate']);
        $this->assertSame(14.0, $headline['highest']);
        $this->assertSame(8.0, $headline['lowest']);
    }

    public function test_coverage_says_how_much_of_the_term_the_figures_rest_on(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 14);
        $this->pupil('Bih', $this->classA);   // enrolled, no result
        $this->pupil('Che', $this->classA);   // enrolled, no result

        $coverage = $this->report()['coverage'];

        $this->assertSame(3, $coverage['enrolled']);
        $this->assertSame(1, $coverage['published']);
        $this->assertSame(33.3, $coverage['percent']);
    }

    // ------------------------------------------------------------ subjects

    public function test_a_subject_is_measured_against_the_pupils_own_averages(): void
    {
        // Strong pupils overall, but sunk in maths. The pass rate alone would
        // not separate a hard subject from a hard class.
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 15, [
            'Mathematics' => [8, 'Mr Tabi'],
            'English' => [16, 'Mrs Eyong'],
        ]);
        $this->recordResult($this->pupil('Bih', $this->classA), $this->term1, 15, [
            'Mathematics' => [8, 'Mr Tabi'],
            'English' => [16, 'Mrs Eyong'],
        ]);

        $subjects = collect($this->report()['subjects'])->keyBy('name');

        $this->assertSame(-7.0, $subjects['Mathematics']['delta']);
        $this->assertSame(1.0, $subjects['English']['delta']);

        // Worst first, so the subject in trouble is the one you read.
        $this->assertSame('Mathematics', $this->report()['subjects'][0]['name']);
    }

    public function test_teachers_are_measured_on_the_results_they_taught(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 12, [
            'Mathematics' => [8, 'Mr Tabi'],
            'English' => [16, 'Mrs Eyong'],
        ]);

        $teachers = collect($this->report()['teachers'])->keyBy('teacher');

        $this->assertSame(8.0, $teachers['Mr Tabi']['average']);
        $this->assertSame(0.0, $teachers['Mr Tabi']['pass_rate']);
        $this->assertSame(100.0, $teachers['Mrs Eyong']['pass_rate']);
        $this->assertSame(['Mathematics'], $teachers['Mr Tabi']['subjects']);
    }

    // ------------------------------------------------------------ at risk

    public function test_a_falling_pupil_is_flagged_even_while_still_passing(): void
    {
        $enrollment = $this->pupil('Ada', $this->classA);
        $this->recordResult($enrollment, $this->term1, 15);
        $this->recordResult($enrollment, $this->term2, 11);

        $atRisk = app(AcademicAnalyticsService::class)->report($this->term2->id)['at_risk'];

        $this->assertCount(1, $atRisk);
        $this->assertSame('Ada Pupil', $atRisk[0]['student']);
        $this->assertSame(-4.0, $atRisk[0]['change']);
        $this->assertSame(__('Falling'), $atRisk[0]['reason']);
    }

    public function test_a_steady_passing_pupil_is_not_flagged(): void
    {
        $enrollment = $this->pupil('Ada', $this->classA);
        $this->recordResult($enrollment, $this->term1, 14);
        $this->recordResult($enrollment, $this->term2, 13.5);

        $this->assertSame([], app(AcademicAnalyticsService::class)->report($this->term2->id)['at_risk']);
    }

    public function test_a_failing_pupil_is_flagged_with_the_reason(): void
    {
        $this->recordResult($this->pupil('Che', $this->classA), $this->term1, 6);

        $atRisk = $this->report()['at_risk'];

        $this->assertCount(1, $atRisk);
        $this->assertSame(__('Below the pass mark'), $atRisk[0]['reason']);
    }

    // ------------------------------------------------------------ scoping

    public function test_narrowing_to_a_class_excludes_the_others(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 16);
        $this->recordResult($this->pupil('Bih', $this->classB), $this->term1, 6);

        $this->assertSame(2, $this->report()['headline']['students']);

        $narrowed = $this->report(null, $this->classA->id);
        $this->assertSame(1, $narrowed['headline']['students']);
        $this->assertSame(16.0, $narrowed['headline']['average']);
    }

    public function test_classes_are_ranked_by_average(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 16);
        $this->recordResult($this->pupil('Bih', $this->classB), $this->term1, 6);

        $classes = $this->report()['classes'];

        $this->assertSame(['Form 1A', 'Form 1B'], array_column($classes, 'name'));
    }

    public function test_the_grade_spread_uses_the_schools_own_scale(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 14);
        $this->recordResult($this->pupil('Bih', $this->classA), $this->term1, 8);

        $bands = collect($this->report()['bands'])->keyBy('grade');

        $this->assertSame(1, $bands['A']['count']);
        $this->assertSame(1, $bands['B']['count']);
    }

    // ------------------------------------------------------------ the screen

    public function test_the_screen_renders_and_says_when_nothing_is_published(): void
    {
        $this->pupil('Ada', $this->classA);

        $this->get(route('admin.analytics.index', ['term_id' => $this->term1->id]))
            ->assertSuccessful()
            ->assertSee(__('No results have been published for :term yet. Figures appear here once report cards are generated and published.', [
                'term' => 'First Term',
            ]), false);
    }

    public function test_the_subject_export_is_a_csv_with_a_bom(): void
    {
        $this->recordResult($this->pupil('Ada', $this->classA), $this->term1, 12, [
            'Mathematics' => [8, 'Mr Tabi'],
        ]);

        $response = $this->get(route('admin.analytics.export', ['term_id' => $this->term1->id]))
            ->assertSuccessful();

        $body = $response->streamedContent();

        // Excel reads the file as the system codepage without the BOM.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Mathematics', $body);
    }
}
