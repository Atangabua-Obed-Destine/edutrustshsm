<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The sidebar used to gate on the legacy role string, so it promised links the
 * router denied (it showed the whole finance tree to `accountant`, a role that
 * could not exist). It now gates on the same permissions the controllers check.
 */
class SidebarVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function userAs(string $roleName, string $legacyRole): User
    {
        $user = User::create([
            'first_name' => 'Test', 'last_name' => ucfirst($roleName),
            'email' => $roleName.'@example.test', 'password' => 'password',
            'role' => $legacyRole, 'is_active' => true,
        ]);
        $user->roles()->sync([Role::where('name', $roleName)->firstOrFail()->id]);

        return $user;
    }

    public function test_accountant_sees_finance_but_not_staff_administration(): void
    {
        $html = $this->actingAs($this->userAs('accountant', 'accountant'))
            ->get(route('admin.account.income.index'))
            ->assertSuccessful()
            ->getContent();

        $this->assertStringContainsString(route('admin.account.income.index'), $html);
        $this->assertStringNotContainsString(route('admin.users.index'), $html,
            'an accountant has no staff-administration permission, so that nav must be hidden');
    }

    public function test_super_admin_sees_staff_administration(): void
    {
        $user = User::create([
            'first_name' => 'Root', 'last_name' => 'User',
            'email' => 'root@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);

        $html = $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertSuccessful()->getContent();

        $this->assertStringContainsString(route('admin.users.index'), $html);
    }

    public function test_rendering_the_sidebar_does_not_run_a_query_per_permission_check(): void
    {
        $user = $this->userAs('accountant', 'accountant');

        DB::enableQueryLog();
        $this->actingAs($user)->get(route('admin.account.income.index'))->assertSuccessful();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The sidebar makes ~8 canAny() calls over ~40 permission names. Without
        // per-request memoization that alone would be dozens of queries.
        $this->assertLessThan(40, $count, "sidebar render issued {$count} queries");
    }
}
