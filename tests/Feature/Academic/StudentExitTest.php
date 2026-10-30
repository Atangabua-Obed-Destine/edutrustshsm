<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\StudentExitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A pupil leaving the school.
 *
 * There was no way to leave: `students.status` offered graduated, withdrawn
 * and expelled, but only the edit form touched it and nothing wrote a date or
 * a reason. What matters most is not the student row — it is that class
 * lists, fee reports and attendance all count active enrolments, so an exit
 * has to close the enrolment or the pupil keeps counting forever.
 */
class StudentExitTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Student $student;
    private ClassSection $class;
    private AcademicSession $session;
    private Term $term;
    private Batch $batch;

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

        $this->student = $this->pupil('Bih');
    }

    private function pupil(string $name): Student
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => $name, 'last_name' => 'Pupil',
            'date_of_birth' => '2012-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->class->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);

        return $student;
    }

    private function recordExit(string $type = 'withdrawn', array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.students.exit.store', $this->student), array_merge([
            'type' => $type,
            'left_on' => now()->toDateString(),
            'reason' => 'Family moved to Douala.',
        ], $extra));
    }

    // ------------------------------------------------------------ the record

    public function test_leaving_is_recorded_with_a_date_a_reason_and_who_recorded_it(): void
    {
        $this->recordExit()->assertRedirect(route('admin.students.show', $this->student));

        $this->student->refresh();
        $this->assertSame('withdrawn', $this->student->status);
        $this->assertSame(now()->toDateString(), $this->student->withdrawal_date->toDateString());
        $this->assertSame('Family moved to Douala.', $this->student->withdrawal_reason);
        $this->assertSame($this->admin->id, $this->student->exit_recorded_by);
    }

    public function test_leaving_closes_the_enrolment_which_is_what_takes_them_off_the_lists(): void
    {
        $enrollment = $this->student->enrollments()->firstOrFail();
        $this->assertSame('active', $enrollment->status);

        $this->recordExit();

        $this->assertSame('withdrawn', $enrollment->fresh()->status);
        // The relation every class list and report reads from.
        $this->assertNull($this->student->fresh()->currentEnrollment);
    }

    public function test_a_departed_pupil_drops_off_the_student_list(): void
    {
        $staying = $this->pupil('Ada');

        $before = $this->get(route('admin.students.index'))->assertSuccessful();
        $this->assertCount(2, $before->viewData('students'));

        $this->recordExit();

        $after = $this->get(route('admin.students.index'))->assertSuccessful();
        $names = collect($after->viewData('students')->items())->pluck('first_name')->all();

        $this->assertSame(['Ada'], $names);
        $this->assertTrue($staying->fresh()->status === 'active');
    }

    public function test_each_kind_of_leaving_closes_the_enrolment_in_its_own_terms(): void
    {
        $cases = [
            'graduated' => ['graduated', 'completed'],
            'transferred' => ['withdrawn', 'transferred'],
            'expelled' => ['expelled', 'withdrawn'],
        ];

        foreach ($cases as $type => [$studentStatus, $enrolmentStatus]) {
            $this->student = $this->pupil('Pupil'.$type);

            $this->recordExit($type, $type === 'transferred' ? ['destination_school' => 'Sacred Heart College'] : []);

            $this->assertSame($studentStatus, $this->student->fresh()->status, $type);
            $this->assertSame($enrolmentStatus, $this->student->enrollments()->first()->status, $type);
        }
    }

    public function test_a_transfer_must_name_the_receiving_school(): void
    {
        $this->recordExit('transferred')->assertSessionHasErrors('destination_school');

        $this->assertSame('active', $this->student->fresh()->status);
    }

    public function test_a_transfer_records_where_the_pupil_went(): void
    {
        $this->recordExit('transferred', ['destination_school' => 'Sacred Heart College']);

        $this->assertSame('Sacred Heart College', $this->student->fresh()->destination_school);
    }

    public function test_leaving_cannot_be_dated_in_the_future(): void
    {
        $this->recordExit('withdrawn', ['left_on' => now()->addWeek()->toDateString()])
            ->assertSessionHasErrors('left_on');

        $this->assertSame('active', $this->student->fresh()->status);
    }

    public function test_a_pupil_who_has_already_left_cannot_leave_again(): void
    {
        $this->recordExit();

        $this->get(route('admin.students.exit', $this->student))->assertNotFound();
        $this->recordExit()->assertNotFound();
    }

    // ------------------------------------------------------------ coming back

    public function test_a_pupil_can_be_re_admitted_and_their_exit_is_cleared(): void
    {
        $this->recordExit();

        $this->post(route('admin.students.readmit', $this->student))->assertRedirect();

        $student = $this->student->fresh();
        $this->assertSame('active', $student->status);
        $this->assertNull($student->withdrawal_date);
        $this->assertNull($student->withdrawal_reason);

        // Re-admission does not reopen the old enrolment — the class and year
        // they return into are decisions, not a reversal.
        $this->assertSame('withdrawn', $this->student->enrollments()->first()->status);
    }

    public function test_an_active_pupil_cannot_be_re_admitted(): void
    {
        $this->post(route('admin.students.readmit', $this->student))->assertNotFound();
    }

    // ------------------------------------------------------------ the archive

    public function test_the_archive_lists_only_pupils_who_have_left(): void
    {
        $this->pupil('Ada');
        $this->recordExit('graduated');

        $response = $this->get(route('admin.students.archive'))->assertSuccessful();

        $this->assertSame(['Bih'], collect($response->viewData('students')->items())->pluck('first_name')->all());
    }

    public function test_the_archive_filters_by_how_they_left(): void
    {
        $this->recordExit('graduated');
        $this->student = $this->pupil('Che');
        $this->recordExit('expelled');

        $response = $this->get(route('admin.students.archive', ['status' => 'expelled']))->assertSuccessful();

        $this->assertSame(['Che'], collect($response->viewData('students')->items())->pluck('first_name')->all());
    }

    // ------------------------------------------------------------ the certificate

    public function test_a_leaving_certificate_downloads_for_a_departed_pupil(): void
    {
        $this->recordExit('transferred', ['destination_school' => 'Sacred Heart College']);

        $response = $this->get(route('admin.students.leaving-certificate', $this->student))->assertSuccessful();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_no_certificate_for_a_pupil_who_is_still_here(): void
    {
        $this->get(route('admin.students.leaving-certificate', $this->student))->assertNotFound();
    }

    // ------------------------------------------------------------ the warning

    public function test_the_form_shows_what_the_pupil_still_owes(): void
    {
        $response = $this->get(route('admin.students.exit', $this->student))->assertSuccessful();

        // No fees in this fixture, so nothing owed — the point is that the
        // figure is computed and offered rather than discovered later.
        $this->assertSame(0.0, $response->viewData('balance'));
    }
}
