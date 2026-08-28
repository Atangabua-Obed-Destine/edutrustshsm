<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 0 regression guards:
 *  - `accountant` is a usable role and reaches the finance routes.
 *  - Roles without a portal never land on an admin-only page (the old 403 trap).
 *  - The staff carve-out is a sibling of the admin group, not nested inside it.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $role.'@example.test',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_accountant_role_persists(): void
    {
        $user = $this->user('accountant');

        $this->assertSame('accountant', $user->fresh()->role);
        $this->assertTrue($user->isAccountant());
        $this->assertTrue($user->isStaff(), 'an accountant is staff for payroll purposes');
    }

    public function test_accountant_reaches_finance_routes(): void
    {
        // Needs BOTH: the legacy role string to clear the route group's
        // role: middleware, and the seeded RBAC grants to clear permission:.
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $user = $this->user('accountant');
        $user->roles()->sync([\App\Models\Role::where('name', 'accountant')->firstOrFail()->id]);

        $this->actingAs($user)
            ->get(route('admin.account.income.index'))
            ->assertSuccessful();
    }

    public function test_accountant_is_denied_admin_only_routes(): void
    {
        $this->actingAs($this->user('accountant'))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_staff_reaches_student_management(): void
    {
        // Regression: this group used to be nested inside role:super_admin,admin,
        // so the outer middleware rejected staff before the carve-out was consulted.
        // Both gates must now pass: the route group's role: check and student.view.
        $user = $this->user('staff');
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'registrar'],
            ['display_name' => 'Registrar', 'is_system' => false]
        );
        $role->permissions()->sync([
            \App\Models\Permission::firstOrCreate(
                ['name' => 'student.view'],
                ['display_name' => 'View', 'group_name' => 'Student']
            )->id,
        ]);
        $user->roles()->sync([$role->id]);

        $this->actingAs($user)
            ->get(route('admin.students.index'))
            ->assertSuccessful();
    }

    public function test_staff_without_the_permission_is_denied_student_management(): void
    {
        $this->actingAs($this->user('staff'))
            ->get(route('admin.students.index'))
            ->assertForbidden();
    }

    public function test_staff_is_denied_finance_routes(): void
    {
        $this->actingAs($this->user('staff'))
            ->get(route('admin.account.income.index'))
            ->assertForbidden();
    }

    public static function homeRouteProvider(): array
    {
        return [
            ['super_admin', 'admin.dashboard'],
            ['admin', 'admin.dashboard'],
            ['accountant', 'admin.account.income.index'],
            ['staff', 'admin.students.index'],
            ['teacher', null],
        ];
    }

    #[DataProvider('homeRouteProvider')]
    public function test_home_route_is_role_aware(string $role, ?string $expected): void
    {
        $this->assertSame($expected, (new User(['role' => $role]))->homeRoute());
    }

    public function test_role_without_a_portal_is_not_logged_into_a_403(): void
    {
        $this->user('teacher');

        $response = $this->post('/login', [
            'email' => 'teacher@example.test',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_login_lands_on_the_dashboard(): void
    {
        $this->user('admin');

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticated();
    }
}
