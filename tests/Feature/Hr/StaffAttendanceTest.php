<?php

namespace Tests\Feature\Hr;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);

        $this->teacher = User::create([
            'first_name' => 'Tom', 'last_name' => 'Teacher',
            'email' => 'teacher@example.test', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true, 'staff_id' => 'STF001',
            'basic_salary' => 200000,
        ]);

        $this->actingAs($this->admin);
    }

    private function mark(string $date, string $status, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.staff-attendance.store'), [
            'date' => $date,
            'attendance' => [
                array_merge(['user_id' => $this->teacher->id, 'status' => $status], $extra),
            ],
        ]);
    }

    public function test_attendance_is_recorded(): void
    {
        $this->mark('2026-03-02', 'present', ['check_in' => '07:30', 'check_out' => '15:00'])
            ->assertRedirect();

        $record = StaffAttendance::firstOrFail();

        $this->assertSame('present', $record->status);
        $this->assertSame($this->teacher->id, $record->user_id);
        $this->assertSame($this->admin->id, $record->recorded_by);
    }

    public function test_saving_the_same_day_twice_updates_rather_than_duplicates(): void
    {
        $this->mark('2026-03-02', 'present');
        $this->mark('2026-03-02', 'absent');

        // One record per person per day.
        $this->assertSame(1, StaffAttendance::count());
        $this->assertSame('absent', StaffAttendance::firstOrFail()->status);
    }

    public function test_attendance_cannot_be_written_for_an_unknown_user(): void
    {
        $this->post(route('admin.staff-attendance.store'), [
            'date' => '2026-03-02',
            'attendance' => [
                ['user_id' => 99999, 'status' => 'present'],
            ],
        ])->assertSessionHasErrors('attendance.0.user_id');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_a_checkout_before_checkin_is_rejected(): void
    {
        $this->mark('2026-03-02', 'present', ['check_in' => '15:00', 'check_out' => '07:30'])
            ->assertSessionHasErrors('attendance.0.check_out');

        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_approved_leave_is_proposed_on_the_register(): void
    {
        $type = LeaveType::create([
            'title' => 'Annual', 'slug' => 'annual', 'annual_limit' => 20,
            'is_paid' => true, 'is_active' => true,
        ]);

        Leave::create([
            'user_id' => $this->teacher->id, 'leave_type_id' => $type->id,
            'apply_date' => '2026-02-01', 'from_date' => '2026-03-01', 'to_date' => '2026-03-05',
            'pay_type' => 'paid', 'status' => 'approved',
        ]);

        $response = $this->get(route('admin.staff-attendance.index', ['date' => '2026-03-02']));

        $response->assertSuccessful();
        // Otherwise a clerk marking the day "all present" would silently record
        // someone on approved leave as absent.
        $this->assertTrue($response->viewData('onLeave')->has($this->teacher->id));
    }

    public function test_pending_leave_is_not_proposed(): void
    {
        $type = LeaveType::create([
            'title' => 'Annual', 'slug' => 'annual', 'annual_limit' => 20,
            'is_paid' => true, 'is_active' => true,
        ]);

        Leave::create([
            'user_id' => $this->teacher->id, 'leave_type_id' => $type->id,
            'apply_date' => '2026-02-01', 'from_date' => '2026-03-01', 'to_date' => '2026-03-05',
            'pay_type' => 'paid', 'status' => 'pending',
        ]);

        $response = $this->get(route('admin.staff-attendance.index', ['date' => '2026-03-02']));

        $this->assertFalse($response->viewData('onLeave')->has($this->teacher->id));
    }

    public function test_the_monthly_rate_excludes_leave_and_holidays(): void
    {
        $this->mark('2026-03-02', 'present');
        $this->mark('2026-03-03', 'present');
        $this->mark('2026-03-04', 'absent');
        $this->mark('2026-03-05', 'leave');
        $this->mark('2026-03-06', 'holiday');

        $response = $this->get(route('admin.staff-attendance.report', ['year' => 2026, 'month' => 3]));
        $row = $response->viewData('rows')->first();

        $this->assertSame(2, $row->present);
        $this->assertSame(1, $row->absent);
        $this->assertSame(1, $row->leave);
        $this->assertSame(1, $row->holiday);
        // 2 present out of 3 workable days (leave and holiday are not failures
        // to attend), not 2 of 5.
        $this->assertEqualsWithDelta(66.7, $row->rate, 0.1);
    }

    public function test_late_counts_as_present_for_the_rate(): void
    {
        $this->mark('2026-03-02', 'late');
        $this->mark('2026-03-03', 'absent');

        $row = $this->get(route('admin.staff-attendance.report', ['year' => 2026, 'month' => 3]))
            ->viewData('rows')->first();

        $this->assertSame(1, $row->late);
        $this->assertEqualsWithDelta(50.0, $row->rate, 0.1);
    }

    public function test_a_month_with_nothing_recorded_has_no_rate(): void
    {
        $row = $this->get(route('admin.staff-attendance.report', ['year' => 2026, 'month' => 3]))
            ->viewData('rows')->first();

        // Rather than reporting 0% or 100% for a month nobody filled in.
        $this->assertNull($row->rate);
    }

    public function test_attendance_does_not_affect_pay(): void
    {
        $this->mark('2026-03-02', 'absent');
        $this->mark('2026-03-03', 'absent');

        // Policy, carried over deliberately: payroll is computed from
        // basic_salary. Absence is an HR record, not a deduction.
        $payroll = Payroll::create([
            'user_id' => $this->teacher->id,
            'basic_salary' => 200000, 'gross_salary' => 200000,
            'total_allowance' => 0, 'bonus' => 0, 'total_deduction' => 0,
            'tax' => 0, 'employer_tax' => 0,
            'net_salary' => 200000, 'total_cost' => 200000,
            'salary_month' => '2026-03', 'status' => Payroll::STATUS_UNPAID,
        ]);

        $this->assertEquals(200000, (float) $payroll->net_salary);
    }

    public function test_the_screens_render(): void
    {
        $this->get(route('admin.staff-attendance.index'))->assertSuccessful();
        $this->get(route('admin.staff-attendance.report'))->assertSuccessful();
    }
}
