<?php

namespace Tests\Feature\Hr;

use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Leave balances are derived, never stored, and the annual cap counts approved
 * AND pending requests — otherwise two requests that each fit could both be
 * approved and blow the limit together.
 */
class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private LeaveType $annual;

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
        ]);

        $this->annual = LeaveType::create([
            'title' => 'Annual Leave', 'slug' => 'annual-leave',
            'annual_limit' => 20, 'is_paid' => true, 'is_active' => true,
        ]);

        $this->actingAs($this->admin);
    }

    private function apply(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('admin.leaves.store'), array_merge([
            'user_id' => $this->teacher->id,
            'leave_type_id' => $this->annual->id,
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-05',
            'pay_type' => 'paid',
            'reason' => 'Family',
        ], $overrides));
    }

    public function test_days_are_counted_inclusive_of_both_ends(): void
    {
        $leave = Leave::create([
            'user_id' => $this->teacher->id, 'leave_type_id' => $this->annual->id,
            'apply_date' => '2026-02-01', 'from_date' => '2026-03-01', 'to_date' => '2026-03-05',
            'pay_type' => 'paid', 'status' => 'pending',
        ]);

        // 1st to 5th is five days, not four.
        $this->assertSame(5, $leave->daysCount());
    }

    public function test_a_request_is_recorded_as_pending(): void
    {
        $this->apply()->assertRedirect();

        $leave = Leave::firstOrFail();
        $this->assertSame('pending', $leave->status);
        $this->assertSame(5, $leave->daysCount());
    }

    public function test_pending_requests_already_consume_the_allowance(): void
    {
        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-05']);

        // Pending, not yet approved — but it must still count.
        $this->assertSame(5, Leave::usedDaysForType($this->teacher->id, $this->annual->id, 2026));
        $this->assertSame(15, Leave::remainingForType($this->annual, $this->teacher->id, 2026));
    }

    public function test_a_request_beyond_the_allowance_is_refused(): void
    {
        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-18']); // 18 days

        $this->apply(['from_date' => '2026-05-01', 'to_date' => '2026-05-05'])  // +5 = 23 > 20
            ->assertSessionHasErrors('leave_type_id');

        $this->assertSame(1, Leave::count());
    }

    public function test_two_requests_that_each_fit_cannot_both_be_approved(): void
    {
        // Each is 15 days; the cap is 20. The second must be refused at entry
        // because the first is already pending.
        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-15']);
        $this->apply(['from_date' => '2026-06-01', 'to_date' => '2026-06-15'])
            ->assertSessionHasErrors('leave_type_id');

        $this->assertSame(1, Leave::count());
    }

    public function test_an_uncapped_type_never_refuses(): void
    {
        $unpaid = LeaveType::create([
            'title' => 'Unpaid Leave', 'slug' => 'unpaid-leave',
            'annual_limit' => null, 'is_paid' => false, 'is_active' => true,
        ]);

        $this->apply([
            'leave_type_id' => $unpaid->id,
            'from_date' => '2026-01-01', 'to_date' => '2026-06-30',
            'pay_type' => 'unpaid',
        ])->assertRedirect();

        $this->assertNull(Leave::remainingForType($unpaid, $this->teacher->id, 2026));
        $this->assertSame(1, Leave::count());
    }

    public function test_the_allowance_is_per_staff_member(): void
    {
        $other = User::create([
            'first_name' => 'Ann', 'last_name' => 'Other',
            'email' => 'other@example.test', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);

        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-18']);

        // Regression: the reference implementation of this summed EVERY staff
        // member's leave, so one person's absence exhausted everyone's balance.
        $this->assertSame(0, Leave::usedDaysForType($other->id, $this->annual->id, 2026));
        $this->assertSame(20, Leave::remainingForType($this->annual, $other->id, 2026));
    }

    public function test_the_allowance_resets_each_calendar_year(): void
    {
        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-18']);

        $this->assertSame(18, Leave::usedDaysForType($this->teacher->id, $this->annual->id, 2026));
        $this->assertSame(0, Leave::usedDaysForType($this->teacher->id, $this->annual->id, 2027));
    }

    public function test_a_rejected_request_frees_the_days_again(): void
    {
        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-18']);
        $leave = Leave::firstOrFail();

        $this->post(route('admin.leaves.reject', $leave), ['review_notes' => 'Not this term'])
            ->assertRedirect();

        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame(0, Leave::usedDaysForType($this->teacher->id, $this->annual->id, 2026));
    }

    public function test_approving_records_who_decided_it(): void
    {
        $this->apply();
        $leave = Leave::firstOrFail();

        $this->post(route('admin.leaves.approve', $leave))->assertRedirect();

        $leave->refresh();
        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->admin->id, $leave->reviewed_by);
        $this->assertNotNull($leave->reviewed_at);
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $this->apply();
        $leave = Leave::firstOrFail();

        $this->post(route('admin.leaves.approve', $leave));
        $this->post(route('admin.leaves.approve', $leave))->assertSessionHas('error');

        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_an_end_date_before_the_start_is_rejected(): void
    {
        $this->apply(['from_date' => '2026-03-10', 'to_date' => '2026-03-01'])
            ->assertSessionHasErrors('to_date');

        $this->assertSame(0, Leave::count());
    }

    public function test_a_leave_type_in_use_cannot_be_deleted(): void
    {
        $this->apply();

        $this->delete(route('admin.leave-types.destroy', $this->annual))->assertSessionHas('error');

        $this->assertSame(1, LeaveType::count());
    }

    public function test_the_screens_render(): void
    {
        $this->apply();

        $this->get(route('admin.leaves.index'))->assertSuccessful();
        $this->get(route('admin.leave-types.index'))->assertSuccessful();
    }

    public function test_remaining_days_can_be_looked_up(): void
    {
        $this->apply(['from_date' => '2026-03-01', 'to_date' => '2026-03-05']);

        $this->getJson(route('admin.leaves.remaining', [
            'user_id' => $this->teacher->id,
            'leave_type_id' => $this->annual->id,
            'year' => 2026,
        ]))->assertSuccessful()->assertJson([
            'limit' => 20,
            'used' => 5,
            'remaining' => 15,
        ]);
    }
}
