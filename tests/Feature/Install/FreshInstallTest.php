<?php

namespace Tests\Feature\Install;

use App\Models\Branch;
use App\Models\Form;
use App\Models\User;
use App\Services\SchoolInstaller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A new school installs, seeds, and logs in: what the installer made must be
 * there to use. It was saved with no branch, so every screen hid it.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_classes_and_subjects_belong_to_the_school(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $branch = Branch::sole();

        foreach (['forms', 'streams', 'subjects', 'departments', 'grade_scales', 'fee_categories'] as $table) {
            $this->assertSame(0, DB::table($table)->whereNull('branch_id')->count(), "$table rows without a branch");
        }

        $admin = $this->firstAccount();
        $this->assertTrue($admin->branches()->whereKey($branch->id)->exists());

        $form1 = Form::withoutGlobalScopes()->where('short_name', 'F1')->firstOrFail();
        $this->actingAs($admin)->get(route('admin.forms.edit', $form1))->assertOk();
        $this->get(route('admin.subjects.index'))->assertOk()->assertSee('Further Mathematics');
    }

    public function test_an_install_already_made_that_way_is_repaired(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->firstAccount();
        DB::table('forms')->update(['branch_id' => null]);
        DB::table('subjects')->update(['branch_id' => null]);
        DB::table('branch_user')->delete();

        $migration = require database_path('migrations/2026_11_06_090000_stamp_unbranched_rows_on_single_branch_installs.php');
        $migration->up();

        $this->assertSame(0, DB::table('forms')->whereNull('branch_id')->count());
        $this->assertSame(0, DB::table('subjects')->whereNull('branch_id')->count());
        $this->assertSame(1, DB::table('branch_user')->count());
    }

    private function firstAccount(): User
    {
        $school = ['school_name' => 'Trial College', 'school_code' => 'TC', 'school_level_mode' => 'both'];
        $admin = ['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.test', 'password' => 'a-long-password'];

        return app(SchoolInstaller::class)->finish($school, $admin);
    }
}
