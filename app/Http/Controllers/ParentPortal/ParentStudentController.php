<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\StudentFee;
use App\Support\ParentContext;

class ParentStudentController extends Controller
{
    /** Overview / profile page for one child (also sets the active selection). */
    public function show(int $student)
    {
        $studentModel = ParentContext::authorizeStudent($student);

        $studentModel->load([
            'guardian',
            'currentEnrollment.classSection.form',
            'currentEnrollment.classSection.classTeacher',
            'currentEnrollment.stream',
            'currentEnrollment.academicSession',
        ]);

        $enrollment = $studentModel->currentEnrollment;

        // Quick fee snapshot for the header cards.
        $feeBalance = 0;
        $subjectCount = 0;
        if ($enrollment) {
            $feeBalance = StudentFee::where('student_enrollment_id', $enrollment->id)->sum('balance');
            $subjectCount = $enrollment->studentSubjects()->count();
        }

        // Enrollment history (lifecycle).
        $history = $studentModel->enrollments()
            ->with(['academicSession', 'classSection.form', 'stream'])
            ->get();

        return view('parent.student.show', compact(
            'studentModel', 'enrollment', 'feeBalance', 'subjectCount', 'history'
        ));
    }
}
