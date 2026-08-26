<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\ParentContext;
use Illuminate\Support\Carbon;

class ParentAttendanceController extends Controller
{
    public function index(int $student)
    {
        $studentModel = ParentContext::authorizeStudent($student);
        $enrollment = $studentModel->currentEnrollment;

        $summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
        $rate = 0;
        $monthly = collect();
        $recent = collect();

        if ($enrollment) {
            $records = Attendance::where('student_enrollment_id', $enrollment->id)->get();

            $summary['present'] = $records->where('status', 'present')->count();
            $summary['absent']  = $records->where('status', 'absent')->count();
            $summary['late']    = $records->where('status', 'late')->count();
            $summary['excused'] = $records->where('status', 'excused')->count();
            $summary['total']   = $records->count();

            // Attendance rate counts present + late as "in attendance".
            $inAttendance = $summary['present'] + $summary['late'];
            $rate = $summary['total'] > 0 ? round(($inAttendance / $summary['total']) * 100, 1) : 0;

            // Monthly breakdown (last 6 months with data).
            $monthly = $records
                ->groupBy(fn ($r) => Carbon::parse($r->date)->format('Y-m'))
                ->map(function ($group, $key) {
                    return (object) [
                        'label'   => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                        'present' => $group->where('status', 'present')->count(),
                        'absent'  => $group->where('status', 'absent')->count(),
                        'late'    => $group->where('status', 'late')->count(),
                    ];
                })
                ->sortKeysDesc()
                ->take(6)
                ->values();

            $recent = $records->sortByDesc('date')->take(15)->values();
        }

        return view('parent.attendance.index', compact(
            'studentModel', 'enrollment', 'summary', 'rate', 'monthly', 'recent'
        ));
    }
}
