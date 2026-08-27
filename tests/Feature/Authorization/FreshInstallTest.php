<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization is now enforced through the Role/Permission tables, so a fresh
 * install that does not seed them would lock out everyone but super_admin.
 */
class FreshInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_produces_a_usable_permission_catalogue(): void
    {
        $this->seed();

        $this->assertGreaterThan(200, Permission::count());
        $this->assertNotNull(Role::where('name', 'super_admin')->first());
        $this->assertNotNull(Role::where('name', 'accountant')->first());
    }

    public function test_the_seeded_admin_has_its_rbac_role_attached(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@edutrustschool.local')->firstOrFail();

        $this->assertTrue($admin->roles()->where('name', 'super_admin')->exists());
        $this->assertNotEmpty($admin->permissionNames());
    }

    public function test_the_seeded_admin_can_reach_the_dashboard(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@edutrustschool.local')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertSuccessful();
    }
}
