<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Term;
use App\Models\TermResult;
use App\Services\TermResultCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ReportCardController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'report-card';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('report-card.view', ['index', 'sectionsByForm', 'show', 'generate', 'publish']),
            static::can('report-card.print', ['bulkDownload']),
        ];
    }

    /**
     * Selection screen: pick session + form + section + term, view generated report cards.
     */
    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $terms = Term::orderBy('term_number')->get();

        $sessionId = $request->input('academic_session_id', $currentSession?->id);
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');
        $termId = $request->input('term_id');

        // Load sections for selected form (for re-rendering after submit)
        $classSections = collect();
        if ($formId) {
            $classSections = ClassSection::where('form_id', $formId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $results = collect();
        $selectedClass = null;
        $selectedTerm = null;

        if ($classSectionId && $termId) {
            $selectedClass = ClassSection::with('form', 'classTeacher')->find($classSectionId);
            $selectedTerm = Term::find($termId);

            $results = TermResult::where('term_id', $termId)
                ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $classSectionId)
                    ->where('academic_session_id', $sessionId)
                    ->where('term_id', $termId))
                ->with(['enrollment.student', 'enrollment.classSection', 'subjectResults.subject'])
                ->orderBy('class_rank')
                ->get();
        }

        return view('admin.report-cards.index', compact(
            'sessions', 'forms', 'terms', 'classSections',
            'sessionId', 'formId', 'classSectionId', 'termId',
            'results', 'selectedClass', 'selectedTerm', 'currentSession'
        ));
    }

    /**
     * AJAX: Get sections for a form.
     */
    public function sectionsByForm(Form $form)
    {
        return response()->json(
            ClassSection::where('form_id', $form->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /**
     * Generate/recalculate report cards for a class + term.
     */
    /**
     * Generate/recalculate report cards for a class + term.
     *
     * The arithmetic lives in TermResultCalculator, shared with the exam
     * publishing preview, so what a teacher approves on that screen is by
     * construction what lands here.
     */
    public function generate(Request $request, TermResultCalculator $calculator)
    {
        $validated = $request->validate([
            'class_section_id' => 'required|exists:class_sections,id',
            'term_id' => 'required|exists:terms,id',
            'form_id' => 'required|exists:forms,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
        ]);

        $classSection = ClassSection::with('form')->findOrFail($validated['class_section_id']);
        $term = Term::findOrFail($validated['term_id']);

        if ($calculator->sequencesFor($classSection->form_id, $term->id)->isEmpty()) {
            return back()->with('error', __('No sequences are mapped to this form and term.'));
        }

        $processed = $calculator->persist($classSection, $term, (int) $validated['academic_session_id']);

        if ($processed === 0) {
            return back()->with('error', __('No students with approved marks were found for this class and term.'));
        }

        return redirect()->route('admin.report-cards.index', [
            'academic_session_id' => $validated['academic_session_id'],
            'form_id' => $validated['form_id'],
            'class_section_id' => $validated['class_section_id'],
            'term_id' => $validated['term_id'],
        ])->with('success', trans_choice(
            'Report cards generated. :count student processed.|Report cards generated. :count students processed.',
            $processed,
            ['count' => $processed]
        ));
    }

    /**
     * Show a single student's report card (printable).
     */
    public function show(TermResult $termResult)
    {
        $termResult->load([
            'enrollment.student',
            'enrollment.classSection.form',
            'enrollment.classSection.classTeacher',
            'term',
            'subjectResults.subject',
        ]);

        $school = SchoolSetting::current();
        $currentSession = AcademicSession::current();

        // Load the actual sequences configured for this form + term via form_sequence pivot
        $formId = $termResult->enrollment->classSection->form_id;
        $termId = $termResult->term_id;
        $seqIds = DB::table('form_sequence')
            ->where('form_id', $formId)
            ->where('term_id', $termId)
            ->distinct()
            ->pluck('sequence_id');
        $sequences = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get();

        return view('admin.report-cards.show', compact('termResult', 'school', 'currentSession', 'sequences'));
    }

    /**
     * Publish / unpublish report cards for a class+term.
     */
    public function publish(Request $request)
    {
        $request->validate([
            'class_section_id' => 'required|exists:class_sections,id',
            'term_id' => 'required|exists:terms,id',
        ]);

        $count = TermResult::where('term_id', $request->term_id)
            ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $request->class_section_id))
            ->update(['is_published' => true]);

        return back()->with('success', $count . ' report card(s) published.');
    }

    /**
     * Bulk download selected report cards as a ZIP of HTML files.
     */
    public function bulkDownload(Request $request)
    {
        $request->validate([
            'term_result_ids' => 'required|array|min:1',
            'term_result_ids.*' => 'integer|exists:term_results,id',
        ]);

        $termResults = TermResult::with([
                'enrollment.student',
                'enrollment.classSection.form',
                'enrollment.classSection.classTeacher',
                'term',
                'subjectResults.subject',
            ])
            ->whereIn('id', $request->term_result_ids)
            ->get();

        if ($termResults->isEmpty()) {
            return back()->with('error', __('No valid report cards selected.'));
        }

        $school = SchoolSetting::current();
        $currentSession = AcademicSession::current();

        // Build a unique temp ZIP path
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $first = $termResults->first();
        $className = $first->enrollment->classSection->name ?? 'class';
        $termName = $first->term->name ?? 'term';
        $zipBaseName = 'report-cards_' . $this->slugify($className) . '_' . $this->slugify($termName) . '_' . now()->format('Ymd_His');
        $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $zipBaseName . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', __('Could not create ZIP archive.'));
        }

        // Cache sequences per (form, term) pair to avoid repeated DB hits
        $sequenceCache = [];
        $usedFilenames = [];

        foreach ($termResults as $termResult) {
            $formId = $termResult->enrollment->classSection->form_id;
            $termId = $termResult->term_id;
            $cacheKey = $formId . '_' . $termId;

            if (!isset($sequenceCache[$cacheKey])) {
                $seqIds = DB::table('form_sequence')
                    ->where('form_id', $formId)
                    ->where('term_id', $termId)
                    ->distinct()
                    ->pluck('sequence_id');
                $sequenceCache[$cacheKey] = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get();
            }
            $sequences = $sequenceCache[$cacheKey];

            // Render the show view to a string
            $html = view('admin.report-cards.show', [
                'termResult' => $termResult,
                'school' => $school,
                'currentSession' => $currentSession,
                'sequences' => $sequences,
                'pdfMode' => true,
            ])->render();

            // Build a safe, unique file name per student
            $student = $termResult->enrollment->student;
            $studentLabel = trim(($student->student_id ?? '') . '_' . $student->last_name . '_' . $student->first_name);
            $base = $this->slugify($studentLabel) ?: ('student_' . $termResult->id);
            $filename = $base . '.pdf';
            $i = 2;
            while (isset($usedFilenames[$filename])) {
                $filename = $base . '_' . $i++ . '.pdf';
            }
            $usedFilenames[$filename] = true;

            // Render to PDF
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        return response()->download($zipPath, $zipBaseName . '.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Slugify a string for safe filenames.
     */
    private function slugify(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9\-_ ]/', '', $value);
        $value = preg_replace('/\s+/', '-', trim($value));
        return strtolower($value ?: '');
    }
}
