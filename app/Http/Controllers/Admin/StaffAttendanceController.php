<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Leave;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily staff attendance register.
 *
 * A record of presence, not a payroll input — see StaffAttendance. The hourly
 * / class-tied variant the reference system carries is deliberately not built:
 * it exists there for lecturers paid by contact hour, which does not apply to
 * salaried secondary-school staff.
 */
class StaffAttendanceController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'staff-attendance';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('staff-attendance.view', ['index', 'report']),
            static::can('staff-attendance.mark', ['store']),
        ];
    }

    /** The register for one day, pre-filled with anything already recorded. */
    public function index(Request $request)
    {
        $date = Carbon::parse($request->input('date', now()->toDateString()));

        $staff = User::staff()
            ->where('is_active', true)
            ->when($request->input('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'staff_id', 'department_id']);

        $existing = StaffAttendance::whereDate('date', $date)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->keyBy('user_id');

        // Anyone on approved leave that day is proposed as 'leave' rather than
        // making a clerk mark it by hand and risk an "absent" against them.
        $onLeave = Leave::where('status', 'approved')
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->pluck('user_id')
            ->flip();

        return view('admin.hr.attendance.index', [
            'date' => $date,
            'staff' => $staff,
            'existing' => $existing,
            'onLeave' => $onLeave,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'recorded' => $existing->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date'],
            'attendance' => ['required', 'array', 'min:1'],
            // Keys must be real staff ids: without this a crafted POST could
            // write attendance for arbitrary users.
            'attendance.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendance.*.status' => ['required', 'in:present,absent,late,leave,holiday'],
            'attendance.*.check_in' => ['nullable', 'date_format:H:i'],
            'attendance.*.check_out' => ['nullable', 'date_format:H:i', 'after:attendance.*.check_in'],
            'attendance.*.note' => ['nullable', 'string', 'max:255'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        DB::transaction(function () use ($validated, $date) {
            foreach ($validated['attendance'] as $row) {
                $attributes = [
                    'status' => $row['status'],
                    'check_in' => $row['check_in'] ?? null,
                    'check_out' => $row['check_out'] ?? null,
                    'note' => $row['note'] ?? null,
                    'recorded_by' => auth()->id(),
                ];

                // Looked up with whereDate rather than updateOrCreate: the date
                // cast writes a datetime, so an equality match on a date string
                // misses on some drivers and the insert then trips the
                // (user, date) unique index instead of updating.
                $existing = StaffAttendance::where('user_id', $row['user_id'])
                    ->whereDate('date', $date)
                    ->first();

                $existing
                    ? $existing->update($attributes)
                    : StaffAttendance::create($attributes + [
                        'user_id' => $row['user_id'],
                        'date' => $date,
                    ]);
            }
        });

        return back()->with('success', trans_choice(
            'Attendance saved for :count staff member.|Attendance saved for :count staff members.',
            count($validated['attendance']),
            ['count' => count($validated['attendance'])]
        ));
    }

    /** Per-staff monthly summary. */
    public function report(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $staff = User::staff()->where('is_active', true)
            ->when($request->input('department_id'), fn ($q, $v) => $q->where('department_id', $v))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'staff_id']);

        $records = StaffAttendance::forMonth($year, $month)
            ->whereIn('user_id', $staff->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $rows = $staff->map(function (User $person) use ($records) {
            $theirs = $records->get($person->id, collect());
            $counts = $theirs->countBy('status');

            $present = ($counts['present'] ?? 0) + ($counts['late'] ?? 0);
            $absent = $counts['absent'] ?? 0;

            // Rate is over days actually WORKABLE — leave and holidays are not
            // failures to attend, so they are excluded from the denominator.
            $workable = $present + $absent;

            return (object) [
                'staff' => $person,
                'present' => $counts['present'] ?? 0,
                'late' => $counts['late'] ?? 0,
                'absent' => $absent,
                'leave' => $counts['leave'] ?? 0,
                'holiday' => $counts['holiday'] ?? 0,
                'recorded' => $theirs->count(),
                'rate' => $workable > 0 ? round($present / $workable * 100, 1) : null,
            ];
        });

        return view('admin.hr.attendance.report', [
            'rows' => $rows,
            'year' => $year,
            'month' => $month,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
