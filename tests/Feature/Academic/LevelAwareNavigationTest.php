<?php

namespace Tests\Feature\Academic;

use App\Models\Branch;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Support\LevelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Features that describe one kind of school are kept to that kind of school.
 *
 * GCE is an Ordinary / Advanced Level entry, streams are Science and Arts,
 * and adding or dropping subjects is about electives. None of the three means
 * anything in a nursery or primary school, yet all three sat in the menu of
 * one — because the navigation read no level at all.
 *
 * Hiding a link is not a rule, so the routes are gated too: an address typed
 * in, or a bookmark from before the school's mode changed, has to be refused
 * as well.
 */
class LevelAwareNavigationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;

    /** Routes that only make sense where there is a secondary school. */
    private const SECONDARY_ONLY = [
        'admin.gce.sessions.index',
        'admin.gce.subjects.index',
        'admin.streams.index',
        'admin.subject-add-drop.index',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);
    }

    private function mode(string $mode): void
    {
        SchoolSetting::updateOrCreate(
            ['branch_id' => $this->branch->id],
            ['school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => $mode]
        );
    }

    // ------------------------------------------------------------ the rule

    public function test_a_school_knows_which_levels_it_runs(): void
    {
        $this->mode('nursery_primary');
        $this->assertTrue(LevelContext::runsNurseryPrimary());
        $this->assertFalse(LevelContext::runsSecondary());

        $this->mode('secondary');
        $this->assertTrue(LevelContext::runsSecondary());
        $this->assertFalse(LevelContext::runsNurseryPrimary());

        $this->mode('both');
        $this->assertTrue(LevelContext::runsSecondary());
        $this->assertTrue(LevelContext::runsNurseryPrimary());
    }

    public function test_running_a_level_is_not_the_same_as_working_in_one(): void
    {
        $this->mode('both');
        LevelContext::set('nursery_primary');

        // Standing in primary does not stop the school being a secondary one.
        $this->assertSame('nursery_primary', LevelContext::current());
        $this->assertTrue(LevelContext::runsSecondary());
    }

    // ------------------------------------------------------------ the routes

    public function test_a_primary_only_school_cannot_reach_secondary_features(): void
    {
        $this->mode('nursery_primary');

        foreach (self::SECONDARY_ONLY as $route) {
            $this->get(route($route))->assertNotFound($route.' should be refused');
        }
    }

    public function test_a_secondary_school_reaches_them_normally(): void
    {
        $this->mode('secondary');

        foreach (self::SECONDARY_ONLY as $route) {
            $this->get(route($route))->assertSuccessful($route.' should be reachable');
        }
    }

    public function test_a_school_running_both_reaches_them_even_while_working_in_primary(): void
    {
        $this->mode('both');
        LevelContext::set('nursery_primary');

        // The school teaches secondary; the admin is simply looking elsewhere.
        $this->get(route('admin.gce.sessions.index'))->assertSuccessful();
        $this->get(route('admin.streams.index'))->assertSuccessful();
    }

    // ------------------------------------------------------------ the menu

    public function test_the_menu_drops_what_the_school_does_not_run(): void
    {
        $this->mode('nursery_primary');

        $response = $this->get(route('admin.dashboard'))->assertSuccessful();

        $response->assertDontSee(route('admin.gce.sessions.index'), false);
        $response->assertDontSee(route('admin.streams.index'), false);
        $response->assertDontSee(route('admin.subject-add-drop.index'), false);
    }

    public function test_the_menu_keeps_them_for_a_secondary_school(): void
    {
        $this->mode('secondary');

        $response = $this->get(route('admin.dashboard'))->assertSuccessful();

        $response->assertSee(route('admin.gce.sessions.index'), false);
        $response->assertSee(route('admin.streams.index'), false);
    }

    // ------------------------------------------------------------ the header

    public function test_a_single_level_school_is_still_told_which_level_it_is_in(): void
    {
        $this->mode('nursery_primary');

        // Nothing to switch, but every list on every screen is filtered by it.
        $this->assertFalse(LevelContext::showSwitcher());

        $this->get(route('admin.dashboard'))
            ->assertSuccessful()
            ->assertSee(__('Nursery / Primary'));
    }

    public function test_a_school_running_both_gets_the_switcher_instead(): void
    {
        $this->mode('both');

        $this->assertTrue(LevelContext::showSwitcher());

        $this->get(route('admin.dashboard'))
            ->assertSuccessful()
            ->assertSee('level-context-form', false);
    }
}
