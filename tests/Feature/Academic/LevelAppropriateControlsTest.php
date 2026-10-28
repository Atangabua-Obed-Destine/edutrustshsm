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
use App\Models\Subject;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A control with one possible answer is noise, and on a report card it is
 * worse than noise — it is a column of meaningless numbers handed to a parent.
 *
 * At primary every coefficient is 1 and every subject is compulsory, so the
 * coefficient columns and the elective selector say nothing. The coefficient
 * rule reads what is actually being printed rather than the school level, so
 * a secondary class that chooses not to weight gets the same clean card.
 */
class LevelAppropriateControlsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Term $term;
    private AcademicSession $session;
    private Batch $batch;
    private Subject $maths;
    private Subject $english;

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

        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true]);
    }

    private function form(string $name, string $schoolLevel, string $level): Form
    {
        return Form::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'short_name' => substr($name, 0, 4),
            'level' => $level, 'school_level' => $schoolLevel, 'education_system' => 'english',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
    }

    /** @param array<string, float> $coefficients subject name => coefficient */
    private function reportCard(Form $form, array $coefficients): TermResult
    {
        $section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => $form->name.'A', 'max_students' => 40, 'is_active' => true,
        ]);

        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => 'Ada', 'last_name' => 'Pupil',
            'date_of_birth' => '2014-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        $enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $section->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);

        $result = TermResult::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'term_id' => $this->term->id,
            'total_weighted_score' => 26, 'total_coefficient' => array_sum($coefficients),
            'term_average' => 13, 'class_rank' => 1, 'total_students' => 1,
            'is_published' => true,
        ]);

        foreach ($coefficients as $name => $coefficient) {
            SubjectTermResult::create([
                'branch_id' => $this->branch->id,
                'term_result_id' => $result->id,
                'subject_id' => $name === 'Mathematics' ? $this->maths->id : $this->english->id,
                'coefficient' => $coefficient,
                'term_average' => 13,
                'weighted_score' => 13 * $coefficient,
            ]);
        }

        return $result;
    }

    // ------------------------------------------------------------ the card

    public function test_a_primary_card_drops_the_coefficient_columns(): void
    {
        $primary = $this->form('Primary 3', 'nursery_primary', 'primary');
        $result = $this->reportCard($primary, ['Mathematics' => 1, 'English' => 1]);

        $response = $this->get(route('admin.report-cards.show', $result))->assertSuccessful();

        $response->assertDontSee(__('Coeff'))
            ->assertDontSee(__('Total Coefficient'))
            ->assertDontSee(__('Total Weighted Score'));

        // Replaced by something a parent can use.
        $response->assertSee(__('Subjects'));
    }

    public function test_rank_stays_at_primary(): void
    {
        $primary = $this->form('Primary 3', 'nursery_primary', 'primary');
        $result = $this->reportCard($primary, ['Mathematics' => 1, 'English' => 1]);

        $this->get(route('admin.report-cards.show', $result))
            ->assertSuccessful()
            ->assertSee(__('Class Rank'));
    }

    public function test_a_weighted_secondary_card_keeps_them(): void
    {
        $secondary = $this->form('Form 1', 'secondary', 'first_cycle');
        $result = $this->reportCard($secondary, ['Mathematics' => 3, 'English' => 2]);

        $this->get(route('admin.report-cards.show', $result))
            ->assertSuccessful()
            ->assertSee(__('Coeff'))
            ->assertSee(__('Total Coefficient'));
    }

    public function test_the_rule_follows_the_marks_not_the_level(): void
    {
        // A secondary class that chose not to weight gets the clean card too,
        // which is why this is read from the subjects being printed.
        $secondary = $this->form('Form 1', 'secondary', 'first_cycle');
        $result = $this->reportCard($secondary, ['Mathematics' => 1, 'English' => 1]);

        $this->get(route('admin.report-cards.show', $result))
            ->assertSuccessful()
            ->assertDontSee(__('Coeff'));
    }

    // ------------------------------------------------------------ curriculum

    public function test_a_primary_curriculum_offers_no_elective(): void
    {
        $primary = $this->form('Primary 3', 'nursery_primary', 'primary');

        $response = $this->get(route('admin.subject-enrollments.configure', $primary))->assertSuccessful();

        $response->assertSee(__('Core'))
            ->assertDontSee(__('Select All Elective'))
            ->assertDontSee(__('Elective:'));
    }

    public function test_a_secondary_curriculum_still_does(): void
    {
        $secondary = $this->form('Form 1', 'secondary', 'first_cycle');

        $this->get(route('admin.subject-enrollments.configure', $secondary))
            ->assertSuccessful()
            ->assertSee(__('Select All Elective'));
    }

    public function test_the_elective_counter_is_written_defensively(): void
    {
        $primary = $this->form('Primary 3', 'nursery_primary', 'primary');

        // The script writes the counter only if the element is on the page;
        // without that guard it throws and the rest of the page stops working.
        $this->get(route('admin.subject-enrollments.configure', $primary))
            ->assertSuccessful()
            ->assertSee("write('stat-elective'", false);
    }

    // ------------------------------------------------------------ the data

    public function test_primary_classes_really_do_not_weight(): void
    {
        $primary = $this->form('Primary 3', 'nursery_primary', 'primary');

        DB::table('form_subject')->insert([
            'form_id' => $primary->id, 'subject_id' => $this->maths->id,
            'coefficient' => 1, 'type' => 'core',
        ]);

        $distinct = DB::table('form_subject')
            ->join('forms', 'forms.id', '=', 'form_subject.form_id')
            ->where('forms.school_level', 'nursery_primary')
            ->distinct()->pluck('coefficient');

        // Compared as numbers: SQLite returns 1 where MySQL returns 1.0, and
        // the point is the value, not how the engine prints it.
        $this->assertSame([1.0], $distinct->map(fn ($c) => (float) $c)->all());
    }
}
