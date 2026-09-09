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
use App\Models\Subject;
use App\Models\Term;
use App\Models\SchoolSetting;
use App\Services\MarksWorkflowService;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class MarksController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'marks-entry';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('marks-entry.view', ['index', 'subjectsByForm', 'termsByForm', 'sequencesByFormTerm', 'sectionsByForm']),
            static::can('marks-entry.enter', ['save', 'submit']),
            static::can('marks-entry.edit', ['approve', 'returnMarks']),
        ];
    }

    /**
     * Marks entry page — cascading filter + marks grid.
     */
    public function index(Request $request)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();

        $sessionId = $request->input('academic_session_id');
        $educationSystem = $request->input('education_system', 'english');
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');
        $subjectId = $request->input('subject_id');
        $termId = $request->input('term_id');
        $sequenceId = $request->input('sequence_id');

        // Default to current session
        if (!$sessionId) {
            $current = AcademicSession::current();
            $sessionId = $current ? $current->id : null;
        }

        // Cascading data for pre-selected filters
        $classSections = collect();
        $subjects = collect();
        $terms = collect();
        $sequences = collect();

        if ($formId) {
            $classSections = ClassSection::where('form_id', $formId)
                ->where('is_active', true)->orderBy('name')->get();

            // Subjects enrolled for this form (via form_subject pivot)
            $subjects = Subject::where('is_active', true)
                ->whereHas('forms', fn ($q) => $q->where('forms.id', $formId))
                ->orderBy('name')->get();

            // Terms that have sequences enrolled for this form (via form_sequence)
            $termIds = DB::table('form_sequence')
                ->where('form_id', $formId)
                ->distinct()
                ->pluck('term_id');
            $terms = Term::whereIn('id', $termIds)->orderBy('term_number')->get();
        }

        if ($formId && $termId) {
            // Sequences enrolled for this form + term
            $seqIds = DB::table('form_sequence')
                ->where('form_id', $formId)
                ->where('term_id', $termId)
                ->pluck('sequence_id');
            $sequences = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get();
        }

        // Load marks grid data if all filters are set
        $enrollments = collect();
        $existingMarks = collect();
        $submission = null;
        $gradeScales = collect();
        $classSection = null;
        $subject = null;
        $sequence = null;
        $filtered = false;
        $coefficient = null;

        if ($classSectionId && $subjectId && $sequenceId && $sessionId) {
            $filtered = true;
            $classSection = ClassSection::with('form')->find($classSectionId);
            $subject = Subject::find($subjectId);
            $sequence = Sequence::with('term')->find($sequenceId);

            if ($classSection && $subject && $sequence) {
                // Get coefficient from form_subject
                $formSubject = DB::table('form_subject')
                    ->where('form_id', $classSection->form_id)
                    ->where('subject_id', $subjectId)
                    ->first();
                $coefficient = $formSubject->coefficient ?? 1;

                // Auto-sync core subjects so students appear without visiting subject-add-drop
                StudentEnrollment::syncCoreSubjectsForClass($classSectionId, $sessionId);

                // Get enrolled students who have this subject
                // Include active + completed (students who progressed to next term but took exams in this one)
                $enrollments = StudentEnrollment::with('student')
                    ->where('class_section_id', $classSectionId)
                    ->where('academic_session_id', $sessionId)
                    ->where('term_id', $termId)
                    ->whereIn('status', ['active', 'completed'])
                    ->whereHas('studentSubjects', fn ($q) => $q->where('subject_id', $subjectId))
                    ->get()
                    ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
                    ->values();

                // Existing marks
                $existingMarks = Mark::where('subject_id', $subjectId)
                    ->where('sequence_id', $sequenceId)
                    ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
                    ->get()
                    ->keyBy('student_enrollment_id');

                // Get/create submission tracker
                $submission = MarksSubmission::firstOrCreate(
                    [
                        'class_section_id' => $classSectionId,
                        'subject_id' => $subjectId,
                        'sequence_id' => $sequenceId,
                    ],
                    [
                        'teacher_id' => auth()->id(),
                        'status' => 'draft',
                        'total_students' => $enrollments->count(),
                        'marks_entered' => 0,
                    ]
                );

                $gradeScales = GradeScale::orderByDesc('min_mark')->get();
            }
        }

        // Recent submissions for current session
        $recentSubmissions = MarksSubmission::with(['classSection.form', 'subject', 'sequence', 'teacher'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        return view('admin.marks.index', compact(
            'sessions', 'forms', 'sessionId', 'educationSystem', 'formId',
            'classSectionId', 'subjectId', 'termId', 'sequenceId',
            'classSections', 'subjects', 'terms', 'sequences',
            'enrollments', 'existingMarks', 'submission', 'gradeScales',
            'classSection', 'subject', 'sequence', 'filtered', 'coefficient',
            'recentSubmissions'
        ));
    }

    /**
     * AJAX — subjects enrolled for a form.
     */
    public function subjectsByForm(Form $form)
    {
        $subjects = Subject::where('is_active', true)
            ->whereHas('forms', fn ($q) => $q->where('forms.id', $form->id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($subjects->map(fn ($s) => [
            'id' => $s->id,
            'label' => $s->name . ' (' . $s->code . ')',
        ]));
    }

    /**
     * AJAX — terms that have sequences enrolled for a form.
     */
    public function termsByForm(Form $form)
    {
        $termIds = DB::table('form_sequence')
            ->where('form_id', $form->id)
            ->distinct()
            ->pluck('term_id');

        $terms = Term::whereIn('id', $termIds)->orderBy('term_number')->get(['id', 'name']);

        return response()->json($terms);
    }

    /**
     * AJAX — sequences enrolled for a form + term.
     */
    public function sequencesByFormTerm(Form $form, Term $term)
    {
        $seqIds = DB::table('form_sequence')
            ->where('form_id', $form->id)
            ->where('term_id', $term->id)
            ->pluck('sequence_id');

        $sequences = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get(['id', 'name']);

        return response()->json($sequences);
    }

    /**
     * AJAX — sections for a form.
     */
    public function sectionsByForm(Form $form)
    {
        $sections = ClassSection::where('form_id', $form->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($sections);
    }

    /**
     * Save marks (batch update).
     */
    public function save(Request $request, MarksWorkflowService $workflow)
    {
        $settings = SchoolSetting::current();
        $maxMark = (float) ($settings?->max_mark ?? 20);

        $validated = $request->validate([
            'class_section_id' => ['required', 'exists:class_sections,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'sequence_id' => ['required', 'exists:sequences,id'],
            'marks' => ['required', 'array'],
            'marks.*.enrollment_id' => ['required', 'exists:student_enrollments,id'],
            // Bounds come from School Settings, not a hardcoded /20.
            'marks.*.score' => ['nullable', 'numeric', 'min:0', 'max:'.$maxMark],
            'marks.*.is_absent' => ['nullable'],
        ]);

        // Marks that have cleared review are not editable here. Saving used to
        // overwrite approved and published marks and reset them to draft, while
        // the submission still read "published" — the two then disagreed about
        // what state the class's results were in, and report cards silently
        // changed underneath parents who had already seen them.
        $existing = MarksSubmission::where('class_section_id', $validated['class_section_id'])
            ->where('subject_id', $validated['subject_id'])
            ->where('sequence_id', $validated['sequence_id'])
            ->first();

        if ($existing && ! $workflow->isEditable($existing)) {
            return back()->with('error', __(
                'These marks are :status and can no longer be edited. Return them to the teacher first.',
                ['status' => __(ucfirst($existing->status))]
            ));
        }

        $gradeScales = GradeScale::orderByDesc('min_mark')->get();

        DB::transaction(function () use ($validated, $gradeScales) {
            $marksEntered = 0;
            $scores = [];

            foreach ($validated['marks'] as $markData) {
                $isAbsent = !empty($markData['is_absent']);
                $score = $isAbsent ? null : ($markData['score'] ?? null);
                $grade = null;

                if ($score !== null) {
                    foreach ($gradeScales as $gs) {
                        if ($score >= $gs->min_mark && $score <= $gs->max_mark) {
                            $grade = $gs->grade;
                            break;
                        }
                    }
                    $scores[] = $score;
                    $marksEntered++;
                } elseif ($isAbsent) {
                    $marksEntered++;
                }

                Mark::updateOrCreate(
                    [
                        'student_enrollment_id' => $markData['enrollment_id'],
                        'subject_id' => $validated['subject_id'],
                        'sequence_id' => $validated['sequence_id'],
                    ],
                    [
                        'score' => $score,
                        'grade' => $grade,
                        'is_absent' => $isAbsent,
                        'entered_by' => auth()->id(),
                        'status' => 'draft',
                    ]
                );
            }

            // Update submission stats
            $submission = MarksSubmission::where('class_section_id', $validated['class_section_id'])
                ->where('subject_id', $validated['subject_id'])
                ->where('sequence_id', $validated['sequence_id'])
                ->first();

            if ($submission) {
                $passmark = (float) (SchoolSetting::current()?->pass_mark ?? 10);
                $submission->update([
                    'marks_entered' => $marksEntered,
                    'class_average' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null,
                    'highest_mark' => count($scores) > 0 ? max($scores) : null,
                    'lowest_mark' => count($scores) > 0 ? min($scores) : null,
                    'pass_rate' => count($scores) > 0
                        ? round((count(array_filter($scores, fn ($s) => $s >= $passmark)) / count($scores)) * 100, 2)
                        : null,
                ]);
            }
        });

        return redirect()->back()->with('success', __('Marks saved successfully.'));
    }

    /**
     * Submit marks for approval.
     */
    public function submit(MarksSubmission $submission, MarksWorkflowService $workflow)
    {
        try {
            $workflow->apply($submission, 'submitted');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Marks submitted for approval.'));
    }

    /**
     * Approve marks (admin).
     */
    public function approve(MarksSubmission $submission, MarksWorkflowService $workflow)
    {
        try {
            $workflow->apply($submission, 'approved');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Marks approved successfully.'));
    }

    /**
     * Return marks for correction.
     */
    public function returnMarks(Request $request, MarksSubmission $submission, MarksWorkflowService $workflow)
    {
        $validated = $request->validate(['admin_comment' => ['required', 'string', 'max:500']]);

        try {
            $workflow->apply($submission, 'returned', $validated['admin_comment']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Marks returned to teacher.'));
    }
}
