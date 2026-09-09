<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Staff leave: recording requests and deciding them.
 *
 * The annual cap is enforced twice — once when the request is recorded and
 * again when it is approved — because the balance can change in between while
 * other requests are decided.
 */
class LeaveController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'staff-leave';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('staff-leave.approve', ['approve', 'reject']),
        ];
    }

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $leaves = Leave::with(['user:id,first_name,last_name,staff_id', 'leaveType:id,title,annual_limit', 'reviewedBy:id,first_name,last_name'])
            ->when($request->input('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->input('leave_type_id'), fn ($q, $v) => $q->where('leave_type_id', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->forYear($year)
            ->orderByDesc('from_date')
            ->paginate(25)
            ->withQueryString();

        // Remaining allowance per row, so the reviewer sees the consequence of
        // approving without opening each record.
        $leaves->getCollection()->transform(function (Leave $leave) use ($year) {
            $leave->used_days = Leave::usedDaysForType($leave->user_id, $leave->leave_type_id, $year);
            $leave->allowance = $leave->leaveType?->annual_limit;

            return $leave;
        });

        return view('admin.hr.leave.index', [
            'leaves' => $leaves,
            'types' => LeaveType::active()->orderBy('title')->get(),
            'staff' => User::staff()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'year' => $year,
            'pendingCount' => Leave::pending()->forYear($year)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'pay_type' => ['required', 'in:paid,unpaid'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $type = LeaveType::findOrFail($validated['leave_type_id']);
        $days = $this->daysBetween($validated['from_date'], $validated['to_date']);
        $year = Carbon::parse($validated['from_date'])->year;

        $this->assertWithinAllowance($type, (int) $validated['user_id'], $year, $days);

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('leave', 'local');
        }

        Leave::create($validated + [
            'apply_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        return back()->with('success', __('Leave request recorded.'));
    }

    public function approve(Request $request, Leave $leave)
    {
        $validated = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $leave->isPending()) {
            return back()->with('error', __('This request has already been decided.'));
        }

        // Re-checked at approval: other requests may have been approved since
        // this one was recorded, so the balance is not what it was then.
        $this->assertWithinAllowance(
            $leave->leaveType,
            $leave->user_id,
            $leave->from_date->year,
            $leave->daysCount(),
            $leave->id
        );

        DB::transaction(function () use ($leave, $validated) {
            $leave->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);
        });

        AuditLog::log('approved', Leave::class, $leave->id, null, ['days' => $leave->daysCount()]);

        return back()->with('success', __('Leave approved.'));
    }

    public function reject(Request $request, Leave $leave)
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'max:500'],
        ]);

        if (! $leave->isPending()) {
            return back()->with('error', __('This request has already been decided.'));
        }

        $leave->update([
            'status' => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'],
        ]);

        AuditLog::log('rejected', Leave::class, $leave->id, null, null);

        return back()->with('success', __('Leave rejected.'));
    }

    public function destroy(Leave $leave)
    {
        if ($leave->attachment) {
            Storage::disk('public')->delete($leave->attachment);
        }

        $leave->delete();

        return back()->with('success', __('Leave request deleted.'));
    }

    /** Remaining allowance for a staff member and type, for the apply form. */
    public function remaining(Request $request)
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'year' => ['nullable', 'integer'],
        ]);

        $type = LeaveType::findOrFail($request->input('leave_type_id'));
        $year = (int) $request->input('year', now()->year);

        return response()->json([
            'limit' => $type->annual_limit,
            'used' => Leave::usedDaysForType((int) $request->input('user_id'), $type->id, $year),
            'remaining' => Leave::remainingForType($type, (int) $request->input('user_id'), $year),
        ]);
    }

    private function daysBetween(string $from, string $to): int
    {
        return (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
    }

    /**
     * Refuse a request that would take a staff member past their annual cap.
     *
     * Counts approved and pending together — otherwise two pending requests
     * that each fit could be approved one after the other and blow the limit.
     */
    private function assertWithinAllowance(?LeaveType $type, int $userId, int $year, int $days, ?int $excludeId = null): void
    {
        if (! $type || ! $type->isCapped()) {
            return;
        }

        $used = Leave::usedDaysForType($userId, $type->id, $year, $excludeId);

        if ($used + $days > $type->annual_limit) {
            throw ValidationException::withMessages([
                'leave_type_id' => __(
                    'That exceeds the :type allowance: :limit days a year, :used already committed, :days requested.',
                    ['type' => $type->title, 'limit' => $type->annual_limit, 'used' => $used, 'days' => $days]
                ),
            ]);
        }
    }
}
