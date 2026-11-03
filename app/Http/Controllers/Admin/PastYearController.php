<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\RecordedSheet;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\TermResult;
use App\Services\PastYearRecorder;
use App\Services\RecordedResults;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recording a school year that happened before the system: where each pupil
 * was, their results as proclaimed at the time, and what was left unpaid.
 */
class PastYearController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'past-year';

    protected static function extraMiddleware(): array
    {
        return [
            static::can('past-year.view', ['index', 'show', 'sheet', 'evidence', 'record']),
            static::can('past-year.record', ['store', 'placement', 'place', 'template', 'upload', 'attachEvidence', 'arrearsTemplate', 'arrears']),
            static::can('past-year.verify', ['verify', 'reopen']),
        ];
    }

    public function __construct(private PastYearRecorder $years, private RecordedResults $results)
    {
    }

    public function index()
    {
        $years = AcademicSession::where('is_recorded', true)->orderByDesc('start_date')->get();
        $current = AcademicSession::current();

        return view('admin.past-years.index', compact('years', 'current'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
        ]);

        try {
            $year = $this->years->createYear($data['name'], $data['start_date'], $data['end_date']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.past-years.show', $year)->with('success', __('Year :name recorded. Now say where each pupil was.', ['name' => $year->name]));
    }

    public function show(AcademicSession $year)
    {
        $this->years->assertRecordedOr404($year);

        $current = AcademicSession::current();
        $nowSections = ClassSection::with('form')->where('is_active', true)
            ->whereHas('form', fn ($f) => $f->forCurrentLevel())->get()
            ->sortBy(fn ($s) => [$s->form->display_order, $s->name])->values();

        $pupilsNow = StudentEnrollment::where('academic_session_id', $current?->id)->where('status', 'active')
            ->get(['student_id', 'class_section_id'])->unique('student_id')->groupBy('class_section_id');
        $placed = StudentEnrollment::where('academic_session_id', $year->id)->distinct()->pluck('student_id')->flip();

        $placement = $nowSections->map(fn ($s) => [
            'section' => $s,
            'pupils' => ($pupilsNow[$s->id] ?? collect())->count(),
            'placed' => ($pupilsNow[$s->id] ?? collect())->filter(fn ($e) => $placed->has($e->student_id))->count(),
        ])->filter(fn ($row) => $row['pupils'] > 0)->values();

        // Last year's classes, as they had pupils, with each term's sheet.
        $lastYearSections = ClassSection::with('form')->whereIn('id', StudentEnrollment::where('academic_session_id', $year->id)->distinct()->pluck('class_section_id'))
            ->get()->sortBy(fn ($s) => [$s->form->display_order, $s->name])->values();
        $terms = $this->years->terms();
        $sheets = RecordedSheet::where('academic_session_id', $year->id)->get()->keyBy(fn ($s) => $s->class_section_id.':'.$s->term_id);
        $counts = StudentEnrollment::where('academic_session_id', $year->id)->selectRaw('class_section_id, COUNT(DISTINCT student_id) as n')
            ->groupBy('class_section_id')->pluck('n', 'class_section_id');

        $arrears = StudentFee::whereHas('enrollment', fn ($q) => $q->where('academic_session_id', $year->id));
        $arrearsSummary = ['pupils' => (clone $arrears)->distinct()->count('student_enrollment_id'), 'owed' => (float) (clone $arrears)->sum('balance')];

        return view('admin.past-years.show', compact('year', 'placement', 'lastYearSections', 'terms', 'sheets', 'counts', 'arrearsSummary'));
    }

    public function placement(AcademicSession $year, ClassSection $section)
    {
        $this->years->assertRecordedOr404($year);
        $rows = $this->years->placements($year, $section);
        $options = ClassSection::with('form')->where('is_active', true)
            ->whereHas('form', fn ($f) => $f->where('school_level', $section->form->school_level))->get()
            ->sortBy(fn ($s) => [$s->form->display_order, $s->name])->values();

        return view('admin.past-years.placement', compact('year', 'section', 'rows', 'options'));
    }

    public function place(Request $request, AcademicSession $year, ClassSection $section)
    {
        $this->years->assertRecordedOr404($year);
        $data = $request->validate([
            'placement' => ['array'],
            'placement.*' => ['nullable', 'integer', 'exists:class_sections,id'],
        ]);

        $rows = $this->years->placements($year, $section)->keyBy(fn ($r) => $r['student']->id);
        $done = 0;
        $refused = [];
        foreach ($data['placement'] ?? [] as $studentId => $sectionId) {
            $row = $rows->get((int) $studentId);
            if (! $row) {
                continue;
            }
            try {
                $this->years->place($year, $row['student'], $sectionId ? (int) $sectionId : null, $row['residence']);
                $done++;
            } catch (RuntimeException $e) {
                $refused[] = $e->getMessage();
            }
        }

        $back = redirect()->route('admin.past-years.show', $year)->with('success', __('Where the pupils of :class were in :year is saved.', ['class' => $section->name, 'year' => $year->name]));

        return $refused ? $back->with('error', implode(' ', $refused)) : $back;
    }

    public function sheet(AcademicSession $year, ClassSection $section, Term $term)
    {
        $this->years->assertRecordedOr404($year);
        $sheet = $this->results->sheet($year, $section, $term)->load(['enteredBy', 'verifiedBy', 'term', 'classSection', 'academicSession']);
        $subjects = $this->results->subjects($section);
        $enrollments = $this->results->enrollments($sheet);
        $results = TermResult::with('subjectResults')->whereIn('student_enrollment_id', $enrollments->pluck('id'))
            ->where('term_id', $term->id)->get()->keyBy('student_enrollment_id');
        $discrepancies = $this->results->discrepancies($sheet);
        $isLastTerm = $this->results->isLastTerm($term);

        return view('admin.past-years.sheet', compact('year', 'section', 'term', 'sheet', 'subjects', 'enrollments', 'results', 'discrepancies', 'isLastTerm'));
    }

    public function template(AcademicSession $year, ClassSection $section, Term $term): StreamedResponse
    {
        $this->years->assertRecordedOr404($year);
        [$headers, $rows] = $this->results->template($this->results->sheet($year, $section, $term));

        return $this->csv($headers, $rows, str($year->name.'-'.$section->name.'-'.$term->name)->slug().'.csv');
    }

    public function upload(Request $request, AcademicSession $year, ClassSection $section, Term $term)
    {
        $this->years->assertRecordedOr404($year);
        $data = $request->validate([
            'csv_file' => ['required', 'file', 'max:5120'],
            'out_of' => ['required', 'numeric', 'min:1', 'max:1000'],
        ]);

        try {
            $outcome = $this->results->import($this->results->sheet($year, $section, $term), $request->file('csv_file')->getRealPath(), (float) $data['out_of']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $back = back()->with('upload_errors', $outcome['errors'])->with('upload_warnings', $outcome['warnings']);

        return $outcome['errors']
            ? $back->with('error', __('Nothing was recorded: correct the lines below and send the file again.'))
            : $back->with('success', trans_choice('{1} 1 pupil recorded.|[2,*] :count pupils recorded.', $outcome['saved'], ['count' => $outcome['saved']]));
    }

    public function attachEvidence(Request $request, AcademicSession $year, ClassSection $section, Term $term)
    {
        $this->years->assertRecordedOr404($year);
        $request->validate(['evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']]);
        $sheet = $this->results->sheet($year, $section, $term);
        if ($sheet->isVerified()) {
            return back()->with('error', __('This sheet is verified and locked. The head teacher can reopen it with a reason.'));
        }
        $sheet->update(['evidence_path' => $request->file('evidence')->store('recorded-sheets', 'local')]);

        return back()->with('success', __('Scan of the paper sheet attached.'));
    }

    public function evidence(AcademicSession $year, ClassSection $section, Term $term)
    {
        $sheet = $this->results->sheet($year, $section, $term);
        abort_unless($sheet->evidence_path && Storage::disk('local')->exists($sheet->evidence_path), 404);

        return Storage::disk('local')->response($sheet->evidence_path);
    }

    public function verify(AcademicSession $year, ClassSection $section, Term $term)
    {
        try {
            $this->results->verify($this->results->sheet($year, $section, $term));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Verified and locked. These results now show in each pupil\'s history and to their parents.'));
    }

    public function reopen(Request $request, AcademicSession $year, ClassSection $section, Term $term)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->results->reopen($this->results->sheet($year, $section, $term), $data['reason']);

        return back()->with('success', __('Reopened for correction. Parents no longer see it until it is verified again.'));
    }

    public function arrearsTemplate(AcademicSession $year): StreamedResponse
    {
        $this->years->assertRecordedOr404($year);
        $rows = StudentEnrollment::with('student')->where('academic_session_id', $year->id)->get()->unique('student_id')
            ->sortBy(fn ($e) => [$e->student?->last_name])->map(fn ($e) => [$e->student?->student_id, trim($e->student?->last_name.' '.$e->student?->first_name), '', ''])->values()->all();

        return $this->csv(['matricule', 'name', 'category', 'amount'], $rows, 'unpaid-'.str($year->name)->slug().'.csv');
    }

    public function arrears(Request $request, AcademicSession $year)
    {
        $this->years->assertRecordedOr404($year);
        $request->validate(['csv_file' => ['required', 'file', 'max:5120']]);

        try {
            $outcome = $this->years->importArrears($year, $request->file('csv_file')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('upload_errors', $outcome['errors'])
            ->with('success', trans_choice('{0} No unpaid fees were recorded.|{1} 1 unpaid fee recorded.|[2,*] :count unpaid fees recorded.', $outcome['saved'], ['count' => $outcome['saved']]));
    }

    /** One pupil's recorded year, printable: the school's record of results. */
    public function record(AcademicSession $year, Student $student)
    {
        $this->years->assertRecordedOr404($year);

        return view('admin.past-years.record', $this->results->recordFor($year, $student, publishedOnly: false));
    }

    private function csv(array $headers, array $rows, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "sep=,\n");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
