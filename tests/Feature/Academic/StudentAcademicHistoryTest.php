<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassCouncil;
use App\Models\ClassCouncilEntry;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\StudentAcademicHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One pupil across every term they have been here.
 *
 * Report cards existed per term and nothing put them side by side. What
 * matters is that the record is complete and honest: a year with no results
 * still appears as a year, and what the council decided travels with it.
 */
class StudentAcademicHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Student $student;
    private Batch $batch;
    private ClassSection $classA;
    private ClassSection $classB;
    private Subject $maths;
    private AcademicSession $year1;
    private AcademicSession $year2;
    private Term $term1;
    private Term $term2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both',
            'pass_mark' => 10, 'max_mark' => 20,
        ]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->year1 = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2024/2025',
            'start_date' => '2024-09-01', 'end_date' => '2025-07-31', 'status' => 'completed',
        ]);
        $this->year2 = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);

        $this->term1 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->term2 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term',
            'start_date' => '2026-01-05', 'end_date' => '2026-03-30',
        ]);

        $this->batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2024', 'shortcode' => '24', 'is_active' => true,
        ]);

        $form1 = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => 1, 'is_active' => true,
        ]);
        $form2 = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 2', 'short_name' => 'F2',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => 2, 'is_active' => true,
        ]);

        $this->classA = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form1->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->classB = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form2->id,
            'section' => 'A', 'name' => 'Form 2A', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->maths = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true,
        ]);

        $this->student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'TS240001',
            'batch_id' => $this->batch->id,
            'first_name' => 'Ada', 'last_name' => 'Pupil',
            'date_of_birth' => '2011-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2024-09-05',
        ]);
    }

    private function enrol(AcademicSession $session, ClassSection $section, ?float $finalAverage = null, ?int $finalRank = null): StudentEnrollment
    {
        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->student->id,
            'academic_session_id' => $session->id, 'term_id' => $this->term1->id,
            'class_section_id' => $section->id,
            'residence_type' => 'day', 'enrollment_date' => $session->start_date,
            'status' => 'active',
            'final_average' => $finalAverage, 'final_rank' => $finalRank,
        ]);
    }

    private function recordResult(StudentEnrollment $enrollment, Term $term, float $average, array $extra = []): TermResult
    {
        $result = TermResult::create(array_merge([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'term_id' => $term->id,
            'total_weighted_score' => $average * 2,
            'total_coefficient' => 2,
            'term_average' => $average,
            'is_published' => true,
        ], $extra));

        SubjectTermResult::create([
            'branch_id' => $this->branch->id,
            'term_result_id' => $result->id,
            'subject_id' => $this->maths->id,
            'coefficient' => 1,
            'term_average' => $average,
            'weighted_score' => $average,
        ]);

        return $result;
    }

    private function history(): array
    {
        return app(StudentAcademicHistoryService::class)->forStudent($this->student);
    }

    // ------------------------------------------------------------ the record

    public function test_years_appear_oldest_first_with_their_class(): void
    {
        $this->enrol($this->year1, $this->classA, 12.5, 4);
        $this->enrol($this->year2, $this->classB);

        $years = $this->history()['years'];

        $this->assertSame(['2024/2025', '2025/2026'], array_column($years, 'session'));
        $this->assertSame(['Form 1A', 'Form 2A'], array_column($years, 'class'));
        $this->assertSame(12.5, $years[0]['final_average']);
        $this->assertSame(4, $years[0]['final_rank']);
    }

    public function test_a_year_with_no_results_still_appears_as_a_year(): void
    {
        // Dropping it would quietly rewrite the pupil's record.
        $this->enrol($this->year1, $this->classA);

        $years = $this->history()['years'];

        $this->assertCount(1, $years);
        $this->assertFalse($years[0]['has_results']);
        $this->assertSame([], $years[0]['terms']);
    }

    public function test_terms_are_ordered_within_their_year(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term2, 13);
        $this->recordResult($enrollment, $this->term1, 11);

        $terms = $this->history()['years'][0]['terms'];

        $this->assertSame(['First Term', 'Second Term'], array_column($terms, 'term'));
    }

    // ------------------------------------------------------------ the summary

    public function test_the_summary_says_where_the_pupil_started_and_ended(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 10);
        $this->recordResult($enrollment, $this->term2, 14);

        $summary = $this->history()['summary'];

        $this->assertSame(1, $summary['years']);
        $this->assertSame(2, $summary['terms']);
        $this->assertSame(12.0, $summary['average']);
        $this->assertSame(14.0, $summary['best']);
        $this->assertSame(10.0, $summary['worst']);
        $this->assertSame(4.0, $summary['direction']);
    }

    public function test_attendance_is_totalled_across_the_whole_record(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 12, ['days_present' => 55, 'days_absent' => 5, 'total_school_days' => 60]);
        $this->recordResult($enrollment, $this->term2, 12, ['days_present' => 35, 'days_absent' => 5, 'total_school_days' => 40]);

        $this->assertSame(90.0, $this->history()['summary']['attendance_rate']);
    }

    public function test_direction_needs_two_terms_to_mean_anything(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 12);

        $this->assertNull($this->history()['summary']['direction']);
    }

    // ------------------------------------------------------------ councils

    public function test_a_concluded_councils_decision_travels_with_the_term(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 17);

        $council = ClassCouncil::create([
            'branch_id' => $this->branch->id,
            'class_section_id' => $this->classB->id,
            'term_id' => $this->term1->id,
            'status' => ClassCouncil::CONCLUDED,
            'concluded_at' => now(),
        ]);
        ClassCouncilEntry::create([
            'branch_id' => $this->branch->id,
            'class_council_id' => $council->id,
            'student_id' => $this->student->id,
            'award' => ClassCouncilEntry::CONGRATULATIONS,
            'remark' => 'A pleasure to teach.',
        ]);

        $term = $this->history()['years'][0]['terms'][0];

        $this->assertSame(__('Congratulations'), $term['council']['label']);
        $this->assertSame('A pleasure to teach.', $term['council']['remark']);
    }

    public function test_an_open_councils_decision_is_not_shown_yet(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 17);

        $council = ClassCouncil::create([
            'branch_id' => $this->branch->id,
            'class_section_id' => $this->classB->id,
            'term_id' => $this->term1->id,
            'status' => ClassCouncil::OPEN,
        ]);
        ClassCouncilEntry::create([
            'branch_id' => $this->branch->id,
            'class_council_id' => $council->id,
            'student_id' => $this->student->id,
            'award' => ClassCouncilEntry::CONGRATULATIONS,
        ]);

        // Still being deliberated; it is not the school's decision yet.
        $this->assertNull($this->history()['years'][0]['terms'][0]['council']);
    }

    // ------------------------------------------------------------ subjects

    public function test_subjects_show_their_movement_weakest_first(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 8);
        $this->recordResult($enrollment, $this->term2, 12);

        $subjects = $this->history()['subjects'];

        $this->assertSame('Mathematics', $subjects[0]['name']);
        $this->assertSame(2, $subjects[0]['terms']);
        $this->assertSame(10.0, $subjects[0]['average']);
        $this->assertSame(4.0, $subjects[0]['change']);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_record_renders_for_a_pupil_with_history(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 13);

        $this->get(route('admin.students.history', $this->student))
            ->assertSuccessful()
            ->assertSee('Ada Pupil')
            ->assertSee('2025/2026')
            ->assertSee('Form 2A');
    }

    public function test_a_pupil_with_nothing_on_record_is_said_so(): void
    {
        $this->get(route('admin.students.history', $this->student))
            ->assertSuccessful()
            ->assertSee(__('This pupil has no enrolment on record yet.'));
    }

    public function test_the_record_downloads_as_a_pdf(): void
    {
        $enrollment = $this->enrol($this->year2, $this->classB);
        $this->recordResult($enrollment, $this->term1, 13);

        $response = $this->get(route('admin.students.history.pdf', $this->student))->assertSuccessful();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_the_student_page_links_to_the_record(): void
    {
        $this->get(route('admin.students.show', $this->student))
            ->assertSuccessful()
            ->assertSee(route('admin.students.history', $this->student), false);
    }
}
