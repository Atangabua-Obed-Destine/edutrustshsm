<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\Sequence;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\Term;
use App\Services\MarksWorkflowService;
use App\Services\TermResultCalculator;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ExamPublishingController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'exam-publishing';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('exam-publishing.view', ['index', 'sectionsByForm', 'termsByForm', 'sequencesByFormTerm']),
            static::can('exam-publishing.publish', ['publishSubject', 'bulkTransition']),
            static::can('exam-publishing.unpublish', ['unpublishSubject']),
        ];
    }

    /**
     * Show the Exam Publishing page with filters and optionally loaded data.
     */
    public function index(Request $request, TermResultCalculator $calculator)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();

        $sessionId = $request->input('academic_session_id');
        $educationSystem = $request->input('education_system', 'english');
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');
        $termId = $request->input('term_id');
        $sequenceId = $request->input('sequence_id');

        if (!$sessionId) {
            $current = AcademicSession::current();
            $sessionId = $current ? $current->id : null;
        }

        $classSections = collect();
        $terms = collect();
        $sequences = collect();

        if ($formId) {
            $classSections = ClassSection::where('form_id', $formId)
                ->where('is_active', true)->orderBy('name')->get();

            $termIds = DB::table('form_sequence')
                ->where('form_id', $formId)
                ->distinct()
                ->pluck('term_id');
            $terms = Term::whereIn('id', $termIds)->orderBy('term_number')->get();
        }

        if ($formId && $termId) {
            $seqIds = DB::table('form_sequence')
                ->where('form_id', $formId)
                ->where('term_id', $termId)
                ->pluck('sequence_id');
            $sequences = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get();
        }

        $filtered = false;
        $subjectRows = collect();
        $stats = null;
        $classSection = null;
        $sequence = null;
        $termSequences = collect();
        $formSubjects = collect();
        $previewStudents = [];
        $subjectSummaries = [];
        $previewPassCount = 0;
        $previewFailCount = 0;

        if ($classSectionId && $sequenceId && $sessionId) {
            $filtered = true;
            $classSection = ClassSection::with('form')->find($classSectionId);
            $sequence = Sequence::with('term')->find($sequenceId);

            if ($classSection && $sequence) {
                // Total active students for this section+session
                $totalStudents = StudentEnrollment::where('class_section_id', $classSectionId)
                    ->where('academic_session_id', $sessionId)
                    ->where('status', 'active')
                    ->count();

                // Get all subjects configured for this form (deduplicated across streams)
                $formSubjects = DB::table('form_subject')
                    ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
                    ->where('form_subject.form_id', $classSection->form_id)
                    ->select(
                        'form_subject.subject_id',
                        'subjects.name as subject_name',
                        'subjects.code as subject_code',
                        DB::raw('MAX(form_subject.coefficient) as coefficient'),
                        DB::raw("MIN(form_subject.type) as type")
                    )
                    ->groupBy('form_subject.subject_id', 'subjects.name', 'subjects.code')
                    ->orderByRaw("MIN(form_subject.type)")
                    ->orderBy('subjects.name')
                    ->get();

                // Build subject rows with marks submission data
                // Get enrollment IDs for this section+session
                $enrollmentIds = StudentEnrollment::where('class_section_id', $classSectionId)
                    ->where('academic_session_id', $sessionId)
                    ->where('status', 'active')
                    ->pluck('id');

                $subjectRows = $formSubjects->map(function ($fs) use ($classSectionId, $sequenceId, $enrollmentIds) {
                    $submission = MarksSubmission::where('class_section_id', $classSectionId)
                        ->where('subject_id', $fs->subject_id)
                        ->where('sequence_id', $sequenceId)
                        ->first();

                    // Students who registered this subject via Subject Add/Drop
                    $subjectStudentCount = StudentSubject::where('subject_id', $fs->subject_id)
                        ->whereIn('student_enrollment_id', $enrollmentIds)
                        ->count();

                    // Count marks for this subject+section+sequence
                    $marksQuery = Mark::where('subject_id', $fs->subject_id)
                        ->where('sequence_id', $sequenceId)
                        ->whereHas('enrollment', fn ($q) => $q->where('class_section_id', $classSectionId));

                    $withMarks = (clone $marksQuery)->whereNotNull('score')->count();
                    $passCount = (clone $marksQuery)->where('score', '>=', 10)->count();
                    $failCount = (clone $marksQuery)->whereNotNull('score')->where('score', '<', 10)->count();
                    $avgScore = (clone $marksQuery)->whereNotNull('score')->avg('score');

                    return (object) [
                        'subject_id' => $fs->subject_id,
                        'subject_name' => $fs->subject_name,
                        'subject_code' => $fs->subject_code,
                        'coefficient' => $fs->coefficient,
                        'type' => $fs->type,
                        'total_students' => $subjectStudentCount,
                        'with_marks' => $withMarks,
                        'pass_count' => $passCount,
                        'fail_count' => $failCount,
                        'avg_score' => $avgScore ? round($avgScore, 1) : null,
                        'submission_id' => $submission?->id,
                        'workflow_status' => $submission?->status ?? 'none',
                    ];
                });

                // Stats
                $publishedCount = $subjectRows->where('workflow_status', 'published')->count();
                $approvedCount = $subjectRows->where('workflow_status', 'approved')->count();
                $submittedCount = $subjectRows->where('workflow_status', 'submitted')->count();
                $totalSubjects = $subjectRows->count();
                $withMarksCount = $subjectRows->where('with_marks', '>', 0)->count();

                $stats = (object) [
                    'total_subjects' => $totalSubjects,
                    'total_students' => $totalStudents,
                    'published_count' => $publishedCount,
                    'approved_count' => $approvedCount,
                    'submitted_count' => $submittedCount,
                    'with_marks_count' => $withMarksCount,
                ];

                // ── Students Results Preview ──
                // Computed by the SAME service that writes report cards, so the
                // preview cannot drift from what will be saved. It previously
                // carried its own copy of the arithmetic, which used a different
                // subject universe, a different coefficient source, and let
                // subjects a student is NOT registered for change their average.
                $computed = $calculator->compute($classSection, Term::findOrFail($termId), (int) $sessionId);

                $termSequences = $computed['sequences'];
                $passMark = $computed['pass_mark'];

                $previewStudents = $computed['students']->values()->map(function ($student, $idx) {
                    $subjects = [];

                    foreach ($student->subjects as $subject) {
                        $subjects[$subject['subject_id']] = [
                            'is_registered' => true,
                            'seq_scores' => collect($subject['scores'])
                                ->map(fn ($score) => ['score' => $score, 'is_absent' => $score === null])
                                ->all(),
                            'term_avg' => $subject['term_average'],
                            'weighted_score' => $subject['weighted_score'],
                            'grade' => $subject['grade'],
                            'grade_desc' => '',
                        ];
                    }

                    return (object) [
                        'sn' => $idx + 1,
                        'matricule' => $student->student?->student_id,
                        'name' => strtoupper($student->student?->last_name ?? '').' '.($student->student?->first_name ?? ''),
                        'enrollment_id' => $student->enrollment_id,
                        'subjects' => $subjects,
                        'overall_weighted' => $student->total_weighted,
                        'overall_coeff' => $student->total_coefficient,
                        'overall_avg' => $student->average,
                        'overall_grade' => $student->grade,
                        'rank' => $student->rank ?? '-',
                    ];
                })->all();

                // Per-subject summary across the class.
                $subjectSummaries = [];
                foreach ($formSubjects as $fs) {
                    $subjectSummaries[$fs->subject_id] = ['registered' => 0, 'with_marks' => 0, 'pass' => 0, 'fail' => 0];
                }

                foreach ($computed['students'] as $student) {
                    foreach ($student->subjects as $subject) {
                        $id = $subject['subject_id'];
                        $subjectSummaries[$id] ??= ['registered' => 0, 'with_marks' => 0, 'pass' => 0, 'fail' => 0];
                        $subjectSummaries[$id]['registered']++;

                        if ($subject['term_average'] !== null) {
                            $subjectSummaries[$id]['with_marks']++;
                            $subject['term_average'] >= $passMark
                                ? $subjectSummaries[$id]['pass']++
                                : $subjectSummaries[$id]['fail']++;
                        }
                    }
                }

                $previewPassCount = $computed['students']
                    ->filter(fn ($s) => $s->average !== null && $s->average >= $passMark)->count();
                $previewFailCount = $computed['students']
                    ->filter(fn ($s) => $s->average !== null && $s->average < $passMark)->count();
            }
        }

        return view('admin.exam-publishing.index', compact(
            'sessions', 'forms', 'sessionId', 'educationSystem', 'formId',
            'classSectionId', 'termId', 'sequenceId',
            'classSections', 'terms', 'sequences',
            'filtered', 'subjectRows', 'stats',
            'classSection', 'sequence',
            'termSequences', 'formSubjects', 'previewStudents',
            'subjectSummaries', 'previewPassCount', 'previewFailCount'
        ));
    }

    /**
     * Publish a single subject's marks (approved → published).
     */
    public function publishSubject(Request $request, MarksWorkflowService $workflow)
    {
        $validated = $request->validate([
            'submission_id' => ['required', 'exists:marks_submissions,id'],
        ]);

        $submission = MarksSubmission::findOrFail($validated['submission_id']);

        try {
            $workflow->apply($submission, 'published');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':subject marks published successfully.', [
            'subject' => $submission->subject->name,
        ]));
    }

    /**
     * Unpublish a subject's marks back to approved (published → approved).
     */
    public function unpublishSubject(Request $request, MarksWorkflowService $workflow)
    {
        $validated = $request->validate([
            'submission_id' => ['required', 'exists:marks_submissions,id'],
        ]);

        $submission = MarksSubmission::findOrFail($validated['submission_id']);

        try {
            $workflow->apply($submission, 'unpublished');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':subject marks unpublished (reverted to approved).', [
            'subject' => $submission->subject->name,
        ]));
    }

    /**
     * Bulk transition: move selected subjects to a new workflow state.
     */
    public function bulkTransition(Request $request, MarksWorkflowService $workflow)
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1'],
            'submission_ids.*' => ['required', 'exists:marks_submissions,id'],
            'transition_to' => ['required', 'in:submitted,approved,published,returned'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $submissions = MarksSubmission::with('subject:id,name')
            ->whereIn('id', $validated['submission_ids'])
            ->get();

        try {
            $result = $workflow->applyMany($submissions, $validated['transition_to'], $validated['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = trans_choice(
            ':count subject transitioned to :status.|:count subjects transitioned to :status.',
            $result['applied'],
            ['count' => $result['applied'], 'status' => __(ucfirst($workflow->targetStatus($validated['transition_to'])))]
        );

        // Skipped subjects are named rather than silently dropped.
        if ($result['skipped']) {
            $message .= ' '.__(':count skipped (invalid state): :names', [
                'count' => count($result['skipped']),
                'names' => implode(', ', $result['skipped']),
            ]);
        }

        return back()->with($result['skipped'] ? 'error' : 'success', $message);
    }

    /**
     * AJAX endpoints (reuse marks controller patterns).
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

    public function termsByForm(Form $form)
    {
        $termIds = DB::table('form_sequence')
            ->where('form_id', $form->id)
            ->distinct()
            ->pluck('term_id');
        return response()->json(Term::whereIn('id', $termIds)->orderBy('term_number')->get(['id', 'name']));
    }

    public function sequencesByFormTerm(Form $form, Term $term)
    {
        $seqIds = DB::table('form_sequence')
            ->where('form_id', $form->id)
            ->where('term_id', $term->id)
            ->pluck('sequence_id');
        return response()->json(Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get(['id', 'name']));
    }
}
