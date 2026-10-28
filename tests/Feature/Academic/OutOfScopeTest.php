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
use App\Services\OutOfScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A school that stops running a level still has that level's last pupils.
 *
 * Every list is filtered to the school's level mode, so those pupils vanish
 * from every screen and the school reads as empty. They are not an error —
 * they are a cohort waiting to be closed out — so they are counted, named,
 * and given the ordinary exit flow a class at a time.
 */
class OutOfScopeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $session;
    private Term $term;
    private Batch $batch;
    private Form $form5;
    private Form $upperSixth;
    private Form $primary3;
    private ClassSection $form5A;

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
        $this->batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true,
        ]);

        $this->form5 = $this->form('Form 5', 'secondary', 'first_cycle', 5);
        $this->upperSixth = $this->form('Upper Sixth', 'secondary', 'second_cycle', 7);
        $this->primary3 = $this->form('Primary 3', 'nursery_primary', 'primary', 3);

        $this->form5A = $this->section($this->form5, 'A');

        $this->pupil('Bih', $this->form5A);
        $this->pupil('Che', $this->form5A);
        $this->pupil('Ngwa', $this->section($this->primary3, 'A'));
    }

    private function form(string $name, string $schoolLevel, string $level, int $order): Form
    {
        return Form::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'short_name' => substr($name, 0, 4),
            'level' => $level, 'school_level' => $schoolLevel, 'education_system' => 'english',
            'has_streams' => false, 'display_order' => $order, 'is_active' => true,
        ]);
    }

    private function section(Form $form, string $letter): ClassSection
    {
        return ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => $letter, 'name' => $form->name.$letter, 'max_students' => 40, 'is_active' => true,
        ]);
    }

    private function pupil(string $name, ClassSection $class): Student
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'first_name' => $name, 'last_name' => 'Pupil',
            'date_of_birth' => '2010-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $class->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);

        return $student;
    }

    private function hidden(): OutOfScopeService
    {
        return tap(app(OutOfScopeService::class))->forget();
    }

    // ------------------------------------------------------------ the count

    public function test_it_counts_what_the_level_mode_hides(): void
    {
        $summary = $this->hidden()->summary();

        $this->assertSame(['secondary'], $summary['levels']);
        $this->assertSame(2, $summary['classes']);
        $this->assertSame(2, $summary['pupils']);
    }

    public function test_a_school_running_both_levels_hides_nothing(): void
    {
        SchoolSetting::query()->update(['school_level_mode' => 'both']);

        $this->assertFalse($this->hidden()->hasAny());
        $this->get(route('admin.students.out-of-scope'))->assertNotFound();
    }

    public function test_a_pupil_who_already_left_is_not_counted(): void
    {
        $this->post(route('admin.students.exit.store', Student::where('first_name', 'Bih')->firstOrFail()), [
            'type' => 'withdrawn', 'left_on' => now()->toDateString(),
        ]);

        $this->assertSame(1, $this->hidden()->summary()['pupils']);
    }

    // ------------------------------------------------------------ the screens

    public function test_the_student_list_says_why_it_is_empty(): void
    {
        $this->get(route('admin.students.index'))
            ->assertSuccessful()
            ->assertDontSee('Bih')
            ->assertSee(__('Review them'))
            ->assertSee(route('admin.students.out-of-scope'), false);
    }

    public function test_the_dashboard_and_class_list_carry_the_same_notice(): void
    {
        $this->get(route('admin.dashboard'))->assertSuccessful()->assertSee(__('Review them'));
        $this->get(route('admin.forms.index'))->assertSuccessful()->assertSee(__('Review them'));
    }

    public function test_the_notice_goes_quiet_when_nothing_is_hidden(): void
    {
        SchoolSetting::query()->update(['school_level_mode' => 'both']);

        $this->get(route('admin.students.index'))
            ->assertSuccessful()
            ->assertDontSee(__('Review them'));
    }

    public function test_the_review_screen_lists_the_hidden_classes_with_their_pupils(): void
    {
        $response = $this->get(route('admin.students.out-of-scope'))->assertSuccessful();

        $response->assertSeeInOrder(['Form 5', 'Bih', 'Che'])
            ->assertSee('Upper Sixth')
            ->assertSee(__('Nobody is enrolled here this year. It can be retired.'))
            ->assertDontSee('Ngwa')
            ->assertDontSee('Primary 3');
    }

    // ------------------------------------------------------------ closing out

    public function test_a_whole_class_can_graduate_at_once(): void
    {
        $ids = Student::whereIn('first_name', ['Bih', 'Che'])->pluck('id')->all();

        $this->post(route('admin.students.out-of-scope.exit'), [
            'students' => $ids, 'type' => 'graduated',
            'left_on' => '2026-06-30', 'reason' => 'School now runs primary only.',
        ])->assertRedirect(route('admin.students.out-of-scope'));

        foreach ($ids as $id) {
            $student = Student::findOrFail($id);
            $this->assertSame('graduated', $student->status);
            $this->assertSame('2026-06-30', $student->withdrawal_date->toDateString());
            $this->assertSame($this->admin->id, $student->exit_recorded_by);
            $this->assertNull($student->currentEnrollment);
        }

        $this->assertSame(0, $this->hidden()->summary()['pupils']);
        $this->get(route('admin.students.archive'))->assertSee('Bih')->assertSee('Che');
    }

    public function test_a_transfer_needs_a_destination(): void
    {
        $this->from(route('admin.students.out-of-scope'))
            ->post(route('admin.students.out-of-scope.exit'), [
                'students' => Student::where('first_name', 'Bih')->pluck('id')->all(),
                'type' => 'transferred', 'left_on' => now()->toDateString(),
            ])->assertSessionHasErrors('destination_school');

        $this->assertSame('active', Student::where('first_name', 'Bih')->firstOrFail()->status);
    }

    public function test_only_hidden_pupils_can_be_closed_out_this_way(): void
    {
        $ngwa = Student::where('first_name', 'Ngwa')->firstOrFail();

        $this->post(route('admin.students.out-of-scope.exit'), [
            'students' => [$ngwa->id], 'type' => 'graduated', 'left_on' => now()->toDateString(),
        ]);

        // A primary pupil is not in the hidden set, so the bulk route ignores them.
        $this->assertSame('active', $ngwa->fresh()->status);
    }

    public function test_an_empty_class_can_be_retired_but_an_occupied_one_cannot(): void
    {
        $this->post(route('admin.students.out-of-scope.retire'))
            ->assertRedirect(route('admin.students.out-of-scope'));

        $this->assertFalse($this->upperSixth->fresh()->is_active);
        $this->assertTrue($this->form5->fresh()->is_active, 'Form 5 still has pupils');
        $this->assertTrue($this->primary3->fresh()->is_active, 'Primary 3 is a level the school runs');
    }

    public function test_closing_out_the_last_pupils_lets_the_class_go_too(): void
    {
        $this->post(route('admin.students.out-of-scope.exit'), [
            'students' => Student::whereIn('first_name', ['Bih', 'Che'])->pluck('id')->all(),
            'type' => 'graduated', 'left_on' => now()->toDateString(),
        ]);
        $this->post(route('admin.students.out-of-scope.retire'));

        $this->assertFalse($this->hidden()->hasAny());
        $this->get(route('admin.students.index'))->assertDontSee(__('Review them'));
    }
}
