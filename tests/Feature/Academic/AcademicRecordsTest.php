<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\AdmissionStep;
use App\Models\Applicant;
use App\Models\Branch;
use App\Models\ClassCouncil;
use App\Models\ClassCouncilEntry;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\IssuedDocument;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\ClassCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The records that leave the school or decide a pupil's future, the reference
 * system's way: certificates in a numbered register anyone can check, the
 * year-end class council deciding who moves up, and admission taken step by
 * step before the decision.
 */
class AcademicRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $year;
    private AcademicSession $nextYear;
    private Term $term;
    private Form $form;
    private ClassSection $class;
    private ClassSection $nextClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'pass_mark' => 10, 'max_mark' => 20, 'promotion_threshold' => 10]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $this->nextYear = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2027/2028', 'start_date' => '2027-09-01', 'end_date' => '2028-07-31', 'status' => 'upcoming', 'is_current' => false]);
        // One term: it is also the year's last, so its council is the year-end one.
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2027-06-30', 'is_current' => true]);
        $this->form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $form2 = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 2', 'short_name' => 'F2', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 2, 'is_active' => true]);
        $this->class = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $this->nextClass = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form2->id, 'section' => 'A', 'name' => 'Form 2A', 'max_students' => 40, 'is_active' => true]);
    }

    private function pupil(string $name, float $average, string $status = 'active'): StudentEnrollment
    {
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS26'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT), 'first_name' => $name, 'last_name' => 'PUPIL', 'date_of_birth' => '2014-03-02', 'gender' => 'female', 'status' => $status, 'admission_date' => '2026-09-07']);
        $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->year->id, 'term_id' => $this->term->id, 'class_section_id' => $this->class->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        TermResult::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'term_id' => $this->term->id, 'total_weighted_score' => $average, 'total_coefficient' => 1, 'term_average' => $average, 'class_rank' => 1, 'is_published' => true]);

        return $enrollment;
    }

    // ── Certificates in a numbered register ───────────────────────────────

    public function test_every_certificate_printed_gets_the_next_serial_and_a_check_code(): void
    {
        $student = $this->pupil('Cid', 12, 'graduated')->student;
        $student->update(['withdrawal_date' => '2027-07-15']);
        $year = now()->year;

        $pdf = $this->get(route('admin.documents.leaving-certificate', $student))->assertSuccessful()->getContent();
        $this->assertStringContainsString('/Subtype /Image', $pdf, 'the QR code is drawn on the certificate');
        $this->get(route('admin.students.leaving-certificate', $student))->assertSuccessful();
        $this->get(route('admin.documents.cumulative-record', $student))->assertSuccessful();

        $this->assertSame(["LC-{$year}-0001", "LC-{$year}-0002", "CR-{$year}-0001"], IssuedDocument::orderBy('id')->pluck('serial')->all());

        $first = IssuedDocument::where('serial', "LC-{$year}-0001")->sole();
        $this->assertMatchesRegularExpression('/^[2-9A-HJKMNP-Z]{12}$/', $first->verify_code);
        $this->assertSame('15/07/2027', $first->details[__('Date left')]);
        $this->assertSame($this->admin->id, $first->issued_by);

        $this->get(route('admin.issued-documents.index'))->assertOk()->assertSee("LC-{$year}-0002")->assertSee("CR-{$year}-0001");
    }

    public function test_anyone_can_check_a_certificate_against_the_register(): void
    {
        $student = $this->pupil('Cid', 12, 'graduated')->student;
        $this->get(route('admin.documents.leaving-certificate', $student))->assertSuccessful();
        $document = IssuedDocument::sole();
        auth()->logout();

        $this->get(route('document.verify', $document->verify_code))
            ->assertOk()
            ->assertSee(__('Genuine document, issued by the school.'))
            ->assertSee($document->serial)
            ->assertSee(__('Leaving Certificate'))
            ->assertSee('Cid PUPIL');

        $this->get(route('document.verify').'?code='.strtolower($document->verifyCodeForPrint()))
            ->assertRedirect(route('document.verify', $document->verify_code));
        $this->get(route('document.verify', 'ZZZZZZZZZZZZ'))->assertNotFound();
    }

    // ── The year-end council decides who moves up ─────────────────────────

    public function test_the_year_end_council_suggests_next_year_from_the_results(): void
    {
        $this->pupil('Ada', 14);
        $this->pupil('Bih', 7);

        $council = app(ClassCouncilService::class)->open($this->class, $this->term);

        $this->assertTrue($council->isYearEnd());
        $decisions = $council->entries->mapWithKeys(fn ($e) => [$e->student->first_name => $e->year_decision])->all();
        $this->assertSame(['Ada' => ClassCouncilEntry::PROMOTE, 'Bih' => ClassCouncilEntry::REPEAT], $decisions);

        $this->get(route('admin.councils.show', $council))->assertOk()->assertSee(__('Next year'))->assertSee(__('Repeats the year'));
    }

    public function test_promotion_follows_the_concluded_council(): void
    {
        $ada = $this->pupil('Ada', 14);
        $bih = $this->pupil('Bih', 8);   // below the pass average
        $council = app(ClassCouncilService::class)->open($this->class, $this->term);

        // The council moves Bih up on deliberation, and holds Ada back.
        $entries = $council->entries->keyBy(fn ($e) => $e->student->first_name);
        $this->post(route('admin.councils.decisions', $council), ['entries' => [
            $entries['Ada']->id => ['award' => ClassCouncilEntry::NONE, 'year_decision' => ClassCouncilEntry::REPEAT],
            $entries['Bih']->id => ['award' => ClassCouncilEntry::NONE, 'year_decision' => ClassCouncilEntry::PROMOTE],
        ]])->assertRedirect();
        $council->update(['status' => ClassCouncil::CONCLUDED, 'concluded_at' => now()]);

        $this->get(route('admin.promotion.index', ['from_session_id' => $this->year->id, 'class_section_id' => $this->class->id]))
            ->assertOk()
            ->assertSee('data-council-decision', false)
            ->assertSee(__('Class council: :decision', ['decision' => __('Moves up')]));

        $this->post(route('admin.promotion.process'), ['decisions' => [
            ['enrollment_id' => $ada->id, 'action' => 'promote', 'target_class_id' => $this->nextClass->id],
            ['enrollment_id' => $bih->id, 'action' => 'promote', 'target_class_id' => $this->nextClass->id],
        ]])->assertRedirect();

        $this->assertSame('active', $ada->fresh()->status, 'the council held Ada back');
        $this->assertSame('promoted', $bih->fresh()->status, 'the council moved Bih up below the average');
        $this->assertTrue(StudentEnrollment::where('student_id', $bih->student_id)->where('academic_session_id', $this->nextYear->id)->where('class_section_id', $this->nextClass->id)->exists());
    }

    public function test_without_a_concluded_council_the_average_still_decides(): void
    {
        $bih = $this->pupil('Bih', 8);
        app(ClassCouncilService::class)->open($this->class, $this->term);   // open, not concluded

        $this->post(route('admin.promotion.process'), ['decisions' => [
            ['enrollment_id' => $bih->id, 'action' => 'promote', 'target_class_id' => $this->nextClass->id],
        ]]);

        $this->assertSame('active', $bih->fresh()->status);
    }

    // ── Admission, step by step ───────────────────────────────────────────

    private function application(): AdmissionApplication
    {
        $applicant = Applicant::create(['first_name' => 'Ann', 'last_name' => 'Applicant', 'email' => 'ann@example.test', 'password' => 'password']);

        return AdmissionApplication::create([
            'branch_id' => $this->branch->id, 'applicant_id' => $applicant->id, 'application_number' => 'APP-0001',
            'academic_session_id' => $this->year->id, 'form_id' => $this->form->id,
            'first_name' => 'Ann', 'last_name' => 'Applicant', 'date_of_birth' => '2014-01-01', 'gender' => 'female',
            'emergency_contact_name' => 'Guardian', 'emergency_contact_phone' => '670000000', 'status' => 'pending',
        ]);
    }

    private function step(AdmissionApplication $application, string $step, string $outcome, array $extra = [])
    {
        return $this->post(route('admin.admissions.applications.step', $application), ['step' => $step, 'outcome' => $outcome] + $extra);
    }

    public function test_an_application_is_accepted_only_after_its_steps(): void
    {
        $application = $this->application();

        $this->post(route('admin.admissions.applications.accept', $application))->assertSessionHas('error');
        $this->assertSame('pending', $application->fresh()->status);

        // In order only.
        $this->step($application, 'documents', 'done')->assertSessionHas('error');

        $this->step($application, 'received', 'done')->assertSessionHas('success');
        $this->assertSame('under_review', $application->fresh()->status);
        $this->step($application, 'documents', 'done', ['note' => 'Birth certificate and Form 1 report seen'])->assertSessionHas('success');
        $this->step($application, 'assessment', 'waived')->assertSessionHasErrors('note');
        $this->step($application, 'assessment', 'done', ['score' => 14.5])->assertSessionHas('success');

        $this->assertTrue($application->fresh()->stepsComplete());
        $this->post(route('admin.admissions.applications.accept', $application))->assertSessionHas('success');
        $this->assertSame('accepted', $application->fresh()->status);

        // The applicant sees the steps, without the school's notes.
        $this->actingAs($application->applicant, 'applicant')
            ->get(route('apply.show', $application))
            ->assertOk()
            ->assertSee('data-applicant-step="assessment"', false)
            ->assertDontSee('Birth certificate and Form 1 report seen');
    }

    public function test_sending_a_step_back_clears_it_and_the_later_ones_but_keeps_history(): void
    {
        $application = $this->application();
        $this->step($application, 'received', 'done');
        $this->step($application, 'documents', 'done');
        $this->step($application, 'assessment', 'waived', ['note' => 'Transfer from a partner school']);

        $this->step($application, 'documents', 'returned')->assertSessionHasErrors('note');
        $this->step($application, 'documents', 'returned', ['note' => 'Report card unreadable'])->assertSessionHas('success');

        $fresh = $application->fresh();
        $this->assertSame('documents', $fresh->nextStep());
        $this->assertNotNull($fresh->stepStanding()['received'], 'what came before stands');
        $this->assertNull($fresh->stepStanding()['assessment'], 'what came after is cleared');
        $this->assertSame(4, AdmissionStep::count(), 'nothing is deleted');

        $this->get(route('admin.admissions.applications.show', $application))
            ->assertOk()->assertSee('data-admission-steps', false)->assertSee('Report card unreadable');
    }
}
