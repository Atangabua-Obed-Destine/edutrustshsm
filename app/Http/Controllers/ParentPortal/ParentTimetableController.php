<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Support\ParentContext;

class ParentTimetableController extends Controller
{
    protected array $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

    public function index(int $student)
    {
        $studentModel = ParentContext::authorizeStudent($student);
        $enrollment = $studentModel->currentEnrollment;

        $slots = collect();
        $entriesByDay = collect();

        if ($enrollment) {
            $slots = TimetableSlot::orderBy('period_number')->get();

            $entriesByDay = TimetableEntry::where('class_section_id', $enrollment->class_section_id)
                ->where('academic_session_id', $enrollment->academic_session_id)
                ->with(['subject', 'teacher', 'room'])
                ->get()
                ->groupBy('day_of_week');
        }

        return view('parent.timetable.index', [
            'studentModel' => $studentModel,
            'enrollment'   => $enrollment,
            'slots'        => $slots,
            'entriesByDay' => $entriesByDay,
            'days'         => $this->days,
        ]);
    }
}
