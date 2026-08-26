<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Room;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\TimetableEntry;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    /**
     * Manage period/slot definitions.
     */
    public function slots()
    {
        $slots = TimetableSlot::orderBy('period_number')->get();
        return view('admin.routines.slots', compact('slots'));
    }

    public function storeSlot(Request $request)
    {
        $request->validate([
            'period_number' => 'required|integer|min:1|unique:timetable_slots,period_number',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'type' => 'required|in:teaching,break,lunch',
            'label' => 'nullable|string|max:50',
        ]);

        TimetableSlot::create($request->only('period_number', 'start_time', 'end_time', 'type', 'label'));

        return redirect()->route('admin.timetable.slots')->with('success', 'Period slot added successfully.');
    }

    public function updateSlot(Request $request, TimetableSlot $slot)
    {
        $request->validate([
            'period_number' => 'required|integer|min:1|unique:timetable_slots,period_number,' . $slot->id,
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'type' => 'required|in:teaching,break,lunch',
            'label' => 'nullable|string|max:50',
        ]);

        $slot->update($request->only('period_number', 'start_time', 'end_time', 'type', 'label'));

        return redirect()->route('admin.timetable.slots')->with('success', 'Period slot updated.');
    }

    public function destroySlot(TimetableSlot $slot)
    {
        $slot->delete();
        return redirect()->route('admin.timetable.slots')->with('success', 'Period slot deleted.');
    }

    /**
     * Class schedule – Add/Edit weekly timetable for a class section.
     */
    public function classSchedule(Request $request)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $subjects = Subject::where('is_active', true)->orderBy('name')->get();
        $teachers = User::whereIn('role', ['teacher', 'admin', 'super_admin'])->where('is_active', true)->orderBy('last_name')->get();
        $rooms = Room::where('is_active', true)->orderBy('name')->get();
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $activeDay = $request->input('day', 'monday');

        $sessionId = $request->input('academic_session_id');
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');

        $classSections = collect();
        $entries = collect();
        $selectedSection = null;
        $filtered = false;

        if ($formId) {
            $classSections = ClassSection::where('form_id', $formId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        if ($classSectionId && $sessionId) {
            $filtered = true;
            $selectedSection = ClassSection::with('form')->find($classSectionId);
            $entries = TimetableEntry::where('class_section_id', $classSectionId)
                ->where('academic_session_id', $sessionId)
                ->with(['subject', 'teacher', 'room'])
                ->orderBy('start_time')
                ->get()
                ->groupBy('day_of_week');
        }

        // Default to current session if none selected
        if (!$sessionId) {
            $current = AcademicSession::current();
            $sessionId = $current ? $current->id : null;
        }

        return view('admin.routines.class-schedule', compact(
            'sessions', 'forms', 'classSections', 'subjects', 'teachers', 'rooms',
            'days', 'activeDay', 'sessionId', 'formId', 'classSectionId',
            'entries', 'selectedSection', 'filtered'
        ));
    }

    /**
     * AJAX – get sections for a given form.
     */
    public function getSectionsByForm(Form $form)
    {
        $sections = ClassSection::where('form_id', $form->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($sections);
    }

    /**
     * Save all timetable entries for a specific day.
     */
    public function saveDaySchedule(Request $request)
    {
        $request->validate([
            'class_section_id' => 'required|exists:class_sections,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday',
            'entries' => 'nullable|array',
            'entries.*.subject_id' => 'required|exists:subjects,id',
            'entries.*.teacher_id' => 'required|exists:users,id',
            'entries.*.room_id' => 'nullable|exists:rooms,id',
            'entries.*.start_time' => 'required|date_format:H:i',
            'entries.*.end_time' => 'required|date_format:H:i|after:entries.*.start_time',
        ]);

        $sessionId = $request->academic_session_id;
        $sectionId = $request->class_section_id;
        $day = $request->day_of_week;

        // Validate for teacher time-overlap conflicts before making changes
        if ($request->entries) {
            foreach ($request->entries as $entry) {
                $conflict = TimetableEntry::where('academic_session_id', $sessionId)
                    ->where('teacher_id', $entry['teacher_id'])
                    ->where('day_of_week', $day)
                    ->where('class_section_id', '!=', $sectionId)
                    ->where('start_time', '<', $entry['end_time'])
                    ->where('end_time', '>', $entry['start_time'])
                    ->first();

                if ($conflict) {
                    $teacher = User::find($entry['teacher_id']);
                    return redirect()->route('admin.timetable.class-schedule', [
                        'academic_session_id' => $sessionId,
                        'form_id' => $request->form_id,
                        'class_section_id' => $sectionId,
                        'day' => $day,
                    ])->with('error', "Teacher {$teacher->full_name} is already assigned to another class on " . ucfirst($day) . " from " . \Carbon\Carbon::parse($conflict->start_time)->format('g:i A') . " to " . \Carbon\Carbon::parse($conflict->end_time)->format('g:i A') . " (overlaps with your {$entry['start_time']}–{$entry['end_time']}).");
                }
            }
        }

        DB::transaction(function () use ($sessionId, $sectionId, $day, $request) {
            // Delete existing entries for this day+section+session
            TimetableEntry::where('academic_session_id', $sessionId)
                ->where('class_section_id', $sectionId)
                ->where('day_of_week', $day)
                ->delete();

            // Create new entries
            if ($request->entries) {
                foreach ($request->entries as $entry) {
                    TimetableEntry::create([
                        'academic_session_id' => $sessionId,
                        'class_section_id' => $sectionId,
                        'day_of_week' => $day,
                        'subject_id' => $entry['subject_id'],
                        'teacher_id' => $entry['teacher_id'],
                        'room_id' => $entry['room_id'] ?: null,
                        'start_time' => $entry['start_time'],
                        'end_time' => $entry['end_time'],
                    ]);
                }
            }
        });

        $section = ClassSection::find($sectionId);

        return redirect()->route('admin.timetable.class-schedule', [
            'academic_session_id' => $sessionId,
            'form_id' => $section->form_id,
            'class_section_id' => $sectionId,
            'day' => $day,
        ])->with('success', 'Schedule for ' . ucfirst($day) . ' saved successfully.');
    }

    public function destroyEntry(TimetableEntry $entry)
    {
        $classSectionId = $entry->class_section_id;
        $entry->delete();
        return redirect()->route('admin.timetable.class-schedule', ['class_section_id' => $classSectionId])
            ->with('success', 'Entry removed.');
    }

    /**
     * Teacher schedule – weekly timetable for a specific teacher.
     */
    public function teacherSchedule(Request $request)
    {
        $currentSession = AcademicSession::current();
        $teachers = User::whereIn('role', ['teacher', 'admin', 'super_admin'])->where('is_active', true)->orderBy('last_name')->get();
        $teacherId = $request->input('teacher_id');
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $entries = collect();
        $selectedTeacher = null;

        if ($teacherId && $currentSession) {
            $selectedTeacher = User::find($teacherId);
            $entries = TimetableEntry::where('teacher_id', $teacherId)
                ->where('academic_session_id', $currentSession->id)
                ->with(['subject', 'classSection.form', 'room'])
                ->orderBy('start_time')
                ->get()
                ->groupBy('day_of_week');
        }

        return view('admin.routines.teacher-schedule', compact(
            'teachers', 'teacherId', 'days', 'entries', 'selectedTeacher', 'currentSession'
        ));
    }
}
