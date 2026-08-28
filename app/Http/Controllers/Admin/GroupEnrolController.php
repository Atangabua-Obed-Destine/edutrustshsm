<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Stream;
use App\Models\StudentEnrollment;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class GroupEnrolController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'group-enrol';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('group-enrol.view', ['index', 'streams', 'sections', 'previewSubjects']),
            static::can('group-enrol.enrol', ['enrol']),
        ];
    }

    public function index(Request $request)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $terms = Term::orderBy('term_number')->get();

        $sessionId = $request->input('academic_session_id');
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');
        $termId = $request->input('term_id');

        if (!$sessionId) {
            $current = AcademicSession::current();
            $sessionId = $current?->id;
        }

        // Cascading sections for source form
        $classSections = collect();
        if ($formId) {
            $classSections = ClassSection::where('form_id', $formId)
                ->where('is_active', true)->orderBy('name')->get();
        }

        // Load students if all source filters are set
        $enrollments = collect();
        $filtered = false;
        $sequences = collect();

        if ($sessionId && $formId && $classSectionId && $termId) {
            $filtered = true;

            // Get sequences configured for this form + term
            $seqIds = DB::table('form_sequence')
                ->where('form_id', $formId)
                ->where('term_id', $termId)
                ->distinct()
                ->pluck('sequence_id');
            $sequences = \App\Models\Sequence::whereIn('id', $seqIds)
                ->orderBy('sequence_number')
                ->get();

            $enrollments = StudentEnrollment::with([
                    'student.batch',
                    'classSection.form',
                    'term',
                    'stream',
                ])
                ->where('academic_session_id', $sessionId)
                ->where('class_section_id', $classSectionId)
                ->where('term_id', $termId)
                ->where('status', 'active')
                ->get()
                ->sortBy(fn ($e) => $e->student->last_name . ' ' . $e->student->first_name)
                ->values();

            // Attach term average + per-sequence averages from TermResult / SubjectTermResults
            $termResultMap = DB::table('term_results')
                ->whereIn('student_enrollment_id', $enrollments->pluck('id'))
                ->where('term_id', $termId)
                ->get()
                ->keyBy('student_enrollment_id');

            $termResultIds = $termResultMap->pluck('id');

            // Compute per-student average for each sequence slot (avg of all subject scores for that seq)
            $seqAvgs = [];
            if ($termResultIds->isNotEmpty()) {
                for ($s = 1; $s <= min($sequences->count(), 3); $s++) {
                    $col = "sequence_{$s}_score";
                    $rows = DB::table('subject_term_results')
                        ->whereIn('term_result_id', $termResultIds)
                        ->whereNotNull($col)
                        ->select('term_result_id', DB::raw("ROUND(AVG($col), 2) as avg_score"))
                        ->groupBy('term_result_id')
                        ->get()
                        ->keyBy('term_result_id');
                    $seqAvgs[$s] = $rows;
                }
            }

            foreach ($enrollments as $enrollment) {
                $tr = $termResultMap->get($enrollment->id);
                $enrollment->setAttribute('_term_average', $tr->term_average ?? null);
                $enrollment->setAttribute('_overall_rank', $tr->class_rank ?? null);

                // Per-sequence averages
                $seqScores = [];
                if ($tr) {
                    for ($s = 1; $s <= min($sequences->count(), 3); $s++) {
                        $seqScores[$s] = isset($seqAvgs[$s]) ? ($seqAvgs[$s]->get($tr->id)?->avg_score ?? null) : null;
                    }
                }
                $enrollment->setAttribute('_seq_averages', $seqScores);
            }
        }

        return view('admin.group-enrol.index', compact(
            'sessions', 'forms', 'terms', 'classSections',
            'sessionId', 'formId', 'classSectionId', 'termId',
            'enrollments', 'filtered', 'sequences'
        ));
    }

    /**
     * AJAX: Get streams for a form.
     */
    public function streams(Form $form)
    {
        return response()->json(
            $form->streams()->where('is_active', true)->get(['streams.id', 'streams.name', 'streams.code'])
        );
    }

    /**
     * AJAX: Get sections for a form.
     */
    public function sections(Form $form)
    {
        return response()->json(
            ClassSection::where('form_id', $form->id)
                ->where('is_active', true)
                ->orderBy('section')
                ->get(['id', 'name', 'section'])
        );
    }

    /**
     * AJAX: Preview curriculum subjects for the target form + stream.
     */
    public function previewSubjects(Request $request)
    {
        $formId = $request->input('form_id');
        $streamId = $request->input('stream_id');

        if (!$formId) {
            return response()->json([]);
        }

        $subjects = DB::table('form_subject')
            ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->where('form_subject.form_id', $formId)
            ->where('form_subject.stream_id', $streamId ?: null)
            ->select(
                'subjects.name',
                'subjects.code',
                'form_subject.coefficient',
                'form_subject.type'
            )
            ->orderBy('form_subject.type')
            ->orderBy('subjects.name')
            ->get();

        return response()->json($subjects);
    }

    /**
     * Perform group enrolment — creates new enrollment for each selected student.
     */
    public function enrol(Request $request)
    {
        $validated = $request->validate([
            'student_ids'           => ['required', 'array', 'min:1'],
            'student_ids.*'         => ['required', 'integer', 'exists:students,id'],
            'target_session_id'     => ['required', 'exists:academic_sessions,id'],
            'target_form_id'        => ['required', 'exists:forms,id'],
            'target_stream_id'      => ['nullable', 'exists:streams,id'],
            'target_section_id'     => ['required', 'exists:class_sections,id'],
            'target_term_id'        => ['required', 'exists:terms,id'],
        ]);

        $targetSection = ClassSection::find($validated['target_section_id']);
        if (!$targetSection || (int) $targetSection->form_id !== (int) $validated['target_form_id']) {
            return back()->with('error', __('The selected section does not belong to the target form.'));
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($validated['student_ids'] as $studentId) {
                // Check if student already has an active enrollment in the exact same target (session + section + term)
                $exists = StudentEnrollment::where('student_id', $studentId)
                    ->where('academic_session_id', $validated['target_session_id'])
                    ->where('class_section_id', $validated['target_section_id'])
                    ->where('term_id', $validated['target_term_id'])
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Deactivate any previous active enrollment in the same session
                // (keeps the old record for marks/reports history, just marks it as completed)
                $previousEnrollment = StudentEnrollment::where('student_id', $studentId)
                    ->where('academic_session_id', $validated['target_session_id'])
                    ->where('status', 'active')
                    ->first();

                if ($previousEnrollment) {
                    $previousEnrollment->update(['status' => 'completed']);
                }

                // Check max capacity
                if ($targetSection->max_students) {
                    $currentCount = StudentEnrollment::where('class_section_id', $validated['target_section_id'])
                        ->where('academic_session_id', $validated['target_session_id'])
                        ->where('status', 'active')
                        ->count();

                    if ($currentCount >= $targetSection->max_students) {
                        $errors[] = "Section {$targetSection->name} is at maximum capacity ({$targetSection->max_students}).";
                        break;
                    }
                }

                $enrollment = StudentEnrollment::create([
                    'student_id'          => $studentId,
                    'academic_session_id' => $validated['target_session_id'],
                    'term_id'             => $validated['target_term_id'],
                    'class_section_id'    => $validated['target_section_id'],
                    'stream_id'           => $validated['target_stream_id'] ?? null,
                    'residence_type'      => 'day', // default, can be updated later
                    'enrollment_date'     => now()->toDateString(),
                    'status'              => 'active',
                ]);

                // Auto-sync subjects: if same class (only term changed), carry over all subjects from previous enrollment
                if ($previousEnrollment && (int) $previousEnrollment->class_section_id === (int) $validated['target_section_id']) {
                    $prevSubjects = DB::table('student_subjects')
                        ->where('student_enrollment_id', $previousEnrollment->id)
                        ->get(['subject_id', 'coefficient']);

                    if ($prevSubjects->isNotEmpty()) {
                        $now = now();
                        $toInsert = [];
                        foreach ($prevSubjects as $ps) {
                            $toInsert[] = [
                                'student_enrollment_id' => $enrollment->id,
                                'subject_id'            => $ps->subject_id,
                                'coefficient'           => $ps->coefficient,
                                'created_at'            => $now,
                                'updated_at'            => $now,
                            ];
                        }
                        DB::table('student_subjects')->insert($toInsert);
                    } else {
                        $enrollment->syncCoreSubjects();
                    }
                } else {
                    // Different class or no previous enrollment — sync core subjects from curriculum
                    $enrollment->syncCoreSubjects();
                }

                $created++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', __('Enrolment failed: ') . $e->getMessage());
        }

        $message = __(':count student(s) enrolled successfully.', ['count' => $created]);
        if ($skipped > 0) {
            $message .= ' ' . __(':count skipped (already enrolled).', ['count' => $skipped]);
        }
        if (!empty($errors)) {
            $message .= ' ' . implode(' ', $errors);
        }

        return back()->with('success', $message);
    }
}
