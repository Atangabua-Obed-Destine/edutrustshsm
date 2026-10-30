<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Subject;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\SchoolSituationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The school's own report card.
 *
 * Every figure already existed on some screen; what is new is that they are
 * compared with last term and written out as sentences. The tests care most
 * about those sentences — a report that says "down 6 points" when nothing
 * moved is worse than no report.
 */
class SchoolSituationReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $session;
    private Term $term1;
    private Term $term2;
    private ClassSection $class;
    private Subject $maths;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both', 'pass_mark' => 10, 'max_mark' => 20,
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

        // Dated in the past so the window is closed and fully countable.
        $this->term1 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => now()->subMonths(6)->toDateString(), 'end_date' => now()->subMonths(4)->toDateString(),
        ]);
        $this->term2 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term',
            'start_date' => now()->subMonths(3)->toDateString(), 'end_date' => now()->subMonth()->toDateString(),
            'is_current' => true,
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

        $this->maths = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true,
        ]);
    }

    private function pupil(string $name, ?string $admittedOn = null): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => $name, 'last_name' => 'Pupil',
            'date_of_birth' => '2012-04-11', 'gender' => 'female',
            'status' => 'active',
            'admission_date' => $admittedOn ?? now()->subYear()->toDateString(),
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term1->id,
            'class_section_id' => $this->class->id,
            'residence_type' => 'day', 'enrollment_date' => $this->session->start_date, 'status' => 'active',
        ]);
    }

    private function recordResult(StudentEnrollment $enrollment, Term $term, float $average): TermResult
    {
        $result = TermResult::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'term_id' => $term->id,
            'total_weighted_score' => $average * 2,
            'total_coefficient' => 2,
            'term_average' => $average,
            'is_published' => true,
        ]);

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

    private function report(?Term $term = null): array
    {
        return app(SchoolSituationReportService::class)->forTerm($term ?? $this->term2);
    }

    /** @param array<int, array<string, mixed>> $highlights */
    private function texts(array $highlights): string
    {
        return implode(' | ', array_column($highlights, 'text'));
    }

    // ------------------------------------------------------------ movement

    public function test_it_counts_who_joined_and_who_left_within_the_term(): void
    {
        $this->pupil('Ada');
        $this->pupil('Bih', admittedOn: $this->term2->start_date->addDays(3)->toDateString());

        // Left during the term.
        $leaver = $this->pupil('Che');
        $leaver->student->update([
            'status' => 'withdrawn',
            'withdrawal_date' => $this->term2->start_date->addDays(10)->toDateString(),
        ]);
        $leaver->update(['status' => 'withdrawn']);

        $enrolment = $this->report()['enrolment'];

        $this->assertSame(2, $enrolment['active']);
        $this->assertSame(1, $enrolment['admitted']);
        $this->assertSame(1, $enrolment['left']);
        $this->assertSame(0, $enrolment['net']);
        $this->assertSame(['withdrawn' => 1], $enrolment['by_reason']);
    }

    // ------------------------------------------------------------ the sentences

    public function test_a_falling_pass_rate_is_said_plainly_and_blamed_on_the_right_class(): void
    {
        $ada = $this->pupil('Ada');
        $bih = $this->pupil('Bih');

        // Everyone passed last term.
        $this->recordResult($ada, $this->term1, 14);
        $this->recordResult($bih, $this->term1, 12);

        // Half of them fail this term.
        $this->recordResult($ada, $this->term2, 14);
        $this->recordResult($bih, $this->term2, 6);

        $highlights = $this->report()['highlights'];
        $text = $this->texts($highlights);

        $this->assertStringContainsString(__('First Term'), $text);
        $this->assertStringContainsString('50', $text);
        $this->assertStringContainsString('Form 1A', $text);
        $this->assertContains('bad', array_column($highlights, 'tone'));
    }

    public function test_a_steady_pass_rate_says_so_rather_than_inventing_movement(): void
    {
        $ada = $this->pupil('Ada');
        $this->recordResult($ada, $this->term1, 14);
        $this->recordResult($ada, $this->term2, 13.8);

        $this->assertStringContainsString(
            __('The pass rate held steady at :rate%.', ['rate' => 100.0]),
            $this->texts($this->report()['highlights'])
        );
    }

    public function test_the_weakest_subject_is_named_when_the_gap_is_real(): void
    {
        $ada = $this->pupil('Ada');

        // One subject only, so no gap against the pupil's own average: it must
        // not be reported as the worst subject.
        $this->recordResult($ada, $this->term1, 12);
        $this->recordResult($ada, $this->term2, 12);

        $this->assertStringNotContainsString('Mathematics', $this->texts($this->report()['highlights']));
    }

    public function test_the_report_admits_when_it_only_covers_some_pupils(): void
    {
        $ada = $this->pupil('Ada');
        $this->pupil('Bih');
        $this->pupil('Che');

        $this->recordResult($ada, $this->term1, 12);
        $this->recordResult($ada, $this->term2, 12);

        $text = $this->texts($this->report()['highlights']);

        $this->assertStringContainsString('33.3', $text);
        $this->assertContains('warning', array_column($this->report()['highlights'], 'tone'));
    }

    public function test_there_are_no_comparisons_for_the_first_term(): void
    {
        $ada = $this->pupil('Ada');
        $this->recordResult($ada, $this->term1, 12);

        $report = $this->report($this->term1);

        $this->assertNull($report['previous_term']);
        $this->assertNull($report['comparison']);
        $this->assertSame([], $report['highlights']);
    }

    // ------------------------------------------------------------ money

    public function test_fees_are_measured_over_the_session_not_the_term(): void
    {
        $enrollment = $this->pupil('Ada');

        $category = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true,
        ]);

        StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => 100000, 'net_amount' => 100000,
            'paid_amount' => 75000, 'balance' => 25000,
            'status' => 'partial', 'due_date' => now()->toDateString(),
        ]);

        $finance = $this->report()['finance'];

        $this->assertSame(100000.0, $finance['billed']);
        $this->assertSame(75000.0, $finance['collected']);
        $this->assertSame(25000.0, $finance['outstanding']);
        $this->assertSame(75.0, $finance['collection_rate']);
    }

    // ------------------------------------------------------------ attendance

    public function test_attendance_is_counted_inside_the_terms_own_dates(): void
    {
        $enrollment = $this->pupil('Ada');

        foreach (['present', 'present', 'present', 'absent'] as $i => $status) {
            Attendance::create([
                'branch_id' => $this->branch->id,
                'student_enrollment_id' => $enrollment->id,
                'date' => $this->term2->start_date->addDays($i + 1)->toDateString(),
                'status' => $status,
                'recorded_by' => $this->admin->id,
            ]);
        }

        // Outside the window: belongs to the term before.
        Attendance::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'date' => $this->term1->start_date->addDay()->toDateString(),
            'status' => 'absent',
            'recorded_by' => $this->admin->id,
        ]);

        $attendance = $this->report()['attendance'];

        $this->assertSame(4, $attendance['marked']);
        $this->assertSame(75.0, $attendance['rate']);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_screen_renders(): void
    {
        $ada = $this->pupil('Ada');
        $this->recordResult($ada, $this->term2, 13);

        $response = $this->get(route('admin.analytics.school-report', ['term_id' => $this->term2->id]))
            ->assertSuccessful()
            ->assertSee(__('State of the School'));

        $report = $response->viewData('report');

        $this->assertSame(1, $report['enrolment']['active']);
        $this->assertSame(['Form 1' => 1], $report['enrolment']['by_form']);
        $this->assertSame(1, $report['academics']['students']);
    }

    public function test_the_report_downloads_as_a_pdf(): void
    {
        $ada = $this->pupil('Ada');
        $this->recordResult($ada, $this->term2, 13);

        $response = $this->get(route('admin.analytics.school-report.pdf', ['term_id' => $this->term2->id]))
            ->assertSuccessful();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
