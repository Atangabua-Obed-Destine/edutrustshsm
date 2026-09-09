<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\Term;
use App\Services\TermResultCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The paperwork a school is actually asked for: an acceptance letter at intake,
 * a leaving certificate on the way out, a cumulative record of everything in
 * between, and the class marksheet a staff meeting sits around.
 *
 * All four were missing. Report cards were the only document the system could
 * produce, which left the office generating the rest by hand.
 */
class StudentDocumentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student';

    public function __construct(private TermResultCalculator $calculator) {}

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('admission.view', ['acceptanceLetter']),
            static::can('student.view', ['leavingCertificate', 'cumulativeRecord']),
            static::can('report-card.view', ['marksheet', 'marksheetPdf', 'marksheetCsv']),
        ];
    }

    // ------------------------------------------------------ acceptance letter

    public function acceptanceLetter(AdmissionApplication $application)
    {
        // An offer letter for an application nobody has accepted would be the
        // school committing to a place it has not granted.
        if (! in_array($application->status, ['accepted', 'enrolled'], true)) {
            return back()->with('error', __('An acceptance letter can only be issued once the application has been accepted.'));
        }

        $application->load(['form', 'stream', 'academicSession']);

        return $this->pdf('admin.documents.acceptance-letter', [
            'application' => $application,
            'school' => SchoolSetting::current(),
        ], __('acceptance-letter').'-'.$application->application_number);
    }

    // --------------------------------------------------- leaving certificate

    public function leavingCertificate(Student $student)
    {
        if (in_array($student->status, ['active', 'suspended'], true)) {
            return back()->with('error', __('A leaving certificate can only be issued for a student who has left. This student is still :status.', [
                'status' => __(ucfirst($student->status)),
            ]));
        }

        $student->load(['enrollments.classSection.form', 'enrollments.academicSession']);

        $last = $student->enrollments->sortByDesc('enrollment_date')->first();

        // Whether the account is clear is the question every receiving school
        // asks, so the certificate answers it rather than making someone look
        // it up separately.
        $outstanding = (float) StudentFee::whereIn('student_enrollment_id', $student->enrollments->pluck('id'))
            ->sum('balance');

        return $this->pdf('admin.documents.leaving-certificate', [
            'student' => $student,
            'last' => $last,
            'outstanding' => $outstanding,
            'school' => SchoolSetting::current(),
        ], __('leaving-certificate').'-'.$student->student_id);
    }

    // ----------------------------------------------------- cumulative record

    public function cumulativeRecord(Student $student)
    {
        $student->load([
            'enrollments.classSection.form',
            'enrollments.academicSession',
            'enrollments.termResults.term',
        ]);

        // One row per term, newest year first, so the record reads as a history.
        $history = $student->enrollments
            ->sortBy([
                fn ($a, $b) => ($b->academicSession?->start_date <=> $a->academicSession?->start_date),
                fn ($a, $b) => ($a->term_id <=> $b->term_id),
            ])
            ->flatMap(fn ($enrollment) => $enrollment->termResults->map(fn ($result) => (object) [
                'session' => $enrollment->academicSession?->name,
                'term' => $result->term?->name,
                'class' => $enrollment->classSection?->name,
                'average' => $result->term_average,
                'rank' => $result->class_rank,
                'total' => $result->total_students,
                'grade' => $result->overall_grade,
                'conduct' => $result->conduct,
                'present' => $result->days_present,
                'absent' => $result->days_absent,
                'final_average' => $enrollment->final_average,
                'final_rank' => $enrollment->final_rank,
            ]))
            ->values();

        return $this->pdf('admin.documents.cumulative-record', [
            'student' => $student,
            'history' => $history,
            'school' => SchoolSetting::current(),
        ], __('cumulative-record').'-'.$student->student_id);
    }

    // ------------------------------------------------------- class marksheet

    public function marksheet(Request $request)
    {
        $data = $this->marksheetData($request);

        return view('admin.documents.marksheet', $data + [
            'sessions' => AcademicSession::orderByDesc('start_date')->get(),
            'forms' => Form::where('is_active', true)->orderBy('display_order')->get(),
            'terms' => Term::orderBy('term_number')->get(),
            'classSections' => $request->input('form_id')
                ? ClassSection::where('form_id', $request->input('form_id'))->where('is_active', true)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function marksheetPdf(Request $request)
    {
        $data = $this->marksheetData($request);

        if (! $data['section']) {
            return back()->with('error', __('Choose a class and term first.'));
        }

        // Landscape: a marksheet is as wide as the class has subjects.
        return $this->pdf('admin.documents.marksheet-print', $data + ['school' => SchoolSetting::current()],
            __('marksheet').'-'.$data['section']->name.'-'.$data['term']->name, 'landscape');
    }

    public function marksheetCsv(Request $request): StreamedResponse
    {
        $data = $this->marksheetData($request);

        abort_unless($data['section'] && $data['term'], 404);

        $filename = __('marksheet').'-'.$data['section']->name.'-'.$data['term']->name.'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            // Excel opens UTF-8 CSV as Latin-1 without this, which mangles every
            // accented name in a francophone school.
            fwrite($out, "\xEF\xBB\xBF");

            $header = [__('Rank'), __('Student ID'), __('Name')];

            foreach ($data['subjects'] as $subject) {
                $header[] = $subject->name.' (x'.$this->trim($subject->coefficient).')';
            }

            $header[] = __('Total');
            $header[] = __('Average');
            $header[] = __('Grade');
            fputcsv($out, $header);

            foreach ($data['rows'] as $row) {
                $line = [$row->rank, $row->student?->student_id, $row->student?->full_name];

                foreach ($data['subjects'] as $subject) {
                    $line[] = $row->by_subject[$subject->id]['term_average'] ?? '';
                }

                $line[] = $row->total_weighted;
                $line[] = $row->average;
                $line[] = $row->grade;
                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The grid behind all three marksheet outputs.
     *
     * @return array<string, mixed>
     */
    private function marksheetData(Request $request): array
    {
        $session = $request->input('academic_session_id')
            ? AcademicSession::find($request->input('academic_session_id'))
            : AcademicSession::current();

        $section = $request->input('class_section_id')
            ? ClassSection::with('form')->find($request->input('class_section_id'))
            : null;

        $term = $request->input('term_id') ? Term::find($request->input('term_id')) : null;

        if (! $session || ! $section || ! $term) {
            return ['session' => $session, 'section' => $section, 'term' => $term, 'subjects' => collect(), 'rows' => collect(), 'class' => null, 'passMark' => 10.0];
        }

        // Same calculation as the report card and the publishing preview — the
        // marksheet cannot disagree with what it is a summary of.
        $result = $this->calculator->compute($section, $term, $session->id);

        // Students are registered per subject, so the columns are the union
        // across the class, taken from the form's curriculum for a stable order.
        $subjects = DB::table('form_subject')
            ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->where('form_subject.form_id', $section->form_id)
            ->orderBy('subjects.name')
            ->distinct()
            ->get(['subjects.id', 'subjects.name', 'form_subject.coefficient']);

        $rows = $result['students']
            ->map(function ($student) {
                $student->by_subject = collect($student->subjects)->keyBy('subject_id')->all();

                return $student;
            })
            ->sortBy(fn ($s) => $s->rank ?? PHP_INT_MAX)
            ->values();

        return [
            'session' => $session,
            'section' => $section,
            'term' => $term,
            'subjects' => $subjects,
            'rows' => $rows,
            'class' => $result['class'],
            'passMark' => $result['pass_mark'],
        ];
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $data */
    private function pdf(string $view, array $data, string $filename, string $orientation = 'portrait')
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', $orientation)
            ->stream($this->slug($filename).'.pdf');
    }

    private function slug(string $value): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^A-Za-z0-9]+/', '-', $value)), '-') ?: 'document';
    }

    private function trim(float|string|null $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
