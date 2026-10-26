<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\StudentExitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Pupils leaving, and the roll of those who have left.
 *
 * Recording an exit is gated separately from editing a pupil: it takes them
 * off every class list and fee report in the school, which is not the same
 * act as correcting a spelling.
 */
class StudentExitController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student-exit';

    public function __construct(private StudentExitService $exits) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('student-exit.readmit', ['readmit']),
            // Reading the certificate is reading the record.
            static::can('student-exit.view', ['certificate']),
        ];
    }

    /** The archive: everyone who has left. */
    public function index(Request $request)
    {
        $query = Student::with(['guardian', 'enrollments.classSection.form'])
            ->whereIn('status', [
                StudentExitService::GRADUATED,
                StudentExitService::WITHDRAWN,
                StudentExitService::EXPELLED,
            ]);

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('student_id', 'like', "%{$search}%")
                ->orWhere('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($year = $request->input('year')) {
            $query->whereYear('withdrawal_date', $year);
        }

        return view('admin.students.archive', [
            'students' => $query->orderByDesc('withdrawal_date')->orderBy('last_name')->paginate(25)->withQueryString(),
            // Grouped in PHP rather than with YEAR(), which is MySQL's and
            // would fail on any other engine.
            'years' => Student::whereNotNull('withdrawal_date')
                ->pluck('withdrawal_date')
                ->map(fn ($date) => (int) $date->format('Y'))
                ->unique()->sortDesc()->values(),
            'filters' => $request->only(['search', 'status', 'year']),
        ]);
    }

    /** The form for recording that one pupil has left. */
    public function create(Student $student)
    {
        abort_if($this->exits->hasLeft($student), 404);

        return view('admin.students.exit', [
            'student' => $student->load('currentEnrollment.classSection.form'),
            'types' => StudentExitService::types(),
            'balance' => $this->exits->outstandingBalance($student),
        ]);
    }

    public function store(Request $request, Student $student)
    {
        abort_if($this->exits->hasLeft($student), 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(StudentExitService::types()))],
            'left_on' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:500'],
            'destination_school' => ['nullable', 'required_if:type,transferred', 'string', 'max:200'],
        ], [
            'destination_school.required_if' => __('Name the school the pupil is transferring to.'),
            'left_on.before_or_equal' => __('A pupil cannot have left on a future date.'),
        ]);

        $this->exits->record(
            $student,
            $validated['type'],
            $validated['left_on'],
            $validated['reason'] ?? null,
            $validated['destination_school'] ?? null
        );

        return redirect()->route('admin.students.show', $student)
            ->with('success', __(':name has been taken off the roll. They no longer count in class lists or fee reports.', [
                'name' => $student->first_name.' '.$student->last_name,
            ]));
    }

    public function readmit(Student $student)
    {
        abort_unless($this->exits->hasLeft($student), 404);

        $this->exits->readmit($student);

        return redirect()->route('admin.students.show', $student)
            ->with('success', __(':name is active again. Enrol them into a class to put them back on a register.', [
                'name' => $student->first_name.' '.$student->last_name,
            ]));
    }

    /** The certificate a pupil leaves with. */
    public function certificate(Student $student)
    {
        abort_unless($this->exits->hasLeft($student), 404);

        $student->load(['guardian', 'enrollments.academicSession', 'enrollments.classSection.form']);

        return Pdf::loadView('admin.students.leaving-certificate', [
            'student' => $student,
            'school' => SchoolSetting::current(),
            'last' => $student->enrollments
                ->sortByDesc(fn ($e) => $e->academicSession?->start_date ?? $e->enrollment_date)
                ->first(),
        ])->setPaper('a4')->download('leaving-certificate-'.str($student->student_id)->slug().'.pdf');
    }
}
