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
use App\Services\NurseryAssessmentService;
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
            static::can('report-card.view', ['index', 'sectionsByForm', 'show', 'generate', 'publish', 'remarks', 'saveRemarks']),
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
        $scope = app(\App\Services\TeachingScope::class);
        $forms = $scope->scopeForms(Form::active()->forCurrentLevel(), auth()->user())->ordered()->get();
        $terms = Term::orderBy('term_number')->get();

        $sessionId = $request->input('academic_session_id', $currentSession?->id);
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');
        $termId = $request->input('term_id');

        // Load sections for selected form (for re-rendering after submit)
        $classSections = collect();
        if ($classSectionId) {
            abort_unless($scope->canUseSection(auth()->user(), (int) $classSectionId), 403);
        }

        if ($formId) {
            $classSections = $scope->scopeSections(ClassSection::where('form_id', $formId), auth()->user())
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
        abort_unless(app(\App\Services\TeachingScope::class)->canUseSection(auth()->user(), (int) $termResult->enrollment?->class_section_id), 403);

        $termResult->load([
            'enrollment.student',
            'enrollment.classSection.form',
            'enrollment.classSection.classTeacher',
            'term',
            'subjectResults.subject',
        ]);

        $school = SchoolSetting::current();
        $currentSession = AcademicSession::current();

        // A nursery pupil's card is a competence grid, not a marks table.
        if (NurseryAssessmentService::isNursery($termResult->enrollment->classSection->form)) {
            $card = app(NurseryAssessmentService::class)->card($termResult);

            return view('admin.report-cards.nursery', compact('termResult', 'school', 'currentSession', 'card'));
        }

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

        // A subject whose marks are not approved simply drops out of every
        // pupil's average, and the card still looks finished. Say which ones
        // before anything reaches a parent; publishing anyway is a choice.
        if (! $request->boolean('publish_anyway')) {
            $missing = $this->unapprovedSubjects((int) $request->class_section_id, (int) $request->term_id);
            if ($missing !== []) {
                return back()->with('incomplete', $missing)->with('error', __('Not published: marks for :list are not approved for this term yet, so they are missing from every average.', ['list' => implode(', ', $missing)]));
            }
        }

        $count = TermResult::where('term_id', $request->term_id)
            ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $request->class_section_id)
                ->where('academic_session_id', $request->integer('academic_session_id') ?: AcademicSession::current()?->id))
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
            $html = NurseryAssessmentService::isNursery($termResult->enrollment->classSection->form)
                ? view('admin.report-cards.nursery', [
                    'termResult' => $termResult,
                    'school' => $school,
                    'currentSession' => $currentSession,
                    'card' => app(NurseryAssessmentService::class)->card($termResult),
                    'pdfMode' => true,
                ])->render()
                : view('admin.report-cards.show', [
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
    /**
     * The remark boxes of every card in a class: the class teacher's remark
     * and conduct, and the head's remark. Nothing wrote them before, so every
     * card printed blank lines. A term result row is created for each pupil
     * if the cards are not generated yet; generating later keeps the remarks.
     */
    public function remarks(Request $request)
    {
        [$section, $term, $session] = $this->remarksContext($request);
        $scope = app(\App\Services\TeachingScope::class);

        $enrollments = \App\Models\StudentEnrollment::with('student')
            ->where('class_section_id', $section->id)->where('academic_session_id', $session->id)
            ->where('status', 'active')->get()
            ->sortBy(fn ($e) => $e->student->last_name.' '.$e->student->first_name)->values();

        $results = TermResult::whereIn('student_enrollment_id', $enrollments->pluck('id'))->where('term_id', $term->id)->get()->keyBy('student_enrollment_id');

        return view('admin.report-cards.remarks', [
            'section' => $section, 'term' => $term, 'session' => $session,
            'rows' => $enrollments->map(fn ($e) => ['enrollment' => $e, 'result' => $results->get($e->id)]),
            'canClassTeacher' => ! $scope->restricted(auth()->user()) || in_array($section->id, $scope->classTeacherOf(auth()->user()), true),
            'canHead' => ! $scope->restricted(auth()->user()),
            'passMark' => (float) (SchoolSetting::current()?->pass_mark ?? 10),
            'suggestions' => [
                __('Excellent work, keep it up.'), __('Very good work.'), __('Good work, can do better.'),
                __('Fair, needs to work harder.'), __('Weak, must work much harder.'),
                __('Promoted.'), __('Promoted with encouragement.'), __('Must repeat the class.'),
            ],
        ]);
    }

    public function saveRemarks(Request $request)
    {
        [$section, $term, $session] = $this->remarksContext($request);
        $scope = app(\App\Services\TeachingScope::class);
        $user = auth()->user();
        $isHead = ! $scope->restricted($user);
        $isClassTeacher = $isHead || in_array($section->id, $scope->classTeacherOf($user), true);
        abort_unless($isClassTeacher, 403, __('Only the class teacher of :class writes its remarks.', ['class' => $section->name]));

        $data = $request->validate([
            'remarks' => ['array'],
            'remarks.*.class_teacher_remark' => ['nullable', 'string', 'max:500'],
            'remarks.*.conduct' => ['nullable', 'in:excellent,very_good,good,fair,poor'],
            'remarks.*.principal_remark' => ['nullable', 'string', 'max:500'],
        ]);

        $enrollmentIds = \App\Models\StudentEnrollment::where('class_section_id', $section->id)
            ->where('academic_session_id', $session->id)->pluck('id')->flip();

        $saved = 0;
        DB::transaction(function () use ($data, $enrollmentIds, $term, $isHead, &$saved) {
            foreach ($data['remarks'] ?? [] as $enrollmentId => $row) {
                if (! $enrollmentIds->has((int) $enrollmentId)) {
                    continue;
                }
                $result = TermResult::firstOrNew(['student_enrollment_id' => (int) $enrollmentId, 'term_id' => $term->id]);
                $result->class_teacher_remark = $row['class_teacher_remark'] ?? null;
                $result->conduct = $row['conduct'] ?? null;
                // Only the head writes the head's remark; a teacher's form
                // shows it read-only and it is never taken from them.
                if ($isHead) {
                    $result->principal_remark = $row['principal_remark'] ?? null;
                }
                $result->save();
                $saved++;
            }
        });

        return back()->with('success', trans_choice('{0} Nothing to save.|{1} Remarks saved for :count pupil.|[2,*] Remarks saved for :count pupils.', $saved, ['count' => $saved]));
    }

    /** @return array{0: ClassSection, 1: Term, 2: AcademicSession} */
    private function remarksContext(Request $request): array
    {
        $request->validate([
            'class_section_id' => ['required', 'exists:class_sections,id'],
            'term_id' => ['required', 'exists:terms,id'],
        ]);
        $section = ClassSection::with('form')->findOrFail($request->integer('class_section_id'));
        abort_unless(app(\App\Services\TeachingScope::class)->canUseSection(auth()->user(), $section->id), 403);
        $session = AcademicSession::current();
        abort_unless($session, 422, __('No current academic year is set.'));

        return [$section, Term::findOrFail($request->integer('term_id')), $session];
    }

    /**
     * Subjects of this class with a sequence this term whose marks are not
     * approved or published this year. Nursery classes have no marks.
     *
     * @return array<int, string>
     */
    private function unapprovedSubjects(int $sectionId, int $termId): array
    {
        $section = ClassSection::with('form')->find($sectionId);
        if (! $section || \App\Services\NurseryAssessmentService::isNursery($section->form)) {
            return [];
        }

        $sequenceIds = DB::table('form_sequence')->where('form_id', $section->form_id)->where('term_id', $termId)->pluck('sequence_id');
        if ($sequenceIds->isEmpty()) {
            return [];
        }

        $subjects = DB::table('form_subject')->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->where('form_subject.form_id', $section->form_id)->where('subjects.is_active', true)
            ->pluck('subjects.name', 'subjects.id');

        $done = \App\Models\MarksSubmission::forSession()
            ->where('class_section_id', $sectionId)
            ->whereIn('sequence_id', $sequenceIds)
            ->whereIn('status', ['approved', 'published'])
            ->get()
            ->groupBy('subject_id')
            ->map->count();

        return $subjects->filter(fn ($name, $id) => (int) ($done[$id] ?? 0) < $sequenceIds->count())->values()->all();
    }

    private function slugify(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9\-_ ]/', '', $value);
        $value = preg_replace('/\s+/', '-', trim($value));
        return strtolower($value ?: '');
    }
}
