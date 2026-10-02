<?php

namespace Tests\Feature\Admin;

use App\Models\Branch;
use App\Models\EdutrustPaySetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EdutrustPay\SettingsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The EdutrustPay connection settings screen.
 *
 * These credentials decide whose figures reach the body, and a wrong value here
 * produces no visible error at this end — from the body's console the school
 * simply goes quiet. So the tests lean on the ways that could happen silently:
 * a secret wiped by an unrelated edit, a permission that does not actually
 * restrict, a saved value that the reporting client then ignores.
 */
class EdutrustPaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'ST JEROME', 'is_active' => true]);

        foreach (['view', 'edit', 'test'] as $action) {
            Permission::firstOrCreate(
                ['name' => 'edutrustpay-reporting.'.$action],
                ['display_name' => ucfirst($action), 'group_name' => 'EdutrustPay Reporting']
            );
        }
    }

    // ---------------------------------------------------------------
    // Permissions
    // ---------------------------------------------------------------

    public function test_a_user_without_the_permission_cannot_open_the_screen(): void
    {
        $this->actingAs($this->userWith([]));

        $this->get(route('admin.edutrustpay.index'))->assertForbidden();
    }

    public function test_view_permission_alone_does_not_allow_saving(): void
    {
        $this->actingAs($this->userWith(['edutrustpay-reporting.view']));

        $this->get(route('admin.edutrustpay.index'))->assertOk();

        // Seeing the credentials and changing them are separate acts.
        $this->put(route('admin.edutrustpay.update', $this->branch), [
            'endpoint' => 'https://console.example',
        ])->assertForbidden();
    }

    public function test_view_permission_alone_does_not_allow_testing(): void
    {
        $this->actingAs($this->userWith(['edutrustpay-reporting.view']));

        $this->post(route('admin.edutrustpay.test', $this->branch))->assertForbidden();
    }

    // ---------------------------------------------------------------
    // Saving
    // ---------------------------------------------------------------

    public function test_settings_can_be_saved_and_the_secret_is_encrypted_at_rest(): void
    {
        $this->actingAs($this->editor());

        $this->put(route('admin.edutrustpay.update', $this->branch), [
            'enabled' => 1,
            'endpoint' => 'https://console.example',
            'institution_ref' => '11111111-2222-3333-4444-555555555555',
            'key_id' => 'etp_abcdef0123456789',
            'secret' => 'a-secret-long-enough-to-pass',
        ])->assertRedirect(route('admin.edutrustpay.index'));

        $setting = EdutrustPaySetting::where('branch_id', $this->branch->id)->firstOrFail();

        $this->assertSame('a-secret-long-enough-to-pass', $setting->secret());

        // Encrypted, not stored in the clear: verifying an HMAC needs the real
        // secret, so it must be recoverable — but not readable in the table.
        $raw = \DB::table('edutrustpay_settings')->where('branch_id', $this->branch->id)->value('secret_ciphertext');
        $this->assertNotSame('a-secret-long-enough-to-pass', $raw);
        $this->assertStringNotContainsString('a-secret-long-enough-to-pass', (string) $raw);
    }

    public function test_a_blank_secret_keeps_the_stored_one(): void
    {
        $this->actingAs($this->editor());
        $this->saveValidSettings();

        // The field is always blank on load, because a saved secret is never
        // displayed. If blank meant "clear", correcting a typo in the endpoint
        // would wipe a working credential and the school would go silent
        // without anyone touching the thing that mattered.
        $this->put(route('admin.edutrustpay.update', $this->branch), [
            'enabled' => 1,
            'endpoint' => 'https://console.example/corrected',
            'institution_ref' => '11111111-2222-3333-4444-555555555555',
            'key_id' => 'etp_abcdef0123456789',
            'secret' => '',
        ])->assertRedirect();

        $setting = EdutrustPaySetting::where('branch_id', $this->branch->id)->firstOrFail();

        $this->assertSame('a-secret-long-enough-to-pass', $setting->secret());
        $this->assertSame('https://console.example/corrected', $setting->endpoint);
    }

    public function test_replacing_the_secret_clears_the_previous_test_result(): void
    {
        $this->actingAs($this->editor());
        $this->saveValidSettings();

        EdutrustPaySetting::where('branch_id', $this->branch->id)->update([
            'last_tested_at' => now(),
            'last_test_ok' => true,
            'last_test_message' => 'Accepted.',
        ]);

        $this->put(route('admin.edutrustpay.update', $this->branch), [
            'enabled' => 1,
            'endpoint' => 'https://console.example',
            'institution_ref' => '11111111-2222-3333-4444-555555555555',
            'key_id' => 'etp_abcdef0123456789',
            'secret' => 'a-completely-different-secret',
        ])->assertRedirect();

        // A new secret invalidates whatever the last test proved.
        $setting = EdutrustPaySetting::where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertNull($setting->last_tested_at);
        $this->assertNull($setting->last_test_ok);
    }

    public function test_the_secret_is_never_rendered_on_the_page(): void
    {
        $this->actingAs($this->editor());
        $this->saveValidSettings();

        $this->get(route('admin.edutrustpay.index'))
            ->assertOk()
            ->assertDontSee('a-secret-long-enough-to-pass');
    }

    // ---------------------------------------------------------------
    // Resolution
    // ---------------------------------------------------------------

    public function test_saved_settings_are_what_the_reporting_client_uses(): void
    {
        $this->actingAs($this->editor());
        $this->saveValidSettings();

        $resolved = app(SettingsResolver::class)->forBranch($this->branch->id);

        $this->assertSame('database', $resolved['source']);
        $this->assertSame('etp_abcdef0123456789', $resolved['key_id']);
        $this->assertSame('a-secret-long-enough-to-pass', $resolved['secret']);
    }

    public function test_env_remains_the_fallback_when_nothing_is_saved(): void
    {
        // A deployment configured before this screen existed must keep working.
        config([
            'edutrustpay.enabled' => true,
            'edutrustpay.endpoint' => 'https://from-env.example',
            'edutrustpay.branches' => [
                $this->branch->id => ['ref' => 'ref-from-env', 'key_id' => 'key-from-env', 'secret' => 'secret-from-env'],
            ],
        ]);

        $resolved = app(SettingsResolver::class)->forBranch($this->branch->id);

        $this->assertSame('env', $resolved['source']);
        $this->assertSame('key-from-env', $resolved['key_id']);
    }

    public function test_a_branch_switched_off_is_not_treated_as_configured(): void
    {
        config(['edutrustpay.branches' => [], 'edutrustpay.enabled' => false]);

        $this->actingAs($this->editor());
        $this->saveValidSettings(['enabled' => 0]);

        // Configured and deliberately quiet is not the same as unconfigured,
        // but neither should be sent.
        $this->assertNotContains(
            $this->branch->id,
            app(SettingsResolver::class)->configuredBranchIds()
        );
    }

    // ---------------------------------------------------------------
    // Connection test
    // ---------------------------------------------------------------

    public function test_a_successful_connection_test_is_recorded(): void
    {
        Http::fake(['*/api/v1/heartbeat' => Http::response(['status' => 'ok'], 200)]);

        $this->actingAs($this->editor());
        $this->saveValidSettings();

        $this->post(route('admin.edutrustpay.test', $this->branch))->assertSessionHas('success');

        $setting = EdutrustPaySetting::where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertTrue($setting->last_test_ok);
        $this->assertNotNull($setting->last_tested_at);
    }

    public function test_a_rejected_credential_says_what_to_do_about_it(): void
    {
        Http::fake(['*/api/v1/heartbeat' => Http::response(['error' => 'invalid_signature'], 401)]);

        $this->actingAs($this->editor());
        $this->saveValidSettings();

        $this->post(route('admin.edutrustpay.test', $this->branch))->assertSessionHas('error');

        $setting = EdutrustPaySetting::where('branch_id', $this->branch->id)->firstOrFail();

        $this->assertFalse($setting->last_test_ok);
        // The most likely real failure: a key rotated on the platform and never
        // updated here. The message must name it.
        $this->assertStringContainsString('rotated', $setting->last_test_message);
    }

    public function test_an_unreachable_console_is_not_reported_as_a_credential_problem(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('offline'));

        $this->actingAs($this->editor());
        $this->saveValidSettings();

        $this->post(route('admin.edutrustpay.test', $this->branch));

        $setting = EdutrustPaySetting::where('branch_id', $this->branch->id)->firstOrFail();

        // Reports queue locally and retry, so an outage is not the same as a
        // wrong key and must not be described as one.
        $this->assertStringContainsString('queue', $setting->last_test_message);
    }

    public function test_testing_before_anything_is_configured_says_so(): void
    {
        // Clear the .env fallback explicitly: the test environment loads the
        // real .env, so a developer who has configured this machine would
        // otherwise see this test pass or fail depending on their own settings.
        config(['edutrustpay.branches' => [], 'edutrustpay.enabled' => false]);

        $this->actingAs($this->editor());

        $this->post(route('admin.edutrustpay.test', $this->branch))->assertSessionHas('error');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function saveValidSettings(array $overrides = []): void
    {
        $this->put(route('admin.edutrustpay.update', $this->branch), array_merge([
            'enabled' => 1,
            'endpoint' => 'https://console.example',
            'institution_ref' => '11111111-2222-3333-4444-555555555555',
            'key_id' => 'etp_abcdef0123456789',
            'secret' => 'a-secret-long-enough-to-pass',
        ], $overrides));
    }

    private function editor(): User
    {
        return $this->userWith([
            'edutrustpay-reporting.view',
            'edutrustpay-reporting.edit',
            'edutrustpay-reporting.test',
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User '.$seq,
            'email' => 'user'.$seq.'@example.test',
            'password' => 'password',
            // Not super_admin: that role short-circuits every permission check
            // in the Gate bridge, so a test using it would prove nothing.
            'role' => 'admin',
            'is_active' => true,
        ]);

        $user->branches()->attach($this->branch->id, ['is_default' => true]);

        $role = Role::create(['name' => 'test-role-'.$seq, 'display_name' => 'Test role '.$seq]);
        $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
