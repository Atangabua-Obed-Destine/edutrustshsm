<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IdCardController extends Controller
{
    /**
     * Show the ID card generator page with filters.
     */
    public function index(Request $request)
    {
        $currentSession = AcademicSession::where('is_current', true)->first();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $forms = Form::forCurrentLevel()->orderBy('level')->get();

        $sessionId = $request->input('academic_session_id', $currentSession?->id);
        $formId = $request->input('form_id');
        $classSectionId = $request->input('class_section_id');
        $search = $request->input('search');

        $classSections = collect();
        if ($formId) {
            $classSections = ClassSection::where('form_id', $formId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get();
        }

        $enrollments = collect();
        $selectedIds = $request->input('selected', []);

        if ($sessionId && ($formId || $classSectionId || $search)) {
            $query = StudentEnrollment::where('academic_session_id', $sessionId)
                ->where('status', 'active')
                ->with(['student', 'classSection.form', 'stream', 'academicSession']);

            if ($classSectionId) {
                $query->where('class_section_id', $classSectionId);
            } elseif ($formId) {
                $sectionIds = ClassSection::where('form_id', $formId)->pluck('id');
                $query->whereIn('class_section_id', $sectionIds);
            }

            if ($search) {
                $query->whereHas('student', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('student_id', 'like', "%{$search}%");
                });
            }

            $enrollments = $query->get()->sortBy(fn ($e) => $e->student->last_name);
        }

        return view('admin.id-cards.index', compact(
            'sessions', 'currentSession', 'forms', 'classSections',
            'enrollments', 'sessionId', 'formId', 'classSectionId', 'search', 'selectedIds'
        ));
    }

    /**
     * Load class sections for a given form (AJAX).
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
     * Generate printable ID cards for the selected students.
     */
    public function print(Request $request)
    {
        $validated = $request->validate([
            'enrollment_ids'   => 'required|array|min:1',
            'enrollment_ids.*' => 'exists:student_enrollments,id',
        ]);

        $enrollments = StudentEnrollment::whereIn('id', $validated['enrollment_ids'])
            ->with(['student.guardian', 'classSection.form', 'stream', 'academicSession'])
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name);

        $settings = SchoolSetting::current();

        return view('admin.id-cards.print', compact('enrollments', 'settings'));
    }

    /**
     * Update a student's photo via AJAX.
     */
    public function updatePhoto(Request $request, Student $student)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Delete old photo if exists
        if ($student->photo && Storage::disk('public')->exists($student->photo)) {
            Storage::disk('public')->delete($student->photo);
        }

        $path = $request->file('photo')->store('students/photos', 'public');
        $student->update(['photo' => $path]);

        return response()->json([
            'success' => true,
            'photo_url' => asset('storage/' . $path),
        ]);
    }
}
