<?php

namespace Tests\Feature\Authorization;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use App\Services\TeachingScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A person may hold several roles, as in the reference system: the teacher
 * who is also the bursar, the head teacher who also teaches. Each role opens
 * its part of the school; each screen still checks its own permission.
 */
class MultipleRolesTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private ClassSection $p3;
    private ClassSection $p4;
    private Subject $maths;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        $session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MATH', 'is_active' => true]);
        foreach ([3, 4] as $order) {
            $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary '.$order, 'short_name' => 'P'.$order, 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => $order, 'is_active' => true]);
            DB::table('form_subject')->insert(['form_id' => $form->id, 'subject_id' => $this->maths->id, 'coefficient' => 1, 'type' => 'core']);
            $this->{'p'.$order} = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P'.$order.'A', 'max_students' => 40, 'is_active' => true]);
        }
        $this->session = $session;
    }

    private AcademicSession $session;

    private function person(string $email, array $roles): User
    {
        $user = User::create(['first_name' => 'Pat', 'last_name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'password', 'role' => User::primaryRoleFor($roles), 'is_active' => true]);
        $user->branches()->attach($this->branch->id, ['is_default' => true]);
        $user->roles()->sync(Role::whereIn('name', $roles)->pluck('id'));

        return $user;
    }

    public function test_the_hr_screen_gives_a_teacher_a_second_role(): void
    {
        $admin = $this->person('admin@example.test', ['super_admin']);
        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'first_name' => 'Comfort', 'last_name' => 'Fon', 'email' => 'bursar.teacher@example.test',
            'roles' => ['teacher', 'accountant'], 'salary_type' => 1, 'basic_salary' => 150000, 'password' => 'secret-pass-1',
        ])->assertRedirect(route('admin.staff.index'));

        $user = User::where('email', 'bursar.teacher@example.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(['teacher', 'accountant'], $user->roles()->pluck('name')->all());
        $this->assertSame('accountant', $user->role);

        // Editing an administrator on the HR screen keeps them an administrator.
        $head = $this->person('head@example.test', ['admin']);
        $this->actingAs($admin)->put(route('admin.staff.update', $head), [
            'first_name' => 'Head', 'last_name' => 'Teacher', 'email' => 'head@example.test',
            'roles' => ['teacher'], 'salary_type' => 1, 'basic_salary' => 200000,
        ])->assertRedirect();
        $this->assertEqualsCanonicalizing(['admin', 'teacher'], $head->fresh()->roles()->pluck('name')->all());
        $this->assertSame('admin', $head->fresh()->role);
    }

    public function test_a_teacher_who_is_also_the_bursar_teaches_and_takes_fees(): void
    {
        $user = $this->person('both@example.test', ['teacher', 'accountant']);
        $this->p3->update(['class_teacher_id' => $user->id]);
        $this->actingAs($user);

        $this->assertTrue(app(TeachingScope::class)->restricted($user), 'still only their own classes');
        $html = $this->get(route('admin.dashboard'))->assertSuccessful()->assertViewIs('admin.dashboard-teacher')->getContent();
        $this->assertStringContainsString(route('admin.marks.index'), $html);
        $this->assertStringContainsString(route('admin.collect-fees.index'), $html);
        $this->assertStringNotContainsString(route('admin.sessions.index'), $html);

        $this->get(route('admin.marks.index'))->assertSuccessful();
        $this->get(route('admin.collect-fees.index'))->assertSuccessful();
        $this->get(route('admin.sessions.index'))->assertForbidden();
    }

    public function test_a_bursar_alone_reaches_the_fees_and_nothing_academic(): void
    {
        $this->actingAs($this->person('bursar@example.test', ['accountant']));

        $this->get(route('admin.dashboard'))->assertSuccessful();
        $this->get(route('admin.collect-fees.index'))->assertSuccessful();
        $this->get(route('admin.fee-reports.index'))->assertSuccessful();
        $this->get(route('admin.marks.index'))->assertForbidden();
        $this->get(route('admin.sessions.index'))->assertForbidden();
    }

    public function test_the_front_desk_registers_pupils_and_does_not_see_the_money(): void
    {
        $this->actingAs($this->person('desk@example.test', ['receptionist']));

        $this->get(route('admin.dashboard'))->assertSuccessful();
        $this->get(route('admin.students.create'))->assertSuccessful();
        $this->get(route('admin.collect-fees.index'))->assertForbidden();
        $this->get(route('admin.marks.index'))->assertForbidden();
    }

    public function test_a_head_teacher_who_teaches_keeps_the_whole_school(): void
    {
        $head = $this->person('head@example.test', ['admin', 'teacher']);
        TeacherSubjectAssignment::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'user_id' => $head->id, 'subject_id' => $this->maths->id, 'class_section_id' => $this->p3->id]);
        $this->actingAs($head);

        $this->assertFalse(app(TeachingScope::class)->restricted($head));
        $this->get(route('admin.dashboard'))->assertSuccessful()->assertViewIs('admin.dashboard');
        $this->get(route('admin.sessions.index'))->assertSuccessful();
        // Their own subject still comes as a card on the marks screen.
        $this->get(route('admin.marks.index'))->assertSuccessful()
            ->assertViewHas('cards', fn ($cards) => $cards->count() === 1 && $cards->first()['section']->is($this->p3));
    }

    public function test_a_teacher_alone_is_unchanged(): void
    {
        $this->actingAs($this->person('teacher@example.test', ['teacher']));

        $this->get(route('admin.dashboard'))->assertSuccessful()->assertViewIs('admin.dashboard-teacher');
        $this->get(route('admin.collect-fees.index'))->assertForbidden();
        $this->get(route('admin.students.index'))->assertForbidden();
        $this->get(route('admin.sessions.index'))->assertForbidden();
    }
}
