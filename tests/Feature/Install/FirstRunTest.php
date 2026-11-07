<?php

namespace Tests\Feature\Install;

use App\Models\Branch;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolInstaller;
use App\Support\EnvFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A technician clones the repository at a school, runs the installer, and
 * creates the super admin from its one-time link. Nothing about one school may
 * reach another, and no one on the school's network may get there first.
 */
class FirstRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    /** @return array<string, string> */
    private function details(array $overrides = []): array
    {
        return array_merge([
            'school_name' => 'Trial College Kumba',
            'school_short_name' => 'TCK',
            'school_code' => 'tck',
            'school_level_mode' => 'secondary',
            'first_name' => 'Ada',
            'last_name' => 'Principal',
            'email' => 'ada@trial.test',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ], $overrides);
    }

    public function test_the_starting_data_has_no_school_and_no_account(): void
    {
        $this->assertSame(0, User::count());
        $this->assertSame(0, SchoolSetting::withoutBranchScope()->count());
    }

    public function test_an_empty_install_sends_the_login_page_to_its_setup(): void
    {
        $this->get(route('login'))->assertRedirect(route('install.show'));
    }

    public function test_setup_stays_closed_without_the_installers_link(): void
    {
        app(SchoolInstaller::class)->issueToken();

        $this->get(route('install.show'))->assertOk()->assertSee('php artisan edutrust:install')->assertDontSee('name="email"', false);
        $this->get(route('install.show', 'wrong-token'))->assertOk()->assertDontSee('name="email"', false);
        $this->post(route('install.store', 'wrong-token'), $this->details())->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_the_link_opens_a_page_that_says_who_makes_edutrust(): void
    {
        $token = app(SchoolInstaller::class)->issueToken();

        $this->get(route('install.show', $token))
            ->assertOk()
            ->assertSee('EduTrust')
            ->assertSee('I-NNOVA')
            ->assertSee('name="email"', false);
    }

    public function test_the_school_and_its_super_admin_are_created_and_logged_in(): void
    {
        $token = app(SchoolInstaller::class)->issueToken();

        $this->post(route('install.store', $token), $this->details())
            ->assertRedirect(route('admin.dashboard'));

        $settings = SchoolSetting::withoutBranchScope()->sole();
        $this->assertSame('Trial College Kumba', $settings->school_name);
        $this->assertSame('TCK', $settings->school_code);
        $this->assertSame('TCK', $settings->student_id_prefix);
        $this->assertSame('secondary', $settings->school_level_mode);

        $branch = Branch::sole();
        $this->assertSame('Trial College Kumba', $branch->name);
        $this->assertSame($branch->id, $settings->branch_id);

        $admin = User::sole();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->roles()->where('name', 'super_admin')->exists());
        $this->assertTrue($admin->branches()->whereKey($branch->id)->exists());
        $this->assertTrue(Hash::check('a-long-password', $admin->password));
        $this->assertSame($admin->id, Auth::id());

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Trial College Kumba');
    }

    public function test_the_link_works_once(): void
    {
        $token = app(SchoolInstaller::class)->issueToken();
        $this->post(route('install.store', $token), $this->details());
        Auth::logout();

        $this->get(route('install.show', $token))->assertRedirect(route('login'));
        $this->post(route('install.store', $token), $this->details(['email' => 'late@trial.test']))
            ->assertRedirect(route('login'));

        $this->assertSame(1, User::count());
        $this->assertFalse(app(SchoolInstaller::class)->tokenIsValid($token));
    }

    public function test_bad_details_are_refused_and_create_nothing(): void
    {
        $token = app(SchoolInstaller::class)->issueToken();

        $this->post(route('install.store', $token), $this->details([
            'school_code' => 'not a code!',
            'password_confirmation' => 'something-else',
        ]))->assertSessionHasErrors(['school_code', 'password']);

        $this->assertSame(0, User::count());
        $this->assertTrue(app(SchoolInstaller::class)->tokenIsValid($token));
    }

    public function test_the_installer_will_not_touch_a_running_school(): void
    {
        $envBefore = File::exists(base_path('.env')) ? File::get(base_path('.env')) : null;

        app(SchoolInstaller::class)->finish($this->details(), $this->details());

        $this->artisan('edutrust:install')
            ->expectsOutputToContain('already installed')
            ->assertFailed();

        $this->assertSame($envBefore, File::exists(base_path('.env')) ? File::get(base_path('.env')) : null);
        $this->assertSame(1, User::count());
    }

    public function test_settings_are_written_without_disturbing_the_rest_of_the_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        File::put($path, "APP_NAME=Laravel\n# DB_HOST=127.0.0.1\nMAIL_MAILER=log\n");

        EnvFile::set($path, [
            'APP_NAME' => 'EduTrust',
            'DB_HOST' => '10.0.0.5',
            'DB_PASSWORD' => 'pa$$ w#rd',
        ]);

        $this->assertSame(
            "APP_NAME=EduTrust\nDB_HOST=10.0.0.5\nMAIL_MAILER=log\nDB_PASSWORD='pa$$ w#rd'\n",
            File::get($path)
        );

        File::delete($path);
    }
}
