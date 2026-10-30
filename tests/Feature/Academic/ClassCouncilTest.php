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
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\ClassCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The class council.
 *
 * The school recorded a class teacher remark, a principal remark and a
 * conduct grade on each term result, but nothing recorded who decided them or
 * what was awarded. These tests pin down the two rules that make a council
 * worth holding: the system suggests and the council decides, and what the
 * meeting saw stays true afterwards.
 */
class ClassCouncilTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Term $term;
    private ClassSection $class;
    private Batch $batch;
    private AcademicSession $session;

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
    }

    private function pupilWithResult(string $name, float $average, ?int $rank = null, bool $published = true): TermResult
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

        return TermResult::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'term_id' => $this->term->id,
            'total_weighted_score' => $average * 2,
            'total_coefficient' => 2,
            'term_average' => $average,
            'class_rank' => $rank,
            'is_published' => $published,
        ]);
    }

    private function openCouncil(): ClassCouncil
    {
        return app(ClassCouncilService::class)->open($this->class, $this->term);
    }

    // ------------------------------------------------------------ opening

    public function test_opening_pulls_in_every_pupil_with_a_published_result(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $this->pupilWithResult('Bih', 11, 2);
        // Unpublished: the school has not finished checking it, so the council
        // has nothing to deliberate on.
        $this->pupilWithResult('Che', 4, 3, published: false);

        $council = $this->openCouncil();

        $this->assertSame(2, $council->entries->count());
        $this->assertSame('CC-'.now()->year.'-0001', $council->reference);
        $this->assertTrue($council->isOpen());
    }

    public function test_opening_twice_returns_the_same_sitting(): void
    {
        $this->pupilWithResult('Ada', 16, 1);

        $first = $this->openCouncil();
        $second = $this->openCouncil();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ClassCouncil::count());
        $this->assertSame(1, $second->entries->count());
    }

    public function test_a_result_published_after_the_sitting_opened_is_picked_up(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $council = $this->openCouncil();
        $this->assertSame(1, $council->entries->count());

        $this->pupilWithResult('Bih', 12, 2);

        $this->assertSame(2, $this->openCouncil()->entries->count());
    }

    // ------------------------------------------------------------ suggestions

    public function test_awards_are_suggested_from_the_average(): void
    {
        $service = app(ClassCouncilService::class);

        // Out of 20, with a pass mark of 10.
        $this->assertSame(ClassCouncilEntry::CONGRATULATIONS, $service->suggestAward(17));
        $this->assertSame(ClassCouncilEntry::HONOUR_ROLL, $service->suggestAward(14.5));
        $this->assertSame(ClassCouncilEntry::ENCOURAGEMENT, $service->suggestAward(12.5));
        $this->assertSame(ClassCouncilEntry::NONE, $service->suggestAward(10.5));
        // A council that says nothing about a failing pupil has not deliberated.
        $this->assertSame(ClassCouncilEntry::WORK_WARNING, $service->suggestAward(7));
    }

    public function test_the_council_can_override_what_was_suggested(): void
    {
        $this->pupilWithResult('Ada', 17, 1);
        $council = $this->openCouncil();
        $entry = $council->entries->first();

        $this->assertSame(ClassCouncilEntry::CONGRATULATIONS, $entry->award);

        $this->post(route('admin.councils.decisions', $council), [
            'entries' => [
                $entry->id => [
                    'award' => ClassCouncilEntry::CONDUCT_WARNING,
                    'conduct' => 'poor',
                    'remark' => 'Strong marks, unacceptable behaviour.',
                ],
            ],
        ])->assertRedirect();

        $entry->refresh();
        $this->assertSame(ClassCouncilEntry::CONDUCT_WARNING, $entry->award);
        $this->assertSame('poor', $entry->conduct);
        $this->assertSame('Strong marks, unacceptable behaviour.', $entry->remark);
    }

    public function test_decisions_posted_for_another_councils_entry_are_ignored(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $council = $this->openCouncil();

        $other = ClassCouncilEntry::create([
            'branch_id' => $this->branch->id,
            'class_council_id' => ClassCouncil::create([
                'branch_id' => $this->branch->id,
                'class_section_id' => $this->class->id,
                'term_id' => Term::create([
                    'branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term',
                    'start_date' => '2026-01-05', 'end_date' => '2026-03-30',
                ])->id,
                'status' => ClassCouncil::OPEN,
            ])->id,
            'student_id' => Student::first()->id,
            'award' => ClassCouncilEntry::NONE,
        ]);

        $this->post(route('admin.councils.decisions', $council), [
            'entries' => [$other->id => ['award' => ClassCouncilEntry::CONGRATULATIONS]],
        ])->assertRedirect();

        $this->assertSame(ClassCouncilEntry::NONE, $other->fresh()->award);
    }

    // ------------------------------------------------------------ concluding

    public function test_concluding_freezes_what_the_meeting_saw(): void
    {
        $result = $this->pupilWithResult('Ada', 16, 1);
        $this->pupilWithResult('Bih', 8, 2);

        $council = $this->openCouncil();

        $this->post(route('admin.councils.conclude', $council))->assertRedirect();

        $council->refresh();
        $this->assertTrue($council->isConcluded());
        $this->assertSame(2, $council->students);
        $this->assertSame(1, $council->passed);
        $this->assertSame(1, $council->failed);
        $this->assertSame('12.00', $council->class_average);
        $this->assertSame('50.00', $council->pass_rate);

        // A mark corrected next week must not rewrite the minutes.
        $result->update(['term_average' => 4]);

        $this->assertSame('12.00', $council->fresh()->class_average);
    }

    public function test_a_concluded_council_refuses_further_decisions(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $council = $this->openCouncil();
        $entry = $council->entries->first();

        $this->post(route('admin.councils.conclude', $council));

        $this->post(route('admin.councils.decisions', $council), [
            'entries' => [$entry->id => ['award' => ClassCouncilEntry::NONE]],
        ])->assertForbidden();

        $this->assertSame(ClassCouncilEntry::CONGRATULATIONS, $entry->fresh()->award);
    }

    public function test_a_concluded_council_cannot_be_deleted_but_can_be_reopened(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $council = $this->openCouncil();
        $this->post(route('admin.councils.conclude', $council));

        $this->delete(route('admin.councils.destroy', $council))->assertForbidden();
        $this->assertSame(1, ClassCouncil::count());

        $this->post(route('admin.councils.reopen', $council))->assertRedirect();
        $this->assertTrue($council->fresh()->isOpen());

        $this->delete(route('admin.councils.destroy', $council))->assertRedirect();
        $this->assertSame(0, ClassCouncil::count());
    }

    public function test_a_council_with_nobody_in_it_cannot_be_concluded(): void
    {
        $council = ClassCouncil::create([
            'branch_id' => $this->branch->id,
            'class_section_id' => $this->class->id,
            'term_id' => $this->term->id,
            'status' => ClassCouncil::OPEN,
        ]);

        $this->post(route('admin.councils.conclude', $council))->assertRedirect();

        $this->assertTrue($council->fresh()->isOpen());
    }

    // ------------------------------------------------------------ the screens

    public function test_opening_a_class_with_no_published_results_says_so(): void
    {
        $this->pupilWithResult('Ada', 16, 1, published: false);

        $this->post(route('admin.councils.store'), [
            'class_section_id' => $this->class->id,
            'term_id' => $this->term->id,
        ])->assertRedirect()->assertSessionHas('error');
    }

    public function test_the_index_lists_classes_that_have_not_sat(): void
    {
        $response = $this->get(route('admin.councils.index', ['term_id' => $this->term->id]))
            ->assertSuccessful();

        $this->assertSame(['Form 1A'], $response->viewData('without')->pluck('name')->all());
    }

    public function test_the_deliberation_screen_shows_each_pupil_and_their_suggestion(): void
    {
        $this->pupilWithResult('Ada', 17, 1);
        $council = $this->openCouncil();

        $this->get(route('admin.councils.show', $council))
            ->assertSuccessful()
            ->assertSee('Ada Pupil')
            ->assertSee(__('Congratulations'));
    }

    public function test_signatures_are_recorded_and_can_be_removed(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $council = $this->openCouncil();

        $this->post(route('admin.councils.signatures.store', $council), [
            'name' => 'Mrs Eyong', 'role' => 'Class teacher',
        ])->assertRedirect();

        $signature = $council->signatures()->firstOrFail();
        $this->assertNotNull($signature->signed_at);

        $this->delete(route('admin.councils.signatures.destroy', [$council, $signature]))->assertRedirect();
        $this->assertSame(0, $council->signatures()->count());
    }

    public function test_the_minutes_download_as_a_pdf(): void
    {
        $this->pupilWithResult('Ada', 16, 1);
        $council = $this->openCouncil();

        $response = $this->get(route('admin.councils.minutes', $council))->assertSuccessful();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
