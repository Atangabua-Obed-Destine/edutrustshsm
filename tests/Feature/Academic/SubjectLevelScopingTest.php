<?php

namespace Tests\Feature\Academic;

use App\Models\Branch;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\User;
use App\Support\LevelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Subjects belong to a school level, or to both.
 *
 * They were one flat list, so a school working in Nursery / Primary was
 * offered Further Mathematics and a secondary school was offered Sounds and
 * Word Building. A subject with no level is taught everywhere — English and
 * Mathematics are, and splitting them in two would mean two sets of marks for
 * the same subject.
 */
class SubjectLevelScopingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both',
        ]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->subject('Further Mathematics', 'FMA', 'secondary');
        $this->subject('Sounds and Word Building', 'SWB', 'nursery_primary');
        $this->subject('English Language', 'ENG', null);
    }

    private function subject(string $name, string $code, ?string $level): Subject
    {
        return Subject::create([
            'branch_id' => $this->branch->id,
            'name' => $name, 'code' => $code,
            'school_level' => $level, 'is_active' => true,
        ]);
    }

    private function form(string $name, string $schoolLevel, string $level): Form
    {
        return Form::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'short_name' => substr($name, 0, 4),
            'level' => $level, 'school_level' => $schoolLevel, 'education_system' => 'english',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------ the scope

    public function test_a_level_sees_its_own_subjects_and_the_shared_ones(): void
    {
        LevelContext::set('nursery_primary');

        $this->assertSame(
            ['English Language', 'Sounds and Word Building'],
            Subject::forCurrentLevel()->orderBy('name')->pluck('name')->all()
        );

        LevelContext::set('secondary');

        $this->assertSame(
            ['English Language', 'Further Mathematics'],
            Subject::forCurrentLevel()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_a_subject_with_no_level_is_taught_everywhere(): void
    {
        $this->assertSame(['English Language'], Subject::taughtAtEveryLevel()->pluck('name')->all());
    }

    public function test_asking_for_no_level_narrows_nothing(): void
    {
        // A caller with no level in hand should get everything rather than
        // silently receiving an empty list.
        $this->assertSame(3, Subject::forLevel(null)->count());
    }

    // ------------------------------------------------------------ the screens

    public function test_the_subject_list_follows_the_level_in_context(): void
    {
        LevelContext::set('nursery_primary');

        $response = $this->get(route('admin.subjects.index'))->assertSuccessful();

        $this->assertSame(
            ['English Language', 'Sounds and Word Building'],
            $response->viewData('subjects')->pluck('name')->sort()->values()->all()
        );

        $response->assertDontSee('Further Mathematics');
    }

    public function test_the_curriculum_picker_follows_the_class_not_the_header(): void
    {
        $primary = $this->form('Primary 6', 'nursery_primary', 'primary');

        // Deliberately the other level in the header: the class being
        // configured is what governs.
        LevelContext::set('secondary');

        $response = $this->get(route('admin.subject-enrollments.configure', $primary))->assertSuccessful();

        $this->assertSame(
            ['English Language', 'Sounds and Word Building'],
            $response->viewData('subjects')->pluck('name')->sort()->values()->all()
        );
    }

    public function test_a_secondary_class_is_not_offered_primary_subjects(): void
    {
        $secondary = $this->form('Form 1', 'secondary', 'first_cycle');

        $response = $this->get(route('admin.subject-enrollments.configure', $secondary))->assertSuccessful();

        $this->assertSame(
            ['English Language', 'Further Mathematics'],
            $response->viewData('subjects')->pluck('name')->sort()->values()->all()
        );
    }

    public function test_the_timetable_offers_only_subjects_for_the_level(): void
    {
        LevelContext::set('nursery_primary');

        $response = $this->get(route('admin.timetable.class-schedule'))->assertSuccessful();

        $this->assertSame(
            ['English Language', 'Sounds and Word Building'],
            $response->viewData('subjects')->pluck('name')->sort()->values()->all()
        );
    }

    // ------------------------------------------------------------ editing

    public function test_a_subject_can_be_created_for_one_level(): void
    {
        $this->post(route('admin.subjects.store'), [
            'name' => 'Handwriting', 'code' => 'HWR',
            'school_level' => 'nursery_primary',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('nursery_primary', Subject::where('code', 'HWR')->value('school_level'));
    }

    public function test_a_subject_can_be_moved_to_both_levels(): void
    {
        $subject = Subject::where('code', 'FMA')->firstOrFail();

        $this->put(route('admin.subjects.update', $subject), [
            'name' => $subject->name, 'code' => $subject->code,
            'school_level' => '',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull($subject->fresh()->school_level);
    }

    public function test_an_unknown_level_is_refused(): void
    {
        $this->post(route('admin.subjects.store'), [
            'name' => 'Nonsense', 'code' => 'NON',
            'school_level' => 'university',
        ])->assertSessionHasErrors('school_level');
    }

    // ------------------------------------------------------------ the backfill

    public function test_the_migration_reads_a_subjects_level_off_the_curriculum(): void
    {
        // Mirrors what the migration did: a subject mapped only to secondary
        // classes is a secondary subject; one taught at both stays shared.
        $secondaryClass = $this->form('Form 1', 'secondary', 'first_cycle');
        $primaryClass = $this->form('Primary 6', 'nursery_primary', 'primary');

        $only = $this->subject('Physics', 'PHY', null);
        $shared = $this->subject('Music', 'MUS', null);

        DB::table('form_subject')->insert([
            ['form_id' => $secondaryClass->id, 'subject_id' => $only->id, 'coefficient' => 1, 'type' => 'core'],
            ['form_id' => $secondaryClass->id, 'subject_id' => $shared->id, 'coefficient' => 1, 'type' => 'core'],
            ['form_id' => $primaryClass->id, 'subject_id' => $shared->id, 'coefficient' => 1, 'type' => 'core'],
        ]);

        $inferred = DB::table('form_subject')
            ->join('forms', 'forms.id', '=', 'form_subject.form_id')
            ->select('form_subject.subject_id', 'forms.school_level')
            ->distinct()->get()->groupBy('subject_id')
            ->map(fn ($rows) => $rows->pluck('school_level')->unique())
            ->filter(fn ($levels) => $levels->count() === 1)
            ->map(fn ($levels) => $levels->first());

        $this->assertSame('secondary', $inferred[$only->id]);
        $this->assertArrayNotHasKey($shared->id, $inferred->all());
    }
}
