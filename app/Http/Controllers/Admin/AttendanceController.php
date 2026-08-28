<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'attendance';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('attendance.view', ['index']),
            static::can('attendance.mark', ['store']),
            static::can('attendance.report', ['report']),
        ];
    }

    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $classSections = ClassSection::where('is_active', true)->whereHas('form', fn($q) => $q->forCurrentLevel())->with('form')->orderBy('name')->get();

        $date = $request->input('date', now()->format('Y-m-d'));
        $classSectionId = $request->input('class_section_id');

        $enrollments = collect();
        $existingAttendance = collect();
        $selectedClass = null;

        if ($classSectionId) {
            $selectedClass = ClassSection::with('form')->find($classSectionId);
            $enrollments = StudentEnrollment::where('class_section_id', $classSectionId)
                ->where('status', 'active')
                ->with('student')
                ->get()
                ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name);

            $existingAttendance = Attendance::whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->where('date', $date)
                ->get()
                ->keyBy('student_enrollment_id');
        }

        return view('admin.attendance.index', compact(
            'classSections', 'date', 'classSectionId', 'enrollments',
            'existingAttendance', 'selectedClass', 'currentSession'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_section_id' => 'required|exists:class_sections,id',
            'date' => 'required|date|before_or_equal:today',
            'attendance' => 'required|array',
            'attendance.*.status' => 'required|in:present,absent,late,excused',
            'attendance.*.reason' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('attendance') as $enrollmentId => $data) {
                Attendance::updateOrCreate(
                    [
                        'student_enrollment_id' => $enrollmentId,
                        'date' => $request->input('date'),
                    ],
                    [
                        'status' => $data['status'],
                        'reason' => $data['reason'] ?? null,
                        'check_in_time' => $data['status'] === 'late' ? ($data['check_in_time'] ?? null) : null,
                        'recorded_by' => auth()->id(),
                    ]
                );
            }
        });

        return redirect()
            ->route('admin.attendance.index', [
                'class_section_id' => $request->input('class_section_id'),
                'date' => $request->input('date'),
            ])
            ->with('success', 'Attendance saved successfully for ' . $request->input('date'));
    }

    public function report(Request $request)
    {
        $currentSession = AcademicSession::current();
        $classSections = ClassSection::where('is_active', true)->whereHas('form', fn($q) => $q->forCurrentLevel())->with('form')->orderBy('name')->get();

        $classSectionId = $request->input('class_section_id');
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));

        $reportData = collect();
        $summary = null;
        $selectedClass = null;

        if ($classSectionId) {
            $selectedClass = ClassSection::with('form')->find($classSectionId);

            $enrollments = StudentEnrollment::where('class_section_id', $classSectionId)
                ->where('status', 'active')
                ->with('student')
                ->get()
                ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name);

            $attendances = Attendance::whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->groupBy('student_enrollment_id');

            $totalDays = Attendance::whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->whereBetween('date', [$startDate, $endDate])
                ->distinct('date')
                ->count('date');

            $reportData = $enrollments->map(function ($enrollment) use ($attendances, $totalDays) {
                $records = $attendances->get($enrollment->id, collect());
                $present = $records->where('status', 'present')->count();
                $absent = $records->where('status', 'absent')->count();
                $late = $records->where('status', 'late')->count();
                $excused = $records->where('status', 'excused')->count();
                $marked = $records->count();

                return (object) [
                    'enrollment' => $enrollment,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'excused' => $excused,
                    'total_marked' => $marked,
                    'rate' => $marked > 0 ? round(($present + $late) / $marked * 100, 1) : 0,
                ];
            });

            $summary = (object) [
                'total_students' => $enrollments->count(),
                'total_days' => $totalDays,
                'avg_present' => $reportData->avg('present'),
                'avg_absent' => $reportData->avg('absent'),
                'avg_rate' => $reportData->avg('rate'),
            ];
        }

        return view('admin.attendance.report', compact(
            'classSections', 'classSectionId', 'startDate', 'endDate',
            'reportData', 'summary', 'selectedClass', 'currentSession'
        ));
    }
}
