<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectEnrollmentController extends Controller
{
    public function index()
    {
        $forms = Form::withCount('streams')
            ->forCurrentLevel()
            ->ordered()
            ->get();

        // Count unique enrolled subjects per form
        $enrollmentCounts = DB::table('form_subject')
            ->selectRaw('form_id, COUNT(DISTINCT subject_id) as total')
            ->groupBy('form_id')
            ->pluck('total', 'form_id');

        // Count enrollments per form per stream
        $streamEnrollments = DB::table('form_subject')
            ->selectRaw('form_id, stream_id, COUNT(*) as total')
            ->groupBy('form_id', 'stream_id')
            ->get()
            ->groupBy('form_id');

        return view('admin.subject-enrollments.index', compact('forms', 'enrollmentCounts', 'streamEnrollments'));
    }

    public function configure(Form $form)
    {
        $streams = $form->streams()
            ->where('streams.is_active', true)
            ->orderBy('streams.name')
            ->get();

        $subjects = Subject::with('department')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // All enrollments for this form across all streams
        $enrollments = DB::table('form_subject')
            ->where('form_id', $form->id)
            ->get();

        // Teachers scheduled per subject in this form (from timetable)
        $subjectTeachers = DB::table('timetable_entries')
            ->join('class_sections', 'timetable_entries.class_section_id', '=', 'class_sections.id')
            ->join('users', 'timetable_entries.teacher_id', '=', 'users.id')
            ->where('class_sections.form_id', $form->id)
            ->where('users.is_active', true)
            ->select('timetable_entries.subject_id', 'users.id as teacher_id', 'users.first_name', 'users.last_name')
            ->distinct()
            ->get()
            ->groupBy('subject_id')
            ->map(function ($group) {
                return $group->map(function ($t) {
                    return ['id' => $t->teacher_id, 'name' => trim($t->first_name . ' ' . $t->last_name)];
                })->sortBy('name')->values();
            });

        // Pre-map for JSON output in view (avoids arrow functions in Blade)
        $streamsJson = $streams->map(function ($s) {
            return ['id' => $s->id, 'name' => $s->name, 'code' => $s->code, 'is_general' => $s->is_general];
        });
        $subjectsJson = $subjects->map(function ($s) {
            return ['id' => $s->id, 'name' => $s->name];
        });

        return view('admin.subject-enrollments.configure', compact('form', 'streams', 'subjects', 'enrollments', 'subjectTeachers', 'streamsJson', 'subjectsJson'));
    }

    public function save(Request $request, Form $form)
    {
        $request->validate([
            'stream_id' => ['nullable', 'integer', 'exists:streams,id'],
            'subjects' => ['present', 'array'],
            'subjects.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'subjects.*.coefficient' => ['required', 'numeric', 'min:0.5', 'max:9.9'],
            'subjects.*.type' => ['required', 'in:core,elective'],
            'subjects.*.subject_master_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $streamId = $request->input('stream_id');
        $subjectsData = $request->input('subjects', []);

        DB::transaction(function () use ($form, $streamId, $subjectsData) {
            // Delete existing enrollments for this form + stream
            $query = DB::table('form_subject')->where('form_id', $form->id);
            if ($streamId) {
                $query->where('stream_id', $streamId);
            } else {
                $query->whereNull('stream_id');
            }
            $query->delete();

            // Batch insert new enrollments
            $rows = [];
            foreach ($subjectsData as $item) {
                $rows[] = [
                    'form_id' => $form->id,
                    'subject_id' => $item['subject_id'],
                    'stream_id' => $streamId,
                    'coefficient' => $item['coefficient'],
                    'type' => $item['type'],
                    'subject_master_id' => $item['subject_master_id'] ?? null,
                ];
            }

            if (!empty($rows)) {
                DB::table('form_subject')->insert($rows);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Subject enrollment saved successfully.',
            'count' => count($subjectsData),
        ]);
    }
}
