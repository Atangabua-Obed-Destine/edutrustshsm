<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\NurseryAssessment;
use App\Models\NurseryCompetence;
use App\Models\NurseryLearningArea;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\NurseryAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A nursery child is observed, not examined.
 *
 * Until now Nursery 1 went through the same machinery as Form 5: marks per
 * sequence, a weighted average, a rank, PASS / FAIL. The nursery sheet rates
 * each competence acquired / in progress / not yet, the report prints the
 * grid, and nothing on the way computes a number.
 */
class NurseryAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $session;
    private Term $term;
    private Term $term2;
    private Batch $batch;
    private Form $nursery;
    private ClassSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10,
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
        $this->term2 = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term',
            'start_date' => '2026-01-05', 'end_date' => '2026-03-31', 'is_current' => false,
        ]);
        $this->batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true,
        ]);

        $this->nursery = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Nursery 1', 'short_name' => 'N1',
            'level' => 'nursery', 'school_level' => 'nursery_primary', 'education_system' => 'english',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $this->nursery->id,
            'section' => 'A', 'name' => 'N1', 'max_students' => 30, 'is_active' => true,
        ]);
    }

    private function pupil(string $first, string $last = 'Pupil'): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => $first, 'last_name' => $last,
            'date_of_birth' => '2021-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->section->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);
    }

    /** @return array{0: NurseryCompetence, 1: NurseryCompetence} */
    private function smallCatalogue(): array
    {
        $area = NurseryLearningArea::create(['branch_id' => $this->branch->id, 'name' => 'Pre-mathematics', 'display_order' => 1]);
        $counts = $area->competences()->create(['branch_id' => $this->branch->id, 'description' => 'Counts objects up to ten', 'display_order' => 1]);
        $shapes = $area->competences()->create(['branch_id' => $this->branch->id, 'description' => 'Names the basic shapes', 'display_order' => 2]);

        return [$counts, $shapes];
    }

    // ------------------------------------------------------------ the list

    public function test_the_standard_list_can_be_loaded_once(): void
    {
        $this->post(route('admin.nursery.competences.seed'))->assertSessionHas('success');

        $this->assertSame(6, NurseryLearningArea::count());
        $this->assertGreaterThan(20, NurseryCompetence::count());

        $this->post(route('admin.nursery.competences.seed'))->assertSessionHas('error');
        $this->assertSame(6, NurseryLearningArea::count());

        $this->get(route('admin.nursery.competences.index'))
            ->assertSuccessful()
            ->assertSee(__('Pre-mathematics'))
            ->assertSee(__('Counts objects up to ten'));
    }

    public function test_a_competence_can_be_kept_to_one_class(): void
    {
        [$counts] = $this->smallCatalogue();
        $n2 = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Nursery 2', 'short_name' => 'N2',
            'level' => 'nursery', 'school_level' => 'nursery_primary', 'education_system' => 'english',
            'has_streams' => false, 'display_order' => 2, 'is_active' => true,
        ]);

        $this->post(route('admin.nursery.competences.store', $counts->learningArea), [
            'description' => 'Writes own first name', 'form_id' => $n2->id,
        ])->assertSessionHas('success');

        $service = app(NurseryAssessmentService::class);
        $forN1 = $service->competencesFor($this->nursery)->flatMap(fn ($a) => $a->competences->pluck('description'));
        $forN2 = $service->competencesFor($n2)->flatMap(fn ($a) => $a->competences->pluck('description'));

        $this->assertNotContains('Writes own first name', $forN1->all());
        $this->assertContains('Writes own first name', $forN2->all());
    }

    public function test_a_rated_competence_is_hidden_not_deleted(): void
    {
        [$counts, $shapes] = $this->smallCatalogue();
        $enrollment = $this->pupil('Ada');

        app(NurseryAssessmentService::class)->save($enrollment, $this->term, [$counts->id => 'acquired'], [], $this->admin->id);

        $this->delete(route('admin.nursery.competences.destroy', $counts));
        $this->delete(route('admin.nursery.competences.destroy', $shapes));

        $this->assertFalse($counts->fresh()->is_active, 'rated: hidden');
        $this->assertNull($shapes->fresh(), 'never rated: gone');
    }

    // ------------------------------------------------------------ the sheet

    public function test_the_sheet_rates_each_competence_and_writes_a_report_with_no_average(): void
    {
        [$counts, $shapes] = $this->smallCatalogue();
        $enrollment = $this->pupil('Ada');

        $this->get(route('admin.nursery.edit', [$enrollment, $this->term]))
            ->assertSuccessful()
            ->assertSee('Counts objects up to ten')
            ->assertSee(__('Acquired'));

        $this->post(route('admin.nursery.update', [$enrollment, $this->term]), [
            'ratings' => [$counts->id => 'acquired', $shapes->id => 'developing'],
            'class_teacher_remark' => 'Loves counting the chairs.',
            'days_present' => 50, 'days_absent' => 2,
            'then' => 'stay',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, NurseryAssessment::count());

        $report = TermResult::where('student_enrollment_id', $enrollment->id)->where('term_id', $this->term->id)->firstOrFail();
        $this->assertNull($report->term_average);
        $this->assertNull($report->class_rank);
        $this->assertSame('Loves counting the chairs.', $report->class_teacher_remark);
        $this->assertSame(52, $report->total_school_days);
        $this->assertFalse($report->is_published);
    }

    public function test_saving_again_changes_the_rating_rather_than_adding_one(): void
    {
        [$counts] = $this->smallCatalogue();
        $enrollment = $this->pupil('Ada');

        $this->post(route('admin.nursery.update', [$enrollment, $this->term]), ['ratings' => [$counts->id => 'not_yet']]);
        $this->post(route('admin.nursery.update', [$enrollment, $this->term]), ['ratings' => [$counts->id => 'acquired']]);

        $this->assertSame(1, NurseryAssessment::count());
        $this->assertSame('acquired', NurseryAssessment::first()->rating);
        $this->assertSame(1, TermResult::count());
    }

    public function test_save_and_next_walks_the_register_by_surname(): void
    {
        [$counts] = $this->smallCatalogue();
        $bih = $this->pupil('Ada', 'Bih');
        $ngwa = $this->pupil('Ada', 'Ngwa');

        $this->post(route('admin.nursery.update', [$bih, $this->term]), ['ratings' => [$counts->id => 'acquired'], 'then' => 'next'])
            ->assertRedirect(route('admin.nursery.edit', [$ngwa, $this->term]));

        $this->post(route('admin.nursery.update', [$ngwa, $this->term]), ['ratings' => [$counts->id => 'acquired'], 'then' => 'next'])
            ->assertRedirect(route('admin.nursery.index', ['class_section_id' => $this->section->id, 'term_id' => $this->term->id]));
    }

    public function test_the_class_list_shows_how_far_each_sheet_has_got(): void
    {
        [$counts, $shapes] = $this->smallCatalogue();
        $done = $this->pupil('Ada', 'Bih');
        $this->pupil('Ada', 'Ngwa');
        app(NurseryAssessmentService::class)->save($done, $this->term, [$counts->id => 'acquired', $shapes->id => 'acquired'], [], null);

        $this->get(route('admin.nursery.index', ['class_section_id' => $this->section->id, 'term_id' => $this->term->id]))
            ->assertSuccessful()
            ->assertSee('2/2')
            ->assertSee('0/2')
            ->assertSee(__('Review'))
            ->assertSee(__('Assess'))
            ->assertSee(__('Publishing opens once every sheet is complete.'));
    }

    // ------------------------------------------------------------ the report

    public function test_the_report_is_a_competence_grid_with_no_rank_and_no_pass_mark(): void
    {
        [$counts, $shapes] = $this->smallCatalogue();
        $enrollment = $this->pupil('Ada');
        $service = app(NurseryAssessmentService::class);
        $service->save($enrollment, $this->term, [$counts->id => 'developing', $shapes->id => 'acquired'], ['class_teacher_remark' => 'A joyful child.'], null);

        $report = TermResult::firstOrFail();

        $this->get(route('admin.report-cards.show', $report))
            ->assertSuccessful()
            ->assertSee(__('Nursery Progress Report'))
            ->assertSee('Counts objects up to ten')
            ->assertSee(__('In progress'))
            ->assertSee('A joyful child.')
            ->assertDontSee(__('Class Rank'))
            ->assertDontSee(__('Term Average'))
            ->assertDontSee('PASS');
    }

    public function test_the_report_says_where_a_child_moved_since_last_term(): void
    {
        [$counts] = $this->smallCatalogue();
        $enrollment = $this->pupil('Ada');
        $service = app(NurseryAssessmentService::class);
        $service->save($enrollment, $this->term, [$counts->id => 'not_yet'], [], null);
        $service->save($enrollment, $this->term2, [$counts->id => 'acquired'], [], null);

        $second = TermResult::where('term_id', $this->term2->id)->firstOrFail();

        $this->get(route('admin.report-cards.show', $second))
            ->assertSuccessful()
            ->assertSee(__('was :label', ['label' => __('Not yet')]));
    }

    public function test_a_parent_sees_the_published_report_and_not_a_draft(): void
    {
        [$counts] = $this->smallCatalogue();
        $enrollment = $this->pupil('Ada');
        app(NurseryAssessmentService::class)->save($enrollment, $this->term, [$counts->id => 'acquired'], [], null);
        $report = TermResult::firstOrFail();

        $guardian = Guardian::create([
            'branch_id' => $this->branch->id, 'portal_access' => true,
            'emergency_contact_name' => 'Mami Ngwa', 'emergency_contact_phone' => '677000111',
        ]);
        $guardian->students()->attach($enrollment->student_id, ['relationship' => 'Mother', 'is_primary' => true, 'can_access_portal' => true]);

        $this->actingAs($guardian, 'guardians')
            ->get(route('parent.report-cards.show', [$enrollment->student_id, $report]))
            ->assertForbidden();

        $report->update(['is_published' => true]);

        $this->actingAs($guardian, 'guardians')
            ->get(route('parent.report-cards.index', $enrollment->student_id))
            ->assertSuccessful()
            ->assertSee(__('Competences'))
            ->assertDontSee(__('Average'));

        $this->actingAs($guardian, 'guardians')
            ->get(route('parent.report-cards.show', [$enrollment->student_id, $report]))
            ->assertSuccessful()
            ->assertSee(__('Nursery Progress Report'))
            ->assertSee('Counts objects up to ten');
    }

    // ------------------------------------------------------------ the rest of the system

    public function test_marks_entry_leaves_nursery_out_and_says_why(): void
    {
        $this->get(route('admin.marks.index'))
            ->assertSuccessful()
            ->assertSee(__('nursery children are assessed by competence, not marked'))
            ->assertDontSee('Nursery 1');
    }

    public function test_the_report_card_list_offers_assessment_instead_of_recalculation(): void
    {
        $this->get(route('admin.report-cards.index', [
            'academic_session_id' => $this->session->id, 'form_id' => $this->nursery->id,
            'class_section_id' => $this->section->id, 'term_id' => $this->term->id,
        ]))
            ->assertSuccessful()
            ->assertSee(__('Assess this class'))
            ->assertDontSee(__('Generate / Recalculate'));
    }

    public function test_a_primary_pupil_is_not_sent_to_the_nursery_sheet(): void
    {
        $primary = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Primary 1', 'short_name' => 'P1',
            'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english',
            'has_streams' => false, 'display_order' => 3, 'is_active' => true,
        ]);
        $this->section->update(['form_id' => $primary->id]);
        $enrollment = $this->pupil('Ada');

        $this->get(route('admin.nursery.edit', [$enrollment, $this->term]))->assertNotFound();
    }

    public function test_a_secondary_only_school_has_none_of_this(): void
    {
        SchoolSetting::current()->update(['school_level_mode' => 'secondary']);

        $this->get(route('admin.nursery.index'))->assertNotFound();
        $this->get(route('admin.nursery.competences.index'))->assertNotFound();
    }
}
