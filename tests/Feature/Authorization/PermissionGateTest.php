<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Role/Permission tables were seeded and UI-complete but authorized nothing.
 * These guard the Gate bridge that finally connects them.
 */
class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'staff'): User
    {
        return User::create([
            'first_name' => 'Test', 'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test', 'password' => 'password',
            'role' => $role, 'is_active' => true,
        ]);
    }

    private function roleWith(string $roleName, array $permissionNames): Role
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['display_name' => ucfirst($roleName), 'is_system' => false]
        );

        $ids = collect($permissionNames)->map(fn ($n) => Permission::firstOrCreate(
            ['name' => $n],
            ['display_name' => $n, 'group_name' => 'Test']
        )->id);

        $role->permissions()->sync($ids);

        return $role;
    }

    public function test_a_granted_permission_is_allowed(): void
    {
        $user = $this->user();
        $user->roles()->sync([$this->roleWith('bursar', ['fee-collection.view'])->id]);

        $this->assertTrue($user->can('fee-collection.view'));
    }

    public function test_an_ungranted_permission_is_denied(): void
    {
        $user = $this->user();
        $user->roles()->sync([$this->roleWith('bursar', ['fee-collection.view'])->id]);

        $this->assertFalse($user->can('fee-collection.collect'));
    }

    public function test_an_unknown_ability_is_denied_not_allowed(): void
    {
        $user = $this->user();
        $user->roles()->sync([$this->roleWith('bursar', ['fee-collection.view'])->id]);

        $this->assertFalse($user->can('there.is.no.such.ability'));
    }

    public function test_super_admin_bypasses_every_check(): void
    {
        $this->assertTrue($this->user('super_admin')->can('anything.at.all'));
    }

    public function test_a_user_with_no_roles_has_nothing(): void
    {
        $this->assertFalse($this->user()->can('fee-collection.view'));
    }

    public function test_permissions_are_loaded_once_per_request(): void
    {
        $user = $this->user();
        $user->roles()->sync([$this->roleWith('bursar', ['a.view', 'b.view', 'c.view'])->id]);

        $user->permissionNames(); // warm

        DB::enableQueryLog();
        for ($i = 0; $i < 25; $i++) {
            $user->can('a.view');
            $user->can('zzz.nope');
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries, 'authorization checks must not hit the database per call');
    }

    public function test_role_users_uses_the_pivot_so_the_delete_guard_fires(): void
    {
        $user = $this->user();
        $role = $this->roleWith('bursar', ['fee-collection.view']);
        $user->roles()->sync([$role->id]);

        // Regression: Role::users() was a hasMany on a role_id column, so this
        // reported 0 and RoleController::destroy would delete a role in use.
        $this->assertTrue($role->users()->exists());
        $this->assertSame(1, $role->users()->count());
    }
}
