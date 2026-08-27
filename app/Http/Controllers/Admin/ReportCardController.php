<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Attendance;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\StudentEnrollment;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportCardController extends Controller
{
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
    public function generate(Request $request)
    {
        $request->validate([
            'class_section_id' => 'required|exists:class_sections,id',
            'term_id' => 'required|exists:terms,id',
            'form_id' => 'required|exists:forms,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
        ]);

        $classSection = ClassSection::with('form')->findOrFail($request->class_section_id);
        $term = Term::findOrFail($request->term_id);
        $formId = $request->form_id;
        $sessionId = $request->academic_session_id;

        // Find sequences through form_sequence pivot (same pattern as exam-publishing)
        $seqIds = DB::table('form_sequence')
            ->where('form_id', $formId)
            ->where('term_id', $term->id)
            ->distinct()
            ->pluck('sequence_id');
        $sequences = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get();

        if ($sequences->isEmpty()) {
            return back()->with('error', 'No sequences found for this term.');
        }

        $enrollments = StudentEnrollment::where('class_section_id', $classSection->id)
            ->where('academic_session_id', $sessionId)
            ->where('term_id', $term->id)
            ->whereIn('status', ['active', 'completed'])
            ->with(['studentSubjects.subject.forms' => fn ($q) => $q->where('form_id', $classSection->form_id)])
            ->get();

        if ($enrollments->isEmpty()) {
            return back()->with('error', 'No active students in this class.');
        }

        DB::transaction(function () use ($enrollments, $sequences, $term, $classSection) {
            $allTermAverages = [];

            foreach ($enrollments as $enrollment) {
                $termResult = TermResult::firstOrNew([
                    'student_enrollment_id' => $enrollment->id,
                    'term_id' => $term->id,
                ]);

                $totalWeighted = 0;
                $totalCoeff = 0;
                $subjectResults = [];

                foreach ($enrollment->studentSubjects as $studentSubject) {
                    $subject = $studentSubject->subject;
                    // Get coefficient from form_subject pivot
                    $formSubject = $subject->forms->first();
                    $coefficient = $formSubject?->pivot?->coefficient ?? 1.0;

                    // Get marks for each sequence (use local 1-based index, not global sequence_number)
                    $seqScores = [];
                    $localIdx = 0;
                    foreach ($sequences as $seq) {
                        $localIdx++;
                        // Only marks that have cleared the approval workflow may
                        // reach a report card. Without this filter the whole
                        // draft -> submitted -> approved -> published pipeline had
                        // no effect on results.
                        $mark = Mark::where('student_enrollment_id', $enrollment->id)
                            ->where('subject_id', $subject->id)
                            ->where('sequence_id', $seq->id)
                            ->whereIn('status', Mark::REPORTABLE_STATUSES)
                            ->first();

                        $seqScores[$localIdx] = ($mark && !$mark->is_absent) ? (float) $mark->score : null;
                    }

                    // Calculate term average for subject (weighted by sequence weight)
                    $totalWeight = 0;
                    $weightedSum = 0;
                    $localIdx = 0;
                    foreach ($sequences as $seq) {
                        $localIdx++;
                        $score = $seqScores[$localIdx] ?? null;
                        if ($score !== null) {
                            $w = (float) ($seq->weight ?? 1);
                            $weightedSum += $score * $w;
                            $totalWeight += $w;
                        }
                    }

                    $termAvg = $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : null;
                    $weightedScore = ($termAvg !== null) ? round($termAvg * $coefficient, 2) : null;
                    $grade = $termAvg !== null ? (GradeScale::getGrade($termAvg)?->grade ?? '-') : '-';

                    // Find teacher from assignment
                    $assignment = $classSection->teacherAssignments()
                        ->where('subject_id', $subject->id)
                        ->with('teacher')
                        ->first();

                    $subjectResults[] = [
                        'subject_id' => $subject->id,
                        'coefficient' => $coefficient,
                        'sequence_1_score' => $seqScores[1] ?? null,
                        'sequence_2_score' => $seqScores[2] ?? null,
                        'sequence_3_score' => $seqScores[3] ?? null,
                        'term_average' => $termAvg,
                        'weighted_score' => $weightedScore,
                        'grade' => $grade,
                        'teacher_name' => $assignment?->teacher?->full_name,
                    ];

                    if ($termAvg !== null) {
                        $totalWeighted += $weightedScore;
                        $totalCoeff += $coefficient;
                    }
                }

                $termAverage = $totalCoeff > 0 ? round($totalWeighted / $totalCoeff, 2) : 0;
                $overallGrade = GradeScale::getGrade($termAverage)?->grade ?? '-';

                // Attendance
                $daysPresent = Attendance::where('student_enrollment_id', $enrollment->id)
                    ->whereIn('status', ['present', 'late'])
                    ->when($term->start_date && $term->end_date, fn ($q) => $q->whereBetween('date', [$term->start_date, $term->end_date]))
                    ->count();

                $daysAbsent = Attendance::where('student_enrollment_id', $enrollment->id)
                    ->where('status', 'absent')
                    ->when($term->start_date && $term->end_date, fn ($q) => $q->whereBetween('date', [$term->start_date, $term->end_date]))
                    ->count();

                $termResult->fill([
                    'total_weighted_score' => $totalWeighted,
                    'total_coefficient' => $totalCoeff,
                    'term_average' => $termAverage,
                    'overall_grade' => $overallGrade,
                    'days_present' => $daysPresent,
                    'days_absent' => $daysAbsent,
                    'total_school_days' => $daysPresent + $daysAbsent,
                    // Do NOT reset is_published here. Regenerating after a mark
                    // correction used to silently unpublish every report card in the
                    // class, immediately revoking parent-portal access. Publishing is
                    // an explicit action (see publish()); a new result defaults to
                    // unpublished via the column default.
                ]);
                $termResult->save();

                // Save subject results
                foreach ($subjectResults as $sr) {
                    SubjectTermResult::updateOrCreate(
                        [
                            'term_result_id' => $termResult->id,
                            'subject_id' => $sr['subject_id'],
                        ],
                        $sr
                    );
                }

                $allTermAverages[$enrollment->id] = [
                    'term_result' => $termResult,
                    'average' => $termAverage,
                ];
            }

            // Compute class statistics and ranks
            $averages = collect($allTermAverages)->pluck('average')->filter(fn ($v) => $v > 0)->sort()->values();
            $classAvg = $averages->avg() ? round($averages->avg(), 2) : 0;
            $highestAvg = $averages->max() ?? 0;
            $lowestAvg = $averages->min() ?? 0;
            $totalStudents = $averages->count();

            // Sort by average descending for ranking
            $ranked = collect($allTermAverages)->sortByDesc('average')->values();
            $rank = 0;
            $lastAvg = null;
            foreach ($ranked as $i => $item) {
                if ($item['average'] !== $lastAvg) {
                    $rank = $i + 1;
                }
                $lastAvg = $item['average'];

                $item['term_result']->update([
                    'class_rank' => $rank,
                    'total_students' => $totalStudents,
                    'class_average' => $classAvg,
                    'highest_average' => $highestAvg,
                    'lowest_average' => $lowestAvg,
                ]);
            }

            // Subject ranks
            $subjectIds = SubjectTermResult::whereIn('term_result_id', collect($allTermAverages)->pluck('term_result.id'))
                ->distinct('subject_id')
                ->pluck('subject_id');

            foreach ($subjectIds as $subjectId) {
                $subResults = SubjectTermResult::whereIn('term_result_id', collect($allTermAverages)->pluck('term_result.id'))
                    ->where('subject_id', $subjectId)
                    ->whereNotNull('term_average')
                    ->orderByDesc('term_average')
                    ->get();

                $subRank = 0;
                $lastScore = null;
                $totalSubStudents = $subResults->count();

                foreach ($subResults as $si => $sr) {
                    if ((float) $sr->term_average !== $lastScore) {
                        $subRank = $si + 1;
                    }
                    $lastScore = (float) $sr->term_average;
                    $sr->update([
                        'subject_rank' => $subRank,
                        'subject_total_students' => $totalSubStudents,
                    ]);
                }
            }
        });

        return redirect()->route('admin.report-cards.index', [
            'academic_session_id' => $request->academic_session_id,
            'form_id' => $request->form_id,
            'class_section_id' => $request->class_section_id,
            'term_id' => $request->term_id,
        ])->with('success', 'Report cards generated successfully. ' . $enrollments->count() . ' students processed.');
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
