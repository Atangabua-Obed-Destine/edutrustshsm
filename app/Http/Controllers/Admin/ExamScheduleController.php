<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\ExamSchedule;
use App\Models\Form;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ExamScheduleController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'exam-schedule';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('exam-schedule.view', ['index', 'sectionsByForm', 'subjectsByForm']),
            static::can('exam-schedule.create', ['save']),
        ];
    }

    /**
     * Show exam schedule page with filter bar + entry rows.
     */
    public function index(Request $request)
    {
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $rooms = Room::where('is_active', true)->orderBy('name')->get();
        $teachers = User::whereIn('role', ['teacher', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $sessionId = $request->input('academic_session_id');
        $educationSystem = $request->input('education_system');
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');

        $classSections = collect();
        $entries = collect();
        $selectedSection = null;
        $filtered = false;

        if ($formId) {
            $query = ClassSection::where('form_id', $formId)->where('is_active', true)->orderBy('name');
            $classSections = $query->get();
        }

        // Load subjects enrolled for the selected form (via form_subject pivot)
        if ($formId) {
            $subjects = Subject::where('is_active', true)
                ->whereHas('forms', fn ($q) => $q->where('forms.id', $formId))
                ->orderBy('name')
                ->get();
        } else {
            $subjects = collect();
        }

        if ($classSectionId && $sessionId) {
            $filtered = true;
            $selectedSection = ClassSection::with('form')->find($classSectionId);
            $entries = ExamSchedule::where('class_section_id', $classSectionId)
                ->where('academic_session_id', $sessionId)
                ->with(['subject', 'room', 'invigilators'])
                ->orderBy('exam_date')
                ->orderBy('start_time')
                ->get();
        }

        // Default to current session
        if (!$sessionId) {
            $current = AcademicSession::current();
            $sessionId = $current ? $current->id : null;
        }

        return view('admin.exam-schedules.index', compact(
            'sessions', 'forms', 'classSections', 'subjects', 'teachers', 'rooms',
            'sessionId', 'educationSystem', 'formId', 'classSectionId',
            'entries', 'selectedSection', 'filtered'
        ));
    }

    /**
     * AJAX – get sections for a given form.
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
     * AJAX – get subjects enrolled for a given form (via form_subject pivot).
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
     * Save all exam schedule entries for a class section.
     */
    public function save(Request $request)
    {
        $request->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'class_section_id' => 'required|exists:class_sections,id',
            'entries' => 'nullable|array',
            'entries.*.subject_id' => 'required|exists:subjects,id',
            'entries.*.room_id' => 'nullable|exists:rooms,id',
            'entries.*.exam_date' => 'required|date',
            'entries.*.start_time' => 'required|date_format:H:i',
            'entries.*.end_time' => 'required|date_format:H:i|after:entries.*.start_time',
            'entries.*.invigilator_ids' => 'nullable|array',
            'entries.*.invigilator_ids.*' => 'integer|exists:users,id',
        ]);

        $sessionId = $request->academic_session_id;
        $sectionId = $request->class_section_id;
        $entriesData = $request->input('entries', []);

        // Check for room conflicts: same room, same date, overlapping times
        foreach ($entriesData as $i => $entry) {
            if (empty($entry['room_id'])) continue;

            $conflict = ExamSchedule::where('academic_session_id', $sessionId)
                ->where('room_id', $entry['room_id'])
                ->where('exam_date', $entry['exam_date'])
                ->where('class_section_id', '!=', $sectionId)
                ->where('start_time', '<', $entry['end_time'])
                ->where('end_time', '>', $entry['start_time'])
                ->with(['classSection.form', 'subject'])
                ->first();

            if ($conflict) {
                $room = Room::find($entry['room_id']);
                $section = ClassSection::with('form')->find($sectionId);
                return redirect()->route('admin.exam-schedules.index', [
                    'academic_session_id' => $sessionId,
                    'form_id' => $section->form_id,
                    'class_section_id' => $sectionId,
                ])->with('error', __('Room :room is already booked on :date from :from to :to for :class (:subject).', [
                    'room' => $room->name,
                    'date' => $entry['exam_date'],
                    'from' => $entry['start_time'],
                    'to' => $entry['end_time'],
                    'class' => $conflict->classSection->name,
                    'subject' => $conflict->subject->name,
                ]));
            }
        }

        DB::transaction(function () use ($sessionId, $sectionId, $entriesData) {
            // Delete existing entries for this section + session
            $existingIds = ExamSchedule::where('academic_session_id', $sessionId)
                ->where('class_section_id', $sectionId)
                ->pluck('id');

            // Remove pivot records
            DB::table('exam_schedule_invigilator')
                ->whereIn('exam_schedule_id', $existingIds)
                ->delete();

            ExamSchedule::where('academic_session_id', $sessionId)
                ->where('class_section_id', $sectionId)
                ->delete();

            // Insert new entries
            foreach ($entriesData as $item) {
                $schedule = ExamSchedule::create([
                    'academic_session_id' => $sessionId,
                    'class_section_id' => $sectionId,
                    'subject_id' => $item['subject_id'],
                    'room_id' => $item['room_id'] ?: null,
                    'exam_date' => $item['exam_date'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                ]);

                if (!empty($item['invigilator_ids'])) {
                    $schedule->invigilators()->attach($item['invigilator_ids']);
                }
            }
        });

        $section = ClassSection::find($sectionId);

        return redirect()->route('admin.exam-schedules.index', [
            'academic_session_id' => $sessionId,
            'form_id' => $section->form_id,
            'class_section_id' => $sectionId,
            'education_system' => $request->input('education_system'),
        ])->with('success', __('Exam schedule saved successfully. :count exam(s) scheduled.', ['count' => count($entriesData)]));
    }

    /**
     * Delete a single exam schedule entry via AJAX.
     */
    public function destroy(ExamSchedule $examSchedule)
    {
        $examSchedule->invigilators()->detach();
        $examSchedule->delete();

        return response()->json(['success' => true, 'message' => __('Exam entry deleted.')]);
    }
}
