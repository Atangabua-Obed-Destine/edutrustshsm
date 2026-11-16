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

        $list = $this->archiveList($request)->filters([
            __('Search') => $search ?: null,
            __('How they left') => $status ? __(ucfirst((string) $status)) : null,
            __('Year') => $year ?: null,
        ]);

        if ($download = $list->export($query)) {
            return $download;
        }

        return view('admin.students.archive', [
            'students' => $list->paginate($query),
            'list' => $list,
            // Grouped in PHP rather than with YEAR(), which is MySQL's and
            // would fail on any other engine.
            'years' => Student::whereNotNull('withdrawal_date')
                ->pluck('withdrawal_date')
                ->map(fn ($date) => (int) $date->format('Y'))
                ->unique()->sortDesc()->values(),
            'filters' => $request->only(['search', 'status', 'year']),
        ]);
    }

    /** The former pupils list's columns: what sorts, and what an export writes. */
    private function archiveList(Request $request): \App\Support\Listing
    {
        // The class of the last enrolment, as a value the list can sort on.
        $lastClass = fn () => \App\Models\StudentEnrollment::query()
            ->join('class_sections', 'class_sections.id', '=', 'student_enrollments.class_section_id')
            ->whereColumn('student_enrollments.student_id', 'students.id')
            ->orderByDesc('student_enrollments.enrollment_date')
            ->select('class_sections.name')
            ->limit(1);

        $last = fn (Student $s) => $s->enrollments->sortByDesc('enrollment_date')->first();

        return new \App\Support\Listing($request, 'former-pupils', __('Former Pupils'), [
            'student_id' => ['label' => __('Student ID'), 'sort' => 'students.student_id', 'value' => fn ($s) => $s->student_id],
            'name' => ['label' => __('Pupil'), 'sort' => ['students.last_name', 'students.first_name'],
                'value' => fn ($s) => trim($s->first_name.' '.$s->last_name)],
            'class' => ['label' => __('Last class'), 'sort' => fn ($q, $dir) => $q->orderBy($lastClass(), $dir)->orderBy('students.last_name'),
                'value' => fn ($s) => $last($s)?->classSection?->name],
            'status' => ['label' => __('How they left'), 'sort' => 'students.status',
                'value' => fn ($s) => $s->status === 'withdrawn' && $s->destination_school ? __('Transferred') : __(ucfirst((string) $s->status))],
            'destination' => ['label' => __('Destination school'), 'value' => fn ($s) => $s->destination_school],
            'last_day' => ['label' => __('Last day'),
                'sort' => fn ($q, $dir) => $q->orderBy('students.withdrawal_date', $dir)->orderBy('students.last_name'),
                'value' => fn ($s) => $s->withdrawal_date ? \Illuminate\Support\Carbon::parse($s->withdrawal_date) : null],
            'note' => ['label' => __('Note'), 'value' => fn ($s) => $s->withdrawal_reason],
        ], default: 'last_day', defaultDir: 'desc');
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
        $last = $student->enrollments
            ->sortByDesc(fn ($e) => $e->academicSession?->start_date ?? $e->enrollment_date)
            ->first();

        // Every copy printed goes in the register with its own serial.
        $issued = \App\Models\IssuedDocument::issue(\App\Models\IssuedDocument::LEAVING_CERTIFICATE, $student, [
            __('Pupil') => $student->first_name.' '.$student->last_name,
            __('Matricule') => $student->student_id,
            __('Date of birth') => $student->date_of_birth?->format('d/m/Y'),
            __('Last class') => $last?->classSection?->name,
            __('Academic Year') => $last?->academicSession?->name,
            __('Date left') => $student->withdrawal_date?->format('d/m/Y'),
        ]);

        return Pdf::loadView('admin.students.leaving-certificate', [
            'student' => $student,
            'school' => SchoolSetting::current(),
            'last' => $last,
            'issued' => $issued,
        ])->setPaper('a4')->download('leaving-certificate-'.str($student->student_id)->slug().'.pdf');
    }
}
