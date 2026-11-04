<?php

namespace Tests\Feature\Authorization;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every link a person is shown leads somewhere they may go: a menu offering
 * screens that then refuse is a dead end, whatever roles they hold.
 */
class RoleMenuLinksTest extends TestCase
{
    use RefreshDatabase;

    public static function people(): array
    {
        return [
            'bursar' => [['accountant']],
            'front desk' => [['receptionist']],
            'teacher and bursar' => [['teacher', 'accountant']],
            'teacher and front desk' => [['teacher', 'receptionist']],
            'teacher' => [['teacher']],
        ];
    }

    /** @dataProvider people */
    public function test_every_link_on_the_dashboard_opens(array $roles): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $user = User::create(['first_name' => 'Pat', 'last_name' => 'Staff', 'email' => 'pat@example.test', 'password' => 'password', 'role' => User::primaryRoleFor($roles), 'is_active' => true]);
        $user->branches()->attach($branch->id, ['is_default' => true]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));
        $this->actingAs($user);

        $html = $this->get(route('admin.dashboard'))->assertSuccessful()->getContent();
        preg_match_all('#href="(http://localhost/admin/[^"\#?]+)#', $html, $m);
        $refused = [];
        foreach (array_unique($m[1]) as $url) {
            $status = $this->get($url)->getStatusCode();
            if ($status === 403) {
                $refused[] = $url;
            }
        }

        $this->assertSame([], $refused, implode(', ', $roles).' is shown links it may not open');
    }
}
