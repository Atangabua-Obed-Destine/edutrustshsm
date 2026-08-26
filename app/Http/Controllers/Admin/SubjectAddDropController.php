<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectAddDropController extends Controller
{
    /**
     * Show the Subject Add/Drop page with student search.
     */
    public function index()
    {
        $currentSession = AcademicSession::where('is_current', true)->first();

        // Get all active students with current enrollment for the searchable dropdown
        $students = Student::where('status', 'active')
            ->whereHas('currentEnrollment')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'student_id', 'first_name', 'last_name', 'other_names']);

        return view('admin.subject-add-drop.index', compact('currentSession', 'students'));
    }

    /**
     * AJAX: Load student info + subjects for the selected student.
     */
    public function loadStudent(Student $student)
    {
        $currentSession = AcademicSession::where('is_current', true)->first();
        if (!$currentSession) {
            return response()->json(['error' => 'No active academic session found.'], 422);
        }

        $enrollment = StudentEnrollment::where('student_id', $student->id)
            ->where('academic_session_id', $currentSession->id)
            ->where('status', 'active')
            ->with(['classSection', 'stream', 'term'])
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Student has no active enrollment for the current session.'], 422);
        }

        $formId = $enrollment->classSection->form_id;
        $streamId = $enrollment->stream_id;

        // Get curriculum subjects for this form+stream from form_subject pivot
        $curriculumSubjects = DB::table('form_subject')
            ->join('subjects', 'subjects.id', '=', 'form_subject.subject_id')
            ->where('form_subject.form_id', $formId)
            ->where('form_subject.stream_id', $streamId)
            ->select(
                'form_subject.subject_id',
                'form_subject.coefficient',
                'form_subject.type',
                'subjects.name as subject_name',
                'subjects.code as subject_code'
            )
            ->orderBy('form_subject.type') // core first
            ->orderBy('subjects.name')
            ->get();

        // Auto-sync core subjects into student_subjects if not already there
        $enrollment->syncCoreSubjects();

        // Get student's currently enrolled subjects
        $enrolledSubjectIds = StudentSubject::where('student_enrollment_id', $enrollment->id)
            ->pluck('subject_id')
            ->toArray();

        // Build enrolled subjects list (core + selected electives)
        $enrolledSubjects = [];
        $availableElectives = [];

        foreach ($curriculumSubjects as $cs) {
            $subjectData = [
                'subject_id' => $cs->subject_id,
                'subject_name' => $cs->subject_name,
                'subject_code' => $cs->subject_code,
                'coefficient' => $cs->coefficient,
                'type' => $cs->type,
            ];

            if (in_array($cs->subject_id, $enrolledSubjectIds)) {
                $enrolledSubjects[] = $subjectData;
            } elseif ($cs->type === 'elective') {
                $availableElectives[] = $subjectData;
            }
        }

        // Load form and stream names
        $formName = DB::table('forms')->where('id', $formId)->value('name');
        $streamName = $enrollment->stream ? $enrollment->stream->name : null;
        $sectionName = $enrollment->classSection->name ?? '—';
        $termName = $enrollment->term->name ?? '—';

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'gender' => $student->gender,
                'photo' => $student->photo,
                'status' => $student->status,
            ],
            'enrollment' => [
                'id' => $enrollment->id,
                'session' => $currentSession->name,
                'form' => $formName,
                'stream' => $streamName,
                'section' => $sectionName,
                'term' => $termName,
                'residence_type' => $enrollment->residence_type,
            ],
            'enrolled_subjects' => $enrolledSubjects,
            'available_electives' => $availableElectives,
            'stats' => [
                'total_enrolled' => count($enrolledSubjects),
                'core_count' => collect($enrolledSubjects)->where('type', 'core')->count(),
                'elective_count' => collect($enrolledSubjects)->where('type', 'elective')->count(),
                'total_coefficient' => collect($enrolledSubjects)->sum('coefficient'),
                'available_electives_count' => count($availableElectives),
            ],
        ]);
    }

    /**
     * Add an elective subject for a student.
     */
    public function addSubject(Request $request)
    {
        $request->validate([
            'enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $enrollment = StudentEnrollment::with('classSection')->findOrFail($request->enrollment_id);

        // Verify this is an elective subject for the student's form+stream
        $formSubject = DB::table('form_subject')
            ->where('form_id', $enrollment->classSection->form_id)
            ->where('stream_id', $enrollment->stream_id)
            ->where('subject_id', $request->subject_id)
            ->where('type', 'elective')
            ->first();

        if (!$formSubject) {
            return response()->json(['error' => 'This subject is not an available elective for this student.'], 422);
        }

        // Check if already enrolled
        $exists = StudentSubject::where('student_enrollment_id', $enrollment->id)
            ->where('subject_id', $request->subject_id)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Student is already enrolled in this subject.'], 422);
        }

        StudentSubject::create([
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $request->subject_id,
            'coefficient' => $formSubject->coefficient,
        ]);

        return response()->json(['success' => true, 'message' => 'Elective subject added successfully.']);
    }

    /**
     * Drop an elective subject for a student.
     */
    public function dropSubject(Request $request)
    {
        $request->validate([
            'enrollment_id' => ['required', 'exists:student_enrollments,id'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $enrollment = StudentEnrollment::with('classSection')->findOrFail($request->enrollment_id);

        // Verify this is an elective (cannot drop core subjects)
        $formSubject = DB::table('form_subject')
            ->where('form_id', $enrollment->classSection->form_id)
            ->where('stream_id', $enrollment->stream_id)
            ->where('subject_id', $request->subject_id)
            ->first();

        if ($formSubject && $formSubject->type === 'core') {
            return response()->json(['error' => 'Core subjects cannot be dropped.'], 422);
        }

        $deleted = StudentSubject::where('student_enrollment_id', $enrollment->id)
            ->where('subject_id', $request->subject_id)
            ->delete();

        if (!$deleted) {
            return response()->json(['error' => 'Subject enrollment not found.'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Elective subject dropped successfully.']);
    }

    /**
     * Auto-enroll core subjects into student_subjects if not already there.
     */
}
