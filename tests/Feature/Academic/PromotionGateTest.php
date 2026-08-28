<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Promotion had no gate: a student with no marks at all could be moved up a
 * year. The recommendation that was supposed to inform the decision read a
 * term_results column that does not exist, so it was always null.
 *
 * Worse, the new enrollment carried no term_id and no subjects, which made
 * promoted students invisible to every marks and report-card query — promotion
 * quietly removed a student from the system it had just advanced them through.
 */
class PromotionGateTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private AcademicSession $nextSession;
    private Term $term1;
    private Term $term2;
    private Form $form;
    private ClassSection $section;
    private ClassSection $nextSection;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        SchoolSetting::create([
            'branch_id' => $this->branch->id,
            'school_name' => 'Test School', 'school_code' => 'TS',
            'promotion_threshold' => 10.0,
        ]);

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $this->nextSession = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2026/2027',
            'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
            'status' => 'upcoming', 'is_current' => false,
        ]);

        // Two terms, so "the year is incomplete" is a state the test can reach.
        $this->term1 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->term2 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term',
            'start_date' => '2026-01-05', 'end_date' => '2026-04-10', 'is_current' => false,
        ]);

        $this->form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $nextForm = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 2', 'short_name' => 'F2',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 2, 'is_active' => true,
        ]);

        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $this->form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->nextSection = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $nextForm->id,
            'section' => 'A', 'name' => 'Form 2A', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->subject = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Maths', 'code' => 'MTH', 'is_active' => true,
        ]);

        // Form 2's curriculum — what a promoted student should be registered for.
        DB::table('form_subject')->insert([
            'form_id' => $nextForm->id, 'subject_id' => $this->subject->id,
            'coefficient' => 4, 'type' => 'core',
        ]);
    }

    /**
     * A student's year is spread across one enrollment per term.
     *
     * @param  array<int, float|null>  $termAverages  indexed by term number
     */
    private function student(string $name, string $id, array $termAverages): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => $id,
            'first_name' => $name, 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        $last = null;

        foreach ([1 => $this->term1, 2 => $this->term2] as $number => $term) {
            $last = StudentEnrollment::create([
                'branch_id' => $this->branch->id, 'student_id' => $student->id,
                'academic_session_id' => $this->session->id, 'term_id' => $term->id,
                'class_section_id' => $this->section->id, 'residence_type' => 'day',
                'enrollment_date' => '2025-09-01', 'status' => 'active',
            ]);

            if (array_key_exists($number, $termAverages) && $termAverages[$number] !== null) {
                TermResult::create([
                    'student_enrollment_id' => $last->id,
                    'term_id' => $term->id,
                    'term_average' => $termAverages[$number],
                ]);
            }
        }

        return $last;
    }

    /** @param array<int, array<string, mixed>> $decisions */
    private function process(array $decisions)
    {
        return $this->post(route('admin.promotion.process'), ['decisions' => $decisions]);
    }

    private function promote(StudentEnrollment $enrollment): array
    {
        return [
            'enrollment_id' => $enrollment->id,
            'action' => 'promote',
            'target_class_id' => $this->nextSection->id,
        ];
    }

    public function test_a_student_with_no_results_at_all_is_not_promoted(): void
    {
        $enrollment = $this->student('Ann', 'MAIN0001', []);

        $this->process([$this->promote($enrollment)])->assertSessionHas('error');

        // Regression: this student used to be advanced a year on no evidence.
        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertSame(0, StudentEnrollment::where('academic_session_id', $this->nextSession->id)->count());
    }

    public function test_a_student_missing_a_term_is_not_promoted(): void
    {
        $enrollment = $this->student('Ben', 'MAIN0002', [1 => 15.0]);

        $this->process([$this->promote($enrollment)])->assertSessionHas('error');

        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertSame(0, StudentEnrollment::where('academic_session_id', $this->nextSession->id)->count());
    }

    public function test_a_student_below_the_threshold_is_not_promoted(): void
    {
        $enrollment = $this->student('Cid', 'MAIN0003', [1 => 8.0, 2 => 9.0]);

        $this->process([$this->promote($enrollment)])->assertSessionHas('error');

        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_a_complete_passing_year_is_promoted_with_a_term_and_subjects(): void
    {
        $enrollment = $this->student('Dee', 'MAIN0004', [1 => 11.0, 2 => 13.0]);

        $this->process([$this->promote($enrollment)])->assertSessionHasNoErrors();

        $this->assertSame('promoted', $enrollment->fresh()->status);

        $new = StudentEnrollment::where('academic_session_id', $this->nextSession->id)->firstOrFail();

        // Regression: term_id was never set, so promoted students were invisible
        // to every marks and report-card query.
        $this->assertSame($this->term1->id, $new->term_id);
        $this->assertSame($this->nextSection->id, $new->class_section_id);
        $this->assertSame($this->branch->id, $new->branch_id);

        // Regression: syncCoreSubjects was never called, so they arrived
        // registered for nothing.
        $this->assertSame(1, StudentSubject::where('student_enrollment_id', $new->id)->count());
    }

    public function test_the_annual_average_is_the_mean_of_the_term_averages(): void
    {
        $enrollment = $this->student('Eve', 'MAIN0005', [1 => 11.0, 2 => 14.0]);

        $this->process([$this->promote($enrollment)])->assertSessionHasNoErrors();

        // final_average and final_rank have been displayed on the student page
        // from the start and written by nothing at all.
        $this->assertEquals(12.5, (float) $enrollment->fresh()->final_average);
        $this->assertSame(1, $enrollment->fresh()->final_rank);
    }

    public function test_the_annual_rank_is_dense_across_the_class(): void
    {
        $top = $this->student('Fay', 'MAIN0006', [1 => 16.0, 2 => 16.0]);
        $tied = $this->student('Gus', 'MAIN0007', [1 => 12.0, 2 => 12.0]);
        $alsoTied = $this->student('Hal', 'MAIN0008', [1 => 11.0, 2 => 13.0]);

        $this->process([$this->promote($top)])->assertSessionHasNoErrors();

        $this->assertSame(1, $top->fresh()->final_rank);
        // Both average 12.00 — a tie must share a rank.
        $this->assertSame(2, $tied->fresh()->final_rank);
        $this->assertSame(2, $alsoTied->fresh()->final_rank);
    }

    public function test_a_repeating_student_stays_in_their_own_class_by_default(): void
    {
        $enrollment = $this->student('Ivy', 'MAIN0009', [1 => 6.0, 2 => 7.0]);

        $this->process([[
            'enrollment_id' => $enrollment->id,
            'action' => 'repeat',
            'target_class_id' => null,
        ]])->assertSessionHasNoErrors();

        $new = StudentEnrollment::where('academic_session_id', $this->nextSession->id)->firstOrFail();

        // The fallback used to pick whichever section of the form sorted first.
        $this->assertSame($this->section->id, $new->class_section_id);
        $this->assertSame($this->term1->id, $new->term_id);
    }

    public function test_running_the_same_promotion_twice_does_not_duplicate_the_enrollment(): void
    {
        $enrollment = $this->student('Joy', 'MAIN0010', [1 => 12.0, 2 => 12.0]);

        $this->process([$this->promote($enrollment)])->assertSessionHasNoErrors();
        $this->process([$this->promote($enrollment)]);

        // The unique key is (student, session, term): a second run must not blow
        // up halfway through a batch.
        $this->assertSame(1, StudentEnrollment::where('academic_session_id', $this->nextSession->id)->count());
    }

    public function test_the_screen_says_why_a_student_cannot_be_promoted(): void
    {
        $this->student('Kim', 'MAIN0011', [1 => 15.0]);

        $students = $this->get(route('admin.promotion.index', [
            'from_session_id' => $this->session->id,
            'class_section_id' => $this->section->id,
        ]))->assertSuccessful()->viewData('students');

        $this->assertCount(1, $students);
        $this->assertFalse($students->first()->is_eligible);
        $this->assertStringContainsString('missing', $students->first()->ineligible_reason);
    }
}
