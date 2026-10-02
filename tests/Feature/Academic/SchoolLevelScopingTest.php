<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\User;
use App\Support\LevelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The school level in context has to decide which sub-levels a screen offers.
 *
 * Several screens hardcoded the secondary pair instead. On the student form
 * that was not just a wrong label: its class dropdown matches form.level
 * against the chosen cycle, and a nursery form's level is "nursery", so in
 * nursery/primary mode no class could ever appear and no pupil could be
 * registered.
 */
class SchoolLevelScopingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both',
        ]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);

        $this->form('Form 1', 'secondary', 'first_cycle');
        $this->form('Lower Sixth', 'secondary', 'second_cycle');
        $this->form('Nursery 1', 'nursery_primary', 'nursery');
        $this->form('Class 1', 'nursery_primary', 'primary');
    }

    private function form(string $name, string $schoolLevel, string $level): Form
    {
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'short_name' => substr($name, 0, 4),
            'level' => $level, 'school_level' => $schoolLevel, 'education_system' => 'english',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);

        ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => $name.'A', 'max_students' => 40, 'is_active' => true,
        ]);

        return $form;
    }

    // ------------------------------------------------------------ the taxonomy

    public function test_each_school_level_offers_its_own_sub_levels(): void
    {
        $this->assertSame(['nursery', 'primary'], array_keys(Form::subLevelsFor('nursery_primary')));
        $this->assertSame(['first_cycle', 'second_cycle'], array_keys(Form::subLevelsFor('secondary')));
        $this->assertSame(__('Nursery'), Form::levelLabel('nursery'));
        $this->assertSame(__('Second Cycle'), Form::levelLabel('second_cycle'));
    }

    // ------------------------------------------------------------ the student form

    public function test_the_student_form_offers_nursery_levels_in_nursery_mode(): void
    {
        LevelContext::set('nursery_primary');

        $response = $this->get(route('admin.students.create'))->assertSuccessful();

        $this->assertSame(['nursery', 'primary'], array_keys($response->viewData('levels')));

        // The class dropdown is filled by matching a form's level against the
        // chosen one, so the two must come from the same school level.
        $levels = $response->viewData('forms')->pluck('level')->unique()->values()->all();
        $this->assertSame(['nursery', 'primary'], $levels);

        $response->assertSee('value="nursery"', false)->assertDontSee('value="first_cycle"', false);
    }

    public function test_the_student_form_still_offers_cycles_in_secondary_mode(): void
    {
        LevelContext::set('secondary');

        $response = $this->get(route('admin.students.create'))->assertSuccessful();

        $this->assertSame(['first_cycle', 'second_cycle'], array_keys($response->viewData('levels')));
        $this->assertSame(['first_cycle', 'second_cycle'], $response->viewData('forms')->pluck('level')->unique()->values()->all());
        $response->assertSee('value="second_cycle"', false)->assertDontSee('value="nursery"', false);
    }

    // ------------------------------------------------------------ other screens

    public function test_form_lists_follow_the_level_in_context(): void
    {
        LevelContext::set('nursery_primary');

        foreach ([
            'admin.pta.index' => 'forms',
            'admin.documents.marksheet' => 'forms',
            'admin.accounting-reports.student-fee-aging' => 'forms',
        ] as $route => $key) {
            $forms = $this->get(route($route))->assertSuccessful()->viewData($key);

            $this->assertSame(
                ['nursery_primary'],
                collect($forms)->pluck('school_level')->unique()->values()->all(),
                $route.' leaked another school level'
            );
        }
    }

    public function test_gce_registration_always_offers_secondary_classes(): void
    {
        // GCE is a secondary qualification whichever level the admin is in.
        LevelContext::set('nursery_primary');

        $forms = $this->get(route('admin.gce.sessions.create'))->assertSuccessful()->viewData('forms');

        $this->assertSame(['secondary'], $forms->pluck('school_level')->unique()->values()->all());
    }

    public function test_the_public_application_form_offers_only_levels_the_school_runs(): void
    {
        SchoolSetting::current()->update(['school_level_mode' => 'secondary']);

        $this->assertSame(['secondary'], Form::forActiveSchoolLevels()->pluck('school_level')->unique()->values()->all());

        SchoolSetting::current()->update(['school_level_mode' => 'nursery_primary']);

        $this->assertSame(['nursery_primary'], Form::forActiveSchoolLevels()->pluck('school_level')->unique()->values()->all());
    }
}
