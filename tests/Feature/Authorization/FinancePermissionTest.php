<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-action permission gating on the finance controllers — the first module
 * converted to the AuthorizesModule convention.
 */
class FinancePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissionNames, string $role = 'accountant'): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'User',
            'email' => uniqid().'@example.test', 'password' => 'password',
            'role' => $role, 'is_active' => true,
        ]);

        $roleModel = Role::firstOrCreate(
            ['name' => 'test-role-'.uniqid()],
            ['display_name' => 'Test Role', 'is_system' => false]
        );

        $roleModel->permissions()->sync(
            collect($permissionNames)->map(fn ($n) => Permission::firstOrCreate(
                ['name' => $n], ['display_name' => $n, 'group_name' => 'Test']
            )->id)
        );

        $user->roles()->sync([$roleModel->id]);

        return $user;
    }

    public function test_view_permission_alone_opens_the_list(): void
    {
        $this->actingAs($this->userWith(['income.view']))
            ->get(route('admin.account.income.index'))
            ->assertSuccessful();
    }

    public function test_view_permission_does_not_allow_creating(): void
    {
        $this->actingAs($this->userWith(['income.view']))
            ->get(route('admin.account.income.create'))
            ->assertForbidden();
    }

    public function test_create_permission_opens_the_create_form(): void
    {
        $this->actingAs($this->userWith(['income.create']))
            ->get(route('admin.account.income.create'))
            ->assertSuccessful();
    }

    public function test_edit_permission_also_grants_read_of_the_list(): void
    {
        // Someone who may edit must be able to see the list they edit from.
        $this->actingAs($this->userWith(['income.edit']))
            ->get(route('admin.account.income.index'))
            ->assertSuccessful();
    }

    public function test_no_finance_permission_is_denied(): void
    {
        $this->actingAs($this->userWith(['student.view']))
            ->get(route('admin.account.income.index'))
            ->assertForbidden();
    }

    public function test_permissions_do_not_leak_between_modules(): void
    {
        $user = $this->userWith(['income.view', 'income.create']);

        $this->actingAs($user)->get(route('admin.account.expense.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.payment-account.index'))->assertForbidden();
    }

    public function test_super_admin_needs_no_explicit_grants(): void
    {
        $user = User::create([
            'first_name' => 'Root', 'last_name' => 'User',
            'email' => 'root@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('admin.account.income.index'))->assertSuccessful();
        $this->actingAs($user)->get(route('admin.payment-account.index'))->assertSuccessful();
    }

    public function test_the_seeded_accountant_role_can_work_the_finance_module(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $user = User::create([
            'first_name' => 'Ann', 'last_name' => 'Accountant',
            'email' => 'ann@example.test', 'password' => 'password',
            'role' => 'accountant', 'is_active' => true,
        ]);
        $user->roles()->sync([Role::where('name', 'accountant')->firstOrFail()->id]);

        $this->actingAs($user)->get(route('admin.account.income.index'))->assertSuccessful();
        $this->actingAs($user)->get(route('admin.account.expense.index'))->assertSuccessful();
        $this->actingAs($user)->get(route('admin.payment-account.index'))->assertSuccessful();

        // ...but not the academic admin surface.
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
}
