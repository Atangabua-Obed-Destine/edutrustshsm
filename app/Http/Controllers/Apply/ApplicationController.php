<?php

namespace App\Http\Controllers\Apply;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Form;
use App\Models\Stream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    protected function guard()
    {
        return Auth::guard('applicant');
    }

    /**
     * Dashboard – list applicant's own applications.
     */
    public function dashboard()
    {
        $applicant = $this->guard()->user();
        $applications = $applicant->applications()
            ->with(['form', 'stream', 'academicSession'])
            ->orderByDesc('created_at')
            ->get();

        return view('apply.dashboard', compact('applicant', 'applications'));
    }

    /**
     * New application form.
     */
    public function create()
    {
        $applicant = $this->guard()->user();
        $currentSession = AcademicSession::current();

        if (!$currentSession) {
            return redirect()->route('apply.dashboard')
                ->with('error', __('Admissions are currently closed. No active academic session.'));
        }

        $forms = Form::where('is_active', true)->orderBy('display_order')->get();

        return view('apply.create', compact('applicant', 'currentSession', 'forms'));
    }

    /**
     * Submit the application.
     */
    public function store(Request $request)
    {
        $applicant = $this->guard()->user();
        $currentSession = AcademicSession::current();

        if (!$currentSession) {
            return redirect()->route('apply.dashboard')
                ->with('error', __('Admissions are currently closed.'));
        }

        $formModel = $request->input('form_id') ? Form::find($request->input('form_id')) : null;
        $isSecondCycle = $formModel && $formModel->level === 'second_cycle';
        $hasStreams = $formModel && $formModel->has_streams;

        $validated = $request->validate([
            // Class placement
            'form_id'           => ['required', 'exists:forms,id'],
            'stream_id'         => [$hasStreams ? 'required' : 'nullable', 'exists:streams,id'],
            // Personal info
            'first_name'        => ['required', 'string', 'max:100'],
            'last_name'         => ['required', 'string', 'max:100'],
            'other_names'       => ['nullable', 'string', 'max:100'],
            'date_of_birth'     => ['required', 'date', 'before:today'],
            'gender'            => ['required', 'in:male,female'],
            'blood_group'       => ['nullable', 'string', 'max:5'],
            'nationality'       => ['nullable', 'string', 'max:100'],
            'place_of_birth'    => ['nullable', 'string', 'max:150'],
            'region_of_origin'  => ['nullable', 'string', 'max:100'],
            'religion'          => ['nullable', 'string', 'max:50'],
            // Contact
            'phone'             => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:100'],
            'home_address'      => ['nullable', 'string', 'max:500'],
            'town'              => ['nullable', 'string', 'max:100'],
            // Previous school
            'previous_school'   => ['nullable', 'string', 'max:200'],
            'previous_class'    => ['nullable', 'string', 'max:100'],
            // Family
            'father_name'       => ['nullable', 'string', 'max:150'],
            'father_phone'      => ['nullable', 'string', 'max:20'],
            'father_email'      => ['nullable', 'email', 'max:100'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'father_address'    => ['nullable', 'string', 'max:300'],
            'mother_name'       => ['nullable', 'string', 'max:150'],
            'mother_phone'      => ['nullable', 'string', 'max:20'],
            'mother_email'      => ['nullable', 'email', 'max:100'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'mother_address'    => ['nullable', 'string', 'max:300'],
            'guardian_name'     => ['nullable', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_phone'    => ['nullable', 'string', 'max:20'],
            'guardian_email'    => ['nullable', 'email', 'max:100'],
            'emergency_contact_name'         => ['required', 'string', 'max:150'],
            'emergency_contact_phone'        => ['required', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            // Documents
            'photo_file'                => ['nullable', 'image', 'max:2048'],
            'birth_certificate_file'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'primary_certificate_file'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'gce_ol_certificate_file'   => [$isSecondCycle ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'transfer_certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'medical_certificate_file'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'gce_ol_certificate_file.required' => __('GCE Ordinary Level certificate is required for Second Cycle admission.'),
            'stream_id.required' => __('Please select a stream for this form.'),
        ]);

        // Handle file uploads
        $files = [];
        $fileFields = [
            'photo_file'                => 'applications/photos',
            'birth_certificate_file'    => 'applications/documents',
            'primary_certificate_file'  => 'applications/documents',
            'gce_ol_certificate_file'   => 'applications/documents',
            'transfer_certificate_file' => 'applications/documents',
            'medical_certificate_file'  => 'applications/documents',
        ];
        foreach ($fileFields as $field => $path) {
            if ($request->hasFile($field)) {
                $files[str_replace('_file', '', $field)] = $request->file($field)->store($path, $path === 'applications/photos' ? 'public' : 'local');
            }
        }

        $application = AdmissionApplication::create([
            'applicant_id'        => $applicant->id,
            'application_number'  => AdmissionApplication::generateApplicationNumber(),
            'academic_session_id' => $currentSession->id,
            'form_id'             => $validated['form_id'],
            'stream_id'           => $validated['stream_id'] ?? null,
            // Personal
            'first_name'          => $validated['first_name'],
            'last_name'           => $validated['last_name'],
            'other_names'         => $validated['other_names'] ?? null,
            'date_of_birth'       => $validated['date_of_birth'],
            'gender'              => $validated['gender'],
            'blood_group'         => $validated['blood_group'] ?? null,
            'nationality'         => $validated['nationality'] ?? 'Cameroonian',
            'place_of_birth'      => $validated['place_of_birth'] ?? null,
            'region_of_origin'    => $validated['region_of_origin'] ?? null,
            'religion'            => $validated['religion'] ?? null,
            // Contact
            'phone'               => $validated['phone'] ?? null,
            'email'               => $validated['email'] ?? null,
            'home_address'        => $validated['home_address'] ?? null,
            'town'                => $validated['town'] ?? null,
            // Previous school
            'previous_school'     => $validated['previous_school'] ?? null,
            'previous_class'      => $validated['previous_class'] ?? null,
            // Family
            'father_name'         => $validated['father_name'] ?? null,
            'father_phone'        => $validated['father_phone'] ?? null,
            'father_email'        => $validated['father_email'] ?? null,
            'father_occupation'   => $validated['father_occupation'] ?? null,
            'father_address'      => $validated['father_address'] ?? null,
            'mother_name'         => $validated['mother_name'] ?? null,
            'mother_phone'        => $validated['mother_phone'] ?? null,
            'mother_email'        => $validated['mother_email'] ?? null,
            'mother_occupation'   => $validated['mother_occupation'] ?? null,
            'mother_address'      => $validated['mother_address'] ?? null,
            'guardian_name'       => $validated['guardian_name'] ?? null,
            'guardian_relationship' => $validated['guardian_relationship'] ?? null,
            'guardian_phone'      => $validated['guardian_phone'] ?? null,
            'guardian_email'      => $validated['guardian_email'] ?? null,
            'emergency_contact_name'         => $validated['emergency_contact_name'],
            'emergency_contact_phone'        => $validated['emergency_contact_phone'],
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
            // Documents
            'photo'               => $files['photo'] ?? null,
            'birth_certificate'   => $files['birth_certificate'] ?? null,
            'primary_certificate' => $files['primary_certificate'] ?? null,
            'gce_ol_certificate'  => $files['gce_ol_certificate'] ?? null,
            'transfer_certificate' => $files['transfer_certificate'] ?? null,
            'medical_certificate' => $files['medical_certificate'] ?? null,
            // Status
            'status'              => 'pending',
        ]);

        return redirect()->route('apply.show', $application)
            ->with('success', __('Application submitted successfully! Your application number is :number.', ['number' => $application->application_number]));
    }

    /**
     * View a single application.
     */
    public function show(AdmissionApplication $application)
    {
        $applicant = $this->guard()->user();

        if ($application->applicant_id !== $applicant->id) {
            abort(403);
        }

        $application->load(['form', 'stream', 'academicSession']);

        return view('apply.show', compact('applicant', 'application'));
    }

    /**
     * AJAX: Get streams for a form.
     */
    public function getFormStreams(Form $form)
    {
        if (!$form->has_streams) {
            return response()->json([]);
        }

        $streams = $form->streams()->where('is_active', true)->orderBy('name')->get(['streams.id', 'streams.name']);
        return response()->json($streams);
    }
}
