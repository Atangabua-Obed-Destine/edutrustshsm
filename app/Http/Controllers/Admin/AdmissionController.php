<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Batch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Stream;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdmissionController extends Controller
{
    /**
     * Applications list with filters.
     */
    public function index(Request $request)
    {
        $query = AdmissionApplication::with(['applicant', 'form', 'stream', 'academicSession']);

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by form
        if ($formId = $request->input('form_id')) {
            $query->where('form_id', $formId);
        }

        // Filter by stream
        if ($streamId = $request->input('stream_id')) {
            $query->where('stream_id', $streamId);
        }

        // Search by application number or name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by academic session
        if ($sessionId = $request->input('academic_session_id')) {
            $query->where('academic_session_id', $sessionId);
        }

        $applications = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $streams = Stream::where('is_active', true)->orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('is_current')->orderByDesc('start_date')->get();
        $currentSession = AcademicSession::current();

        // Stats (scoped to selected session or all)
        $statsQuery = AdmissionApplication::query();
        if ($sessionId) {
            $statsQuery->where('academic_session_id', $sessionId);
        }
        $stats = [
            'total'        => (clone $statsQuery)->count(),
            'pending'      => (clone $statsQuery)->where('status', 'pending')->count(),
            'under_review' => (clone $statsQuery)->where('status', 'under_review')->count(),
            'accepted'     => (clone $statsQuery)->where('status', 'accepted')->count(),
            'rejected'     => (clone $statsQuery)->where('status', 'rejected')->count(),
            'enrolled'     => (clone $statsQuery)->where('status', 'enrolled')->count(),
        ];

        return view('admin.admissions.index', compact('applications', 'forms', 'streams', 'sessions', 'currentSession', 'stats'));
    }

    /**
     * Review a single application.
     */
    public function show(AdmissionApplication $application)
    {
        $application->load(['applicant', 'form', 'stream', 'academicSession', 'reviewer', 'student']);

        // Data needed if admin wants to enroll
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $streams = Stream::where('is_active', true)->orderBy('name')->get();
        $batches = Batch::where('is_active', true)->orderBy('name')->get();
        $classSections = $application->form_id
            ? ClassSection::where('form_id', $application->form_id)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $terms = Term::orderBy('term_number')->get();

        return view('admin.admissions.show', compact('application', 'forms', 'streams', 'batches', 'classSections', 'terms'));
    }

    /**
     * Update the status (under_review, etc.).
     */
    public function updateStatus(Request $request, AdmissionApplication $application)
    {
        $validated = $request->validate([
            'status'      => ['required', 'in:pending,under_review'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->update([
            'status'      => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Application status updated to :status.', ['status' => str_replace('_', ' ', $validated['status'])]));
    }

    /**
     * Accept the application.
     */
    public function accept(Request $request, AdmissionApplication $application)
    {
        if ($application->status === 'enrolled') {
            return back()->with('error', __('This application has already been enrolled.'));
        }

        $application->update([
            'status'      => 'accepted',
            'admin_notes' => $request->input('admin_notes', $application->admin_notes),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Application accepted. You can now enrol this student.'));
    }

    /**
     * Reject the application.
     */
    public function reject(Request $request, AdmissionApplication $application)
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'max:2000'],
        ]);

        $application->update([
            'status'      => 'rejected',
            'admin_notes' => $validated['admin_notes'],
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('Application has been rejected.'));
    }

    /**
     * AJAX: Get sections for a form.
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
     * AJAX: Get streams for a form.
     */
    public function streamsByForm(Form $form)
    {
        if (!$form->has_streams) {
            return response()->json([]);
        }

        $streams = $form->streams()->where('is_active', true)->orderBy('name')->get(['streams.id', 'streams.name']);
        return response()->json($streams);
    }

    /**
     * Enrol an accepted application → creates Student + Enrollment.
     */
    public function enrol(Request $request, AdmissionApplication $application)
    {
        if (!in_array($application->status, ['accepted'])) {
            return back()->with('error', __('Only accepted applications can be enrolled.'));
        }

        // Admin may override form/stream placement
        $overrideFormId = $request->input('form_id', $application->form_id);
        $formModel = Form::find($overrideFormId);
        $hasStreams = $formModel && $formModel->has_streams;

        $validated = $request->validate([
            'form_id'           => ['required', 'exists:forms,id'],
            'stream_id'         => [$hasStreams ? 'required' : 'nullable', 'exists:streams,id'],
            'batch_id'          => ['required', 'exists:batches,id'],
            'class_section_id'  => ['required', 'exists:class_sections,id'],
            'term_id'           => ['required', 'exists:terms,id'],
            'residence_type'    => ['required', 'in:day,boarding,half_boarding'],
            'admission_date'    => ['required', 'date'],
        ]);

        $student = DB::transaction(function () use ($application, $validated) {
            // 1. Resolve guardian — reuse an existing parent (matched by email
            //    in this branch) so siblings share ONE guardian account.
            $guardian = app(\App\Services\GuardianResolver::class)->resolve([
                'father_name'       => $application->father_name,
                'father_phone'      => $application->father_phone,
                'father_email'      => $application->father_email,
                'father_occupation' => $application->father_occupation,
                'father_address'    => $application->father_address,
                'mother_name'       => $application->mother_name,
                'mother_phone'      => $application->mother_phone,
                'mother_email'      => $application->mother_email,
                'mother_occupation' => $application->mother_occupation,
                'mother_address'    => $application->mother_address,
                'guardian_name'     => $application->guardian_name,
                'guardian_relationship' => $application->guardian_relationship,
                'guardian_phone'    => $application->guardian_phone,
                'guardian_email'    => $application->guardian_email,
                'emergency_contact_name'         => $application->emergency_contact_name,
                'emergency_contact_phone'        => $application->emergency_contact_phone,
                'emergency_contact_relationship' => $application->emergency_contact_relationship,
            ]);

            // 2. Generate student ID
            $settings = SchoolSetting::current();
            $schoolCode = $settings->school_code ?? 'SCH';
            $batch = Batch::findOrFail($validated['batch_id']);
            $prefix = $schoolCode . $batch->shortcode;

            $lastStudent = Student::where('student_id', 'like', $prefix . '%')
                ->orderByDesc('student_id')
                ->first();

            $nextNumber = 1;
            if ($lastStudent) {
                $nextNumber = ((int) substr($lastStudent->student_id, strlen($prefix))) + 1;
            }
            $studentId = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 3. Create student from application data
            $student = Student::create([
                'student_id'          => $studentId,
                'batch_id'            => $validated['batch_id'],
                'first_name'          => $application->first_name,
                'last_name'           => $application->last_name,
                'other_names'         => $application->other_names,
                'date_of_birth'       => $application->date_of_birth,
                'gender'              => $application->gender,
                'blood_group'         => $application->blood_group,
                'nationality'         => $application->nationality,
                'place_of_birth'      => $application->place_of_birth,
                'region_of_origin'    => $application->region_of_origin,
                'religion'            => $application->religion,
                'phone'               => $application->phone,
                'email'               => $application->email,
                'home_address'        => $application->home_address,
                'town'                => $application->town,
                'previous_school'     => $application->previous_school,
                'previous_class'      => $application->previous_class,
                'guardian_id'         => $guardian->id,
                'photo'               => $application->photo,
                'birth_certificate'   => $application->birth_certificate,
                'primary_certificate' => $application->primary_certificate,
                'gce_ol_certificate'  => $application->gce_ol_certificate,
                'transfer_certificate' => $application->transfer_certificate,
                'medical_certificate' => $application->medical_certificate,
                'status'              => 'active',
                'admission_date'      => $validated['admission_date'],
            ]);

            // 4. Create enrollment
            $enrollFormId = $validated['form_id'];
            $enrollStreamId = $validated['stream_id'] ?? null;
            $currentSession = AcademicSession::current();
            if ($currentSession) {
                StudentEnrollment::create([
                    'student_id'          => $student->id,
                    'academic_session_id' => $currentSession->id,
                    'term_id'             => $validated['term_id'],
                    'class_section_id'    => $validated['class_section_id'],
                    'stream_id'           => $enrollStreamId,
                    'residence_type'      => $validated['residence_type'],
                    'enrollment_date'     => $validated['admission_date'],
                    'status'              => 'active',
                ]);
            }

            // 5. Update application with final placement & mark enrolled
            $application->update([
                'status'      => 'enrolled',
                'form_id'     => $enrollFormId,
                'stream_id'   => $enrollStreamId,
                'student_id'  => $student->id,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            return $student;
        });

        return redirect()->route('admin.admissions.applications.show', $application)
            ->with('success', __('Student :name enrolled successfully with ID :id.', [
                'name' => $student->full_name,
                'id'   => $student->student_id,
            ]));
    }
}
