<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\TermResult;
use App\Support\ParentContext;
use Illuminate\Support\Facades\DB;

class ParentReportCardController extends Controller
{
    /** List the published report cards across all of this student's enrollments. */
    public function index(int $student)
    {
        $studentModel = ParentContext::authorizeStudent($student);

        $enrollmentIds = $studentModel->enrollments()->pluck('id');

        $results = TermResult::whereIn('student_enrollment_id', $enrollmentIds)
            ->where('is_published', true)
            ->with(['term', 'enrollment.academicSession', 'enrollment.classSection.form'])
            ->get()
            ->sortByDesc(fn ($r) => optional($r->enrollment->academicSession)->start_date)
            ->values();

        return view('parent.report-cards.index', compact('studentModel', 'results'));
    }

    /** Show a single published report card — reuses the admin report-card view. */
    public function show(int $student, TermResult $termResult)
    {
        $studentModel = ParentContext::authorizeStudent($student);

        // Security: the report card must belong to this student AND be published.
        abort_if($termResult->enrollment->student_id !== $studentModel->id, 403);
        abort_if(! $termResult->is_published, 403, __('This report card has not been published yet.'));

        $termResult->load([
            'enrollment.student',
            'enrollment.classSection.form',
            'enrollment.classSection.classTeacher',
            'term',
            'subjectResults.subject',
        ]);

        $school = SchoolSetting::current();
        $currentSession = AcademicSession::current();

        $formId = $termResult->enrollment->classSection->form_id;
        $termId = $termResult->term_id;
        $seqIds = DB::table('form_sequence')
            ->where('form_id', $formId)
            ->where('term_id', $termId)
            ->distinct()
            ->pluck('sequence_id');
        $sequences = Sequence::whereIn('id', $seqIds)->orderBy('sequence_number')->get();

        // Reuse the exact admin report-card layout for visual consistency.
        return view('admin.report-cards.show', compact('termResult', 'school', 'currentSession', 'sequences'));
    }
}
