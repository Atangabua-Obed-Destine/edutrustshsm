<?php

namespace Tests\Feature\Lists;

use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The shared list toolbar (App\Support\Listing) on the people lists: staff
 * accounts, the HR staff list, former pupils, admission applications, parent
 * accounts, staff leave and the audit log. Each opens with its region, sorts
 * by a heading, and exports the filtered rows.
 */
class PeopleListsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Saint Joseph College', 'school_code' => 'SJC', 'school_level_mode' => 'secondary', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($this->admin);
    }

    /** @return array<int, array<int, string>> the data rows, heading left out */
    private function csv(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $content))));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $lines[0]);

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), array_slice($lines, 1));
    }

    private function staffMember(string $first, string $last, string $role, array $extra = []): User
    {
        $user = User::create(array_merge([
            'first_name' => $first, 'last_name' => $last, 'email' => strtolower($last).'@example.test',
            'password' => 'password', 'role' => $role, 'is_active' => true,
        ], $extra));
        $user->branches()->attach($this->branch->id, ['is_default' => true]);

        return $user;
    }

    // ------------------------------------------------------------ staff accounts

    public function test_staff_accounts_sort_and_export(): void
    {
        $this->staffMember('Zed', 'Zulu', 'teacher');
        $this->staffMember('Bob', 'Brown', 'staff');
        $this->staffMember('Cat', 'Cole', 'accountant', ['is_active' => false]);

        $names = fn ($r) => $r->viewData('users')->getCollection()->pluck('last_name')->all();

        $page = $this->get(route('admin.users.index'))->assertOk()->assertSee('data-list-region="users"', false);
        $this->assertSame(['Admin', 'Brown', 'Cole', 'Zulu'], $names($page));

        $this->assertSame(['Zulu', 'Cole', 'Brown', 'Admin'], $names($this->get(route('admin.users.index', ['sort' => 'name', 'dir' => 'desc']))));

        $response = $this->get(route('admin.users.index', ['role' => 'teacher', 'export' => 'csv']))->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('$2y$', $content, 'no password hash in an export');
        $rows = $this->csv($response);
        $this->assertCount(1, $rows);
        $this->assertSame('Zulu Zed', $rows[0][0]);
        $this->assertContains(__('Teacher'), $rows[0]);

        $rows = $this->csv($this->get(route('admin.users.index', ['status' => 'inactive', 'export' => 'csv'])));
        $this->assertCount(1, $rows);
        $this->assertSame('Cole Cat', $rows[0][0]);
        $this->assertContains(__('Inactive'), $rows[0]);
    }

    // -------------------------------------------------------------- HR staff list

    public function test_hr_staff_list_sorts_and_exports(): void
    {
        $sciences = Department::create(['branch_id' => $this->branch->id, 'name' => 'Sciences']);
        $arts = Department::create(['branch_id' => $this->branch->id, 'name' => 'Arts']);
        $this->staffMember('Amy', 'Able', 'teacher', ['staff_id' => 'STF001', 'department_id' => $sciences->id, 'salary_type' => 1, 'basic_salary' => 250000]);
        $this->staffMember('Zed', 'Zulu', 'staff', ['staff_id' => 'STF002', 'department_id' => $arts->id, 'salary_type' => 2, 'basic_salary' => 100000]);

        $ids = fn ($r) => $r->viewData('staff')->getCollection()->pluck('staff_id')->all();

        $page = $this->get(route('admin.staff.index'))->assertOk()->assertSee('data-list-region="staff"', false);
        $this->assertSame(['STF001', 'STF002'], $ids($page));

        $this->assertSame(['STF002', 'STF001'], $ids($this->get(route('admin.staff.index', ['sort' => 'department', 'dir' => 'asc']))));
        $this->assertSame(['STF002', 'STF001'], $ids($this->get(route('admin.staff.index', ['sort' => 'basic_salary', 'dir' => 'asc']))));

        $rows = $this->csv($this->get(route('admin.staff.index', ['department_id' => $sciences->id, 'export' => 'csv']))->assertOk());
        $this->assertCount(1, $rows);
        $this->assertSame(['STF001', 'Amy Able', 'Sciences'], array_slice($rows[0], 0, 3));
        $this->assertContains('250000', $rows[0]);
    }

    // ------------------------------------------------------------- former pupils

    public function test_former_pupils_sort_and_export(): void
    {
        $pupil = fn (string $first, string $last, string $status, ?string $left, array $extra = []) => Student::create(array_merge([
            'branch_id' => $this->branch->id, 'student_id' => 'SJC'.strtoupper(substr($last, 0, 3)),
            'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '2010-01-01', 'gender' => 'female',
            'status' => $status, 'admission_date' => '2020-09-07', 'withdrawal_date' => $left,
        ], $extra));

        $pupil('Ann', 'Alpha', 'graduated', '2025-07-31');
        $pupil('Ben', 'Bravo', 'withdrawn', '2026-03-10', ['destination_school' => 'Lycée Bilingue', 'withdrawal_reason' => 'Family moved']);
        $pupil('Cyd', 'Charlie', 'expelled', '2026-01-15');
        $pupil('Dan', 'Delta', 'active', null);

        $names = fn ($r) => $r->viewData('students')->getCollection()->pluck('last_name')->all();

        $page = $this->get(route('admin.students.archive'))->assertOk()->assertSee('data-list-region="former-pupils"', false);
        $this->assertSame(['Bravo', 'Charlie', 'Alpha'], $names($page), 'latest leaver first');

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names($this->get(route('admin.students.archive', ['sort' => 'name', 'dir' => 'asc']))));

        $rows = $this->csv($this->get(route('admin.students.archive', ['status' => 'withdrawn', 'export' => 'csv']))->assertOk());
        $this->assertCount(1, $rows);
        $this->assertSame('Ben Bravo', $rows[0][1]);
        $this->assertContains(__('Transferred'), $rows[0]);
        $this->assertContains('Lycée Bilingue', $rows[0]);
        $this->assertContains('10/03/2026', $rows[0]);

        $this->assertCount(2, $this->csv($this->get(route('admin.students.archive', ['year' => 2026, 'export' => 'csv']))));
    }

    // ------------------------------------------------------ admission applications

    public function test_admission_applications_sort_and_export(): void
    {
        $session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $applicant = Applicant::create(['branch_id' => $this->branch->id, 'first_name' => 'Pat', 'last_name' => 'Parent', 'email' => 'pat@example.test', 'password' => 'password']);

        foreach ([['APP-1', 'Ann', 'Alpha', 'pending', '2026-09-01'], ['APP-2', 'Ben', 'Bravo', 'accepted', '2026-09-03'], ['APP-3', 'Cyd', 'Charlie', 'rejected', '2026-09-02']] as [$number, $first, $last, $status, $on]) {
            $application = AdmissionApplication::create([
                'branch_id' => $this->branch->id, 'applicant_id' => $applicant->id, 'application_number' => $number,
                'academic_session_id' => $session->id, 'form_id' => $form->id,
                'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '2014-01-01', 'gender' => 'female',
                'emergency_contact_name' => 'Pat Parent', 'emergency_contact_phone' => '670000000', 'status' => $status,
            ]);
            $application->created_at = $on.' 10:00:00';
            $application->save();
        }

        $numbers = fn ($r) => $r->viewData('applications')->getCollection()->pluck('application_number')->all();

        $page = $this->get(route('admin.admissions.applications.index'))->assertOk()->assertSee('data-list-region="applications"', false);
        $this->assertSame(['APP-2', 'APP-3', 'APP-1'], $numbers($page), 'newest first');

        $this->assertSame(['APP-1', 'APP-2', 'APP-3'], $numbers($this->get(route('admin.admissions.applications.index', ['sort' => 'name', 'dir' => 'asc']))));

        $rows = $this->csv($this->get(route('admin.admissions.applications.index', ['status' => 'accepted', 'export' => 'csv']))->assertOk());
        $this->assertCount(1, $rows);
        $this->assertSame(['APP-2', 'Ben Bravo'], array_slice($rows[0], 0, 2));
        $this->assertContains(__('Accepted'), $rows[0]);
        $this->assertContains('Form 1', $rows[0]);
    }

    // ------------------------------------------------------------- parent accounts

    public function test_parent_accounts_sort_and_export_without_secrets(): void
    {
        $alpha = Guardian::create(['branch_id' => $this->branch->id, 'guardian_name' => 'Alpha Parent', 'emergency_contact_name' => 'Alpha Parent', 'emergency_contact_phone' => '677000111', 'guardian_email' => 'alpha@example.test', 'password' => 'secret-pass', 'portal_access' => true]);
        Guardian::create(['branch_id' => $this->branch->id, 'father_name' => 'Charlie Parent', 'emergency_contact_name' => 'Charlie Parent', 'emergency_contact_phone' => '677000222', 'father_phone' => '677000222', 'invite_token' => 'SECRETTOKEN123', 'invite_expires_at' => now()->addDay()]);
        Guardian::create(['branch_id' => $this->branch->id, 'mother_name' => 'Bravo Parent', 'emergency_contact_name' => 'Bravo Parent', 'emergency_contact_phone' => '677000333']);

        $child = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'SJC0001', 'first_name' => 'Kim', 'last_name' => 'Alpha', 'date_of_birth' => '2012-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-01']);
        $alpha->students()->attach($child->id, ['relationship' => 'mother', 'is_primary' => true, 'can_access_portal' => true]);

        $names = fn ($r) => $r->viewData('guardians')->getCollection()->map(fn ($g) => $g->display_name)->all();

        $page = $this->get(route('admin.parent-portal.index'))->assertOk()->assertSee('data-list-region="parent-accounts"', false);
        $this->assertSame(['Bravo Parent', 'Charlie Parent', 'Alpha Parent'], $names($page), 'newest first');

        $this->assertSame(['Alpha Parent', 'Bravo Parent', 'Charlie Parent'], $names($this->get(route('admin.parent-portal.index', ['sort' => 'name', 'dir' => 'asc']))));
        $this->assertSame(['Alpha Parent', 'Charlie Parent', 'Bravo Parent'], $names($this->get(route('admin.parent-portal.index', ['sort' => 'status', 'dir' => 'asc']))), 'active, invited, then none');

        $response = $this->get(route('admin.parent-portal.index', ['export' => 'csv']))->assertOk();
        $content = $response->streamedContent();
        $this->assertStringNotContainsString('SECRETTOKEN123', $content, 'no invite token in an export');
        $this->assertStringNotContainsString('$2y$', $content, 'no password hash in an export');
        $rows = $this->csv($response);
        $this->assertCount(3, $rows);

        $rows = $this->csv($this->get(route('admin.parent-portal.index', ['q' => 'Alpha Parent', 'export' => 'csv'])));
        $this->assertCount(1, $rows);
        $this->assertSame('Alpha Parent', $rows[0][0]);
        $this->assertContains('Kim Alpha', $rows[0]);
        $this->assertContains(__('Active'), $rows[0]);
    }

    // ------------------------------------------------------------------ staff leave

    public function test_staff_leave_sorts_and_exports(): void
    {
        $tom = $this->staffMember('Tom', 'Teacher', 'teacher', ['staff_id' => 'STF001']);
        $amy = $this->staffMember('Amy', 'Able', 'staff', ['staff_id' => 'STF002']);
        $annual = LeaveType::create(['title' => 'Annual Leave', 'slug' => 'annual-leave', 'annual_limit' => 20, 'is_paid' => true, 'is_active' => true]);

        foreach ([[$tom, '2026-03-01', '2026-03-05', 'pending'], [$amy, '2026-05-10', '2026-05-11', 'approved'], [$tom, '2026-01-10', '2026-01-10', 'rejected']] as [$who, $from, $to, $status]) {
            Leave::create(['user_id' => $who->id, 'leave_type_id' => $annual->id, 'apply_date' => $from, 'from_date' => $from, 'to_date' => $to, 'pay_type' => 'paid', 'status' => $status]);
        }

        $rows = fn ($r) => $r->viewData('leaves')->getCollection()->map(fn ($l) => $l->user->first_name.' '.$l->from_date->format('m'))->all();

        $page = $this->get(route('admin.leaves.index', ['year' => 2026]))->assertOk()->assertSee('data-list-region="leaves"', false);
        $this->assertSame(['Amy 05', 'Tom 03', 'Tom 01'], $rows($page), 'latest start first');

        $this->assertSame(['Tom 01', 'Tom 03', 'Amy 05'], $rows($this->get(route('admin.leaves.index', ['year' => 2026, 'sort' => 'from', 'dir' => 'asc']))));
        $this->assertSame('Amy 05', $rows($this->get(route('admin.leaves.index', ['year' => 2026, 'sort' => 'staff', 'dir' => 'asc'])))[0]);

        $csv = $this->csv($this->get(route('admin.leaves.index', ['year' => 2026, 'status' => 'approved', 'export' => 'csv']))->assertOk());
        $this->assertCount(1, $csv);
        $this->assertSame(['Amy Able', 'STF002', 'Annual Leave', '10/05/2026', '11/05/2026', '2', '2 / 20'], array_slice($csv[0], 0, 7));
        $this->assertContains(__('Approved'), $csv[0]);
    }

    // -------------------------------------------------------------------- audit log

    private function probes(): void
    {
        foreach ([['10.0.0.1', '2026-01-01 08:00:00'], ['10.0.0.2', '2026-03-01 08:00:00'], ['10.0.0.3', '2026-02-01 08:00:00']] as [$ip, $at]) {
            AuditLog::create(['user_id' => $this->admin->id, 'action' => 'probed', 'model_type' => Student::class, 'model_id' => 7, 'ip_address' => $ip, 'new_values' => ['note' => $ip], 'created_at' => $at]);
        }
    }

    public function test_audit_log_sorts_and_exports(): void
    {
        $this->probes();
        $ips = fn ($r) => $r->viewData('logs')->getCollection()->pluck('ip_address')->all();

        $page = $this->get(route('admin.audit-log.index', ['action' => 'probed']))->assertOk()->assertSee('data-list-region="audit-log"', false);
        $this->assertSame(['10.0.0.2', '10.0.0.3', '10.0.0.1'], $ips($page), 'newest first');

        $this->assertSame(['10.0.0.1', '10.0.0.2', '10.0.0.3'], $ips($this->get(route('admin.audit-log.index', ['action' => 'probed', 'sort' => 'ip', 'dir' => 'asc']))));

        $rows = $this->csv($this->get(route('admin.audit-log.index', ['action' => 'probed', 'export' => 'csv']))->assertOk());
        $this->assertCount(3, $rows);
        $this->assertSame(['01/03/2026 08:00', 'Ada Admin'], array_slice($rows[0], 0, 2));
        $this->assertContains('Student', $rows[0]);
        $this->assertContains('10.0.0.2', $rows[0]);
    }

    public function test_exporting_the_audit_log_still_needs_the_export_permission(): void
    {
        $this->probes();

        $viewer = $this->staffMember('Val', 'Viewer', 'admin');
        $role = Role::create(['name' => 'audit-viewer', 'display_name' => 'Audit viewer', 'is_system' => false]);
        $role->permissions()->sync([Permission::firstOrCreate(['name' => 'audit-log.view'], ['display_name' => 'audit-log.view', 'group_name' => 'Test'])->id]);
        $viewer->roles()->sync([$role->id]);

        $this->actingAs($viewer)->get(route('admin.audit-log.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.audit-log.index', ['export' => 'csv']))->assertForbidden();
    }
}
