<?php

namespace Tests\Feature\Authorization;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\FeeCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trail was written by ~59 hand-placed calls in finance controllers only —
 * marks, grades, enrolments and fee collection had none — and there was no
 * controller, route or view to read any of it.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
    }

    public function test_creating_a_record_is_audited_automatically(): void
    {
        $this->actingAs($this->admin);

        $category = FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);

        $log = AuditLog::where('model_type', FeeCategory::class)
            ->where('model_id', $category->id)->where('action', 'created')->first();

        $this->assertNotNull($log, 'creating a model must write an audit entry');
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Tuition', $log->new_values['name']);
    }

    public function test_updating_records_only_what_changed(): void
    {
        $this->actingAs($this->admin);
        $category = FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);

        $category->update(['name' => 'Tuition Fees']);

        $log = AuditLog::where('model_id', $category->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame('Tuition', $log->old_values['name']);
        $this->assertSame('Tuition Fees', $log->new_values['name']);
        $this->assertArrayNotHasKey('code', $log->new_values, 'unchanged fields must not be recorded');
    }

    public function test_deleting_a_record_is_audited(): void
    {
        $this->actingAs($this->admin);
        $category = FeeCategory::create(['name' => 'Sports', 'code' => 'SPO', 'is_active' => true]);
        $id = $category->id;

        $category->delete();

        $this->assertTrue(
            AuditLog::where('model_id', $id)->where('action', 'deleted')->exists()
        );
    }

    public function test_secrets_are_never_written_to_the_trail(): void
    {
        $this->actingAs($this->admin);

        $user = User::create([
            'first_name' => 'New', 'last_name' => 'Person',
            'email' => 'new@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);

        $log = AuditLog::where('model_type', User::class)
            ->where('model_id', $user->id)->firstOrFail();

        $this->assertArrayNotHasKey('password', $log->new_values);
        $this->assertArrayNotHasKey('remember_token', $log->new_values);
    }

    public function test_logging_in_does_not_flood_the_trail(): void
    {
        User::create([
            'first_name' => 'Log', 'last_name' => 'In',
            'email' => 'login@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);

        $before = AuditLog::withoutGlobalScopes()->count();
        $this->post('/login', ['email' => 'login@example.test', 'password' => 'password']);

        // last_login_at is excluded, so a sign-in writes nothing.
        $this->assertSame($before, AuditLog::withoutGlobalScopes()->count());
    }

    public function test_the_trail_can_be_read(): void
    {
        $this->actingAs($this->admin);
        FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);

        // The list shows who/when/what-record, not field values.
        $this->get(route('admin.audit-log.index'))
            ->assertSuccessful()
            ->assertSee('FeeCategory', false)
            ->assertSee('Created', false);
    }

    public function test_an_entry_shows_its_field_level_changes(): void
    {
        $this->actingAs($this->admin);
        $category = FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);
        $category->update(['name' => 'Tuition Fees']);

        $log = AuditLog::where('model_id', $category->id)->where('action', 'updated')->firstOrFail();

        $this->get(route('admin.audit-log.show', $log))
            ->assertSuccessful()
            ->assertSee('Tuition Fees', false);
    }

    public function test_the_trail_can_be_exported(): void
    {
        $this->actingAs($this->admin);
        FeeCategory::create(['name' => 'Tuition', 'code' => 'TUI', 'is_active' => true]);

        $this->get(route('admin.audit-log.export'))
            ->assertSuccessful()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_system_entries_written_without_a_branch_stay_visible(): void
    {
        // Regression: AuditLog originally used the BelongsToBranch global scope,
        // which filters `branch_id = <current>`. Anything written outside an HTTP
        // auth context (console, seeders, pre-login) carries a NULL branch and
        // silently disappeared the moment anyone signed in.
        AuditLog::create([
            'branch_id' => null,
            'user_id' => null,
            'action' => 'created',
            'model_type' => \App\Models\FeeCategory::class,
            'model_id' => 999,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin);

        $this->assertTrue(
            AuditLog::visible()->where('model_id', 999)->exists(),
            'system-level entries must remain readable'
        );

        $this->get(route('admin.audit-log.index'))->assertSuccessful()->assertSee('System', false);
    }

    public function test_reading_the_trail_requires_permission(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $accountant = User::create([
            'first_name' => 'Ann', 'last_name' => 'Accountant',
            'email' => 'ann@example.test', 'password' => 'password',
            'role' => 'accountant', 'is_active' => true,
        ]);
        $accountant->roles()->sync([Role::where('name', 'accountant')->firstOrFail()->id]);

        $this->actingAs($accountant)->get(route('admin.audit-log.index'))->assertForbidden();
    }
}
