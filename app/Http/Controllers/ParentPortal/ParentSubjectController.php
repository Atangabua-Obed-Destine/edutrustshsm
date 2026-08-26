<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\StudentSubject;
use App\Models\TeacherSubjectAssignment;
use App\Support\ParentContext;

class ParentSubjectController extends Controller
{
    public function index(int $student)
    {
        $studentModel = ParentContext::authorizeStudent($student);
        $enrollment = $studentModel->currentEnrollment;

        $subjects = collect();

        if ($enrollment) {
            $studentSubjects = StudentSubject::where('student_enrollment_id', $enrollment->id)
                ->with('subject.department')
                ->get();

            // Map each subject to its assigned teacher for this class section (if any).
            $assignments = TeacherSubjectAssignment::where('class_section_id', $enrollment->class_section_id)
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->with('teacher')
                ->get()
                ->keyBy('subject_id');

            $subjects = $studentSubjects->map(function ($ss) use ($assignments) {
                $teacher = $assignments->get($ss->subject_id)?->teacher;

                return (object) [
                    'name'        => $ss->subject->name ?? '—',
                    'code'        => $ss->subject->code ?? '',
                    'department'  => $ss->subject->department->name ?? null,
                    'coefficient' => $ss->coefficient,
                    'teacher'     => $teacher?->full_name,
                    'teacher_phone' => $teacher?->phone,
                    'teacher_email' => $teacher?->email,
                ];
            })->sortBy('name')->values();
        }

        return view('parent.subjects.index', compact('studentModel', 'enrollment', 'subjects'));
    }
}
