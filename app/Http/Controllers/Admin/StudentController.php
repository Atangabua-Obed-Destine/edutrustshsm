<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'student';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('student.view', ['getFormStreams', 'getFormSections']),
        ];
    }

    public function index(Request $request)
    {
        $currentSession = AcademicSession::current();
        $query = Student::with(['guardian', 'currentEnrollment.classSection.form']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by class
        if ($classId = $request->input('class_section_id')) {
            $query->whereHas('currentEnrollment', fn ($q) => $q->where('class_section_id', $classId));
        }

        // Scope to the active school level (via the student's current class's form)
        $schoolLevel = \App\Support\LevelContext::current();
        $query->whereHas('currentEnrollment.classSection.form', fn ($q) => $q->where('school_level', $schoolLevel));

        $students = $query->orderBy('last_name')->orderBy('first_name')->paginate(25)->withQueryString();

        $classSections = ClassSection::where('is_active', true)
            ->whereHas('form', fn ($q) => $q->where('school_level', $schoolLevel))
            ->with('form')->orderBy('name')->get();

        return view('admin.students.index', compact('students', 'classSections', 'currentSession'));
    }

    public function create()
    {
        $currentSession = AcademicSession::current();
        $settings = SchoolSetting::current();
        $batches = Batch::where('is_active', true)->orderBy('name')->get();
        $forms = Form::active()->forCurrentLevel()->ordered()->get();
        $terms = Term::orderBy('term_number')->get();

        return view('admin.students.create', compact(
            'currentSession', 'settings', 'batches', 'forms', 'terms'
        ));
    }

    public function store(Request $request)
    {
        // Determine cycle for conditional document validation
        $formModel = $request->input('form_id') ? Form::find($request->input('form_id')) : null;
        $isSecondCycle = $formModel && $formModel->level === 'second_cycle';
        $hasStreams = $formModel && $formModel->has_streams;

        $validated = $request->validate([
            // Tab 1: Admission & Class
            'batch_id'          => ['required', 'exists:batches,id'],
            'admission_date'    => ['required', 'date'],
            'form_id'           => ['required', 'exists:forms,id'],
            'stream_id'         => [$hasStreams ? 'required' : 'nullable', 'exists:streams,id'],
            'class_section_id'  => ['required', 'exists:class_sections,id'],
            'term_id'           => ['required', 'exists:terms,id'],
            'residence_type'    => ['required', 'in:day,boarding,half_boarding'],
            // Tab 2: Personal Information
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
            // Tab 3: Contact & Address
            'phone'             => ['nullable', 'string', 'max:20'],
            'email'             => ['nullable', 'email', 'max:100'],
            'home_address'      => ['nullable', 'string', 'max:500'],
            'town'              => ['nullable', 'string', 'max:100'],
            'previous_school'   => ['nullable', 'string', 'max:200'],
            'previous_class'    => ['nullable', 'string', 'max:100'],
            // Tab 4: Family & Guardians
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
            // Tab 5: Documents
            'photo_file'                => ['nullable', 'image', 'max:2048'],
            'birth_certificate_file'    => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'primary_certificate_file'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'gce_ol_certificate_file'   => [$isSecondCycle ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'transfer_certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'medical_certificate_file'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'gce_ol_certificate_file.required' => 'GCE Ordinary Level certificate is required for Second Cycle admission.',
            'stream_id.required' => 'Please select a stream for this form.',
        ]);

        $student = DB::transaction(function () use ($validated, $request) {
            // 1. Resolve guardian record — reuse an existing parent (matched by
            //    email in this branch) so siblings share ONE guardian account.
            $guardian = app(\App\Services\GuardianResolver::class)->resolve([
                'father_name'       => $validated['father_name'] ?? null,
                'father_phone'      => $validated['father_phone'] ?? null,
                'father_email'      => $validated['father_email'] ?? null,
                'father_occupation' => $validated['father_occupation'] ?? null,
                'father_address'    => $validated['father_address'] ?? null,
                'mother_name'       => $validated['mother_name'] ?? null,
                'mother_phone'      => $validated['mother_phone'] ?? null,
                'mother_email'      => $validated['mother_email'] ?? null,
                'mother_occupation' => $validated['mother_occupation'] ?? null,
                'mother_address'    => $validated['mother_address'] ?? null,
                'guardian_name'     => $validated['guardian_name'] ?? null,
                'guardian_relationship' => $validated['guardian_relationship'] ?? null,
                'guardian_phone'    => $validated['guardian_phone'] ?? null,
                'guardian_email'    => $validated['guardian_email'] ?? null,
                'emergency_contact_name'         => $validated['emergency_contact_name'],
                'emergency_contact_phone'        => $validated['emergency_contact_phone'],
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
            ]);

            // 2. Generate student ID: SchoolCode + BatchShortcode + Sequential(0001)
            $settings = SchoolSetting::current();
            $schoolCode = $settings->school_code ?? 'SCH';
            $batch = Batch::findOrFail($validated['batch_id']);
            $prefix = $schoolCode . $batch->shortcode;

            $lastStudent = Student::where('student_id', 'like', $prefix . '%')
                ->orderByDesc('student_id')
                ->first();

            $nextNumber = 1;
            if ($lastStudent) {
                $numericPart = substr($lastStudent->student_id, strlen($prefix));
                $nextNumber = ((int) $numericPart) + 1;
            }
            $studentId = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            // 3. Handle file uploads
            $photoPath = $request->hasFile('photo_file')
                ? $request->file('photo_file')->store('students/photos', 'public') : null;
            $birthCertPath = $request->hasFile('birth_certificate_file')
                ? $request->file('birth_certificate_file')->store('students/documents', 'local') : null;
            $primaryCertPath = $request->hasFile('primary_certificate_file')
                ? $request->file('primary_certificate_file')->store('students/documents', 'local') : null;
            $gceOlCertPath = $request->hasFile('gce_ol_certificate_file')
                ? $request->file('gce_ol_certificate_file')->store('students/documents', 'local') : null;
            $transferCertPath = $request->hasFile('transfer_certificate_file')
                ? $request->file('transfer_certificate_file')->store('students/documents', 'local') : null;
            $medicalCertPath = $request->hasFile('medical_certificate_file')
                ? $request->file('medical_certificate_file')->store('students/documents', 'local') : null;

            // 4. Create student record
            $student = Student::create([
                'student_id'          => $studentId,
                'batch_id'            => $validated['batch_id'],
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
                'phone'               => $validated['phone'] ?? null,
                'email'               => $validated['email'] ?? null,
                'home_address'        => $validated['home_address'] ?? null,
                'town'                => $validated['town'] ?? null,
                'previous_school'     => $validated['previous_school'] ?? null,
                'previous_class'      => $validated['previous_class'] ?? null,
                'guardian_id'         => $guardian->id,
                'photo'               => $photoPath,
                'birth_certificate'   => $birthCertPath,
                'primary_certificate' => $primaryCertPath,
                'gce_ol_certificate'  => $gceOlCertPath,
                'transfer_certificate' => $transferCertPath,
                'medical_certificate' => $medicalCertPath,
                'status'              => 'active',
                'admission_date'      => $validated['admission_date'],
            ]);

            // 5. Create enrollment for current session
            $currentSession = AcademicSession::current();
            if ($currentSession) {
                $enrollment = StudentEnrollment::create([
                    'student_id'          => $student->id,
                    'academic_session_id' => $currentSession->id,
                    'term_id'             => $validated['term_id'],
                    'class_section_id'    => $validated['class_section_id'],
                    'stream_id'           => $validated['stream_id'] ?? null,
                    'residence_type'      => $validated['residence_type'],
                    'enrollment_date'     => $validated['admission_date'],
                    'status'              => 'active',
                ]);

                // Register the form's core subjects, or the student arrives
                // enrolled in a class but studying nothing.
                $enrollment->syncCoreSubjects();
            }

            return $student;
        });

        return redirect()->route('admin.students.show', $student)
            ->with('success', "Student registered successfully. Student ID: {$student->student_id}");
    }

    public function show(Student $student)
    {
        $student->load([
            'batch',
            'guardian',
            'enrollments.academicSession',
            'enrollments.classSection.form',
            'enrollments.term',
            'enrollments.stream',
            'currentEnrollment.academicSession',
            'currentEnrollment.classSection.form',
            'currentEnrollment.term',
            'currentEnrollment.stream',
            'currentEnrollment.studentSubjects.subject',
        ]);

        return view('admin.students.show', compact('student'));
    }

    public function edit(Student $student)
    {
        $student->load(['guardian', 'currentEnrollment']);
        $currentSession = AcademicSession::current();
        $classSections = ClassSection::where('is_active', true)->whereHas('form', fn($q) => $q->forCurrentLevel())->with('form')->orderBy('name')->get();

        return view('admin.students.edit', compact('student', 'classSections', 'currentSession'));
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'religion' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,graduated,withdrawn,suspended,expelled'],
            // Guardian
            'father_name' => ['nullable', 'string', 'max:150'],
            'father_phone' => ['nullable', 'string', 'max:20'],
            'father_email' => ['nullable', 'email', 'max:100'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'father_address' => ['nullable', 'string', 'max:300'],
            'mother_name' => ['nullable', 'string', 'max:150'],
            'mother_phone' => ['nullable', 'string', 'max:20'],
            'mother_email' => ['nullable', 'email', 'max:100'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'mother_address' => ['nullable', 'string', 'max:300'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'string', 'max:20'],
            'guardian_email' => ['nullable', 'email', 'max:100'],
            'emergency_contact_name' => ['required', 'string', 'max:150'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            // Enrollment
            'class_section_id' => ['nullable', 'exists:class_sections,id'],
            'residence_type' => ['nullable', 'in:day,boarding,half_boarding'],
        ]);

        DB::transaction(function () use ($validated, $student) {
            // Update student
            $student->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'other_names' => $validated['other_names'] ?? null,
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'nationality' => $validated['nationality'] ?? 'Cameroonian',
                'place_of_birth' => $validated['place_of_birth'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'status' => $validated['status'],
            ]);

            // Update guardian
            if ($student->guardian) {
                $student->guardian->update([
                    'father_name' => $validated['father_name'] ?? null,
                    'father_phone' => $validated['father_phone'] ?? null,
                    'father_email' => $validated['father_email'] ?? null,
                    'father_occupation' => $validated['father_occupation'] ?? null,
                    'father_address' => $validated['father_address'] ?? null,
                    'mother_name' => $validated['mother_name'] ?? null,
                    'mother_phone' => $validated['mother_phone'] ?? null,
                    'mother_email' => $validated['mother_email'] ?? null,
                    'mother_occupation' => $validated['mother_occupation'] ?? null,
                    'mother_address' => $validated['mother_address'] ?? null,
                    'guardian_name' => $validated['guardian_name'] ?? null,
                    'guardian_relationship' => $validated['guardian_relationship'] ?? null,
                    'guardian_phone' => $validated['guardian_phone'] ?? null,
                    'guardian_email' => $validated['guardian_email'] ?? null,
                    'emergency_contact_name' => $validated['emergency_contact_name'],
                    'emergency_contact_phone' => $validated['emergency_contact_phone'],
                    'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                ]);
            }

            // Update enrollment if class changed
            if ($validated['class_section_id'] ?? null) {
                $enrollment = $student->currentEnrollment;
                if ($enrollment) {
                    // Do NOT derive stream_id from the section: class_sections has no
                    // stream_id column, so this used to write null and silently wipe
                    // the student's stream (breaking subject sync and fee resolution).
                    // The edit form has no stream field, so preserve what's there.
                    $enrollment->update([
                        'class_section_id' => $validated['class_section_id'],
                        'residence_type' => $validated['residence_type'] ?? $enrollment->residence_type,
                    ]);
                }
            }
        });

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'Student updated successfully.');
    }

    // ===== AJAX Endpoints for cascading dropdowns =====

    public function getFormStreams(Form $form)
    {
        return response()->json(
            $form->streams()->where('is_active', true)->get(['streams.id', 'streams.name', 'streams.code'])
        );
    }

    public function getFormSections(Form $form)
    {
        return response()->json(
            ClassSection::where('form_id', $form->id)
                ->where('is_active', true)
                ->orderBy('section')
                ->get(['id', 'name', 'section'])
        );
    }
}
