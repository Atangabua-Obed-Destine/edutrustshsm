<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\ClassSection;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BulkUploadController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'bulk-upload';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('bulk-upload.view', ['index', 'template']),
            static::can('bulk-upload.upload', ['upload']),
        ];
    }

    public function index()
    {
        $currentSession = AcademicSession::current();
        // Class sections are not session-scoped (no academic_session_id column);
        // they belong to a Form and are reused every year.
        $classSections = ClassSection::with('form')
            ->where('is_active', true)
            ->whereHas('form', fn ($q) => $q->forCurrentLevel())
            ->orderBy('name')
            ->get();

        return view('admin.students.bulk-upload', compact('classSections', 'currentSession'));
    }

    public function template()
    {
        $headers = [
            'first_name', 'last_name', 'other_names', 'date_of_birth', 'gender',
            'nationality', 'place_of_birth', 'religion', 'residence_type',
            'phone', 'email', 'home_address',
            'father_name', 'father_phone', 'mother_name', 'mother_phone',
            'emergency_contact_name', 'emergency_contact_phone',
            'previous_school', 'previous_class',
        ];

        $example = [
            'John', 'Doe', 'Eric', '2008-05-15', 'male',
            'Cameroonian', 'Bamenda', 'Christian', 'day',
            '677123456', 'john@example.com', 'Mile 3 Nkwen',
            'Mr. Doe', '677000001', 'Mrs. Doe', '677000002',
            'Mr. Doe', '677000001',
            'GBHS Bamenda', 'Class 6',
        ];

        $csv = implode(',', $headers) . "\n" . implode(',', $example) . "\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_upload_template.csv"',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'class_section_id' => ['required', 'exists:class_sections,id'],
        ]);

        $currentSession = AcademicSession::current();
        if (!$currentSession) {
            return back()->with('error', 'No active academic session found.');
        }

        $currentTerm = Term::where('is_current', true)->first();
        if (!$currentTerm) {
            return back()->with('error', __('No current term is set. Set one before importing students.'));
        }

        $classSection = ClassSection::with('form')->find($request->class_section_id);
        $settings = SchoolSetting::current();
        $prefix = $settings?->student_id_prefix ?? 'LCC';
        $year = date('Y');

        $file = $request->file('csv_file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_map('trim', array_shift($rows));

        $successCount = 0;
        $errors = [];
        $lineNum = 1;

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                $lineNum++;

                if (count($row) < count($headers)) {
                    $row = array_pad($row, count($headers), '');
                }

                $data = array_combine($headers, array_map('trim', array_slice($row, 0, count($headers))));

                $rowValidator = Validator::make($data, [
                    'first_name' => ['required', 'string', 'max:100'],
                    'last_name' => ['required', 'string', 'max:100'],
                    'date_of_birth' => ['required', 'date'],
                    'gender' => ['required', 'in:male,female'],
                    'emergency_contact_name' => ['required', 'string'],
                    'emergency_contact_phone' => ['required', 'string'],
                ]);

                if ($rowValidator->fails()) {
                    $errors[] = "Row {$lineNum}: " . implode(', ', $rowValidator->errors()->all());
                    continue;
                }

                // Generate student ID
                $lastStudent = Student::where('student_id', 'like', "{$prefix}/{$year}/%")
                    ->orderByDesc('student_id')
                    ->first();
                $nextNum = 1;
                if ($lastStudent) {
                    $parts = explode('/', $lastStudent->student_id);
                    $nextNum = (int) end($parts) + 1;
                }
                $studentId = sprintf('%s/%s/%03d', $prefix, $year, $nextNum);

                // Resolve guardian (reuse by email when present; bulk rows usually
                // have no parent email so this typically creates a fresh record).
                $guardian = app(\App\Services\GuardianResolver::class)->resolve([
                    'father_name' => $data['father_name'] ?? null,
                    'father_phone' => $data['father_phone'] ?? null,
                    'father_email' => $data['father_email'] ?? null,
                    'mother_name' => $data['mother_name'] ?? null,
                    'mother_phone' => $data['mother_phone'] ?? null,
                    'mother_email' => $data['mother_email'] ?? null,
                    'guardian_email' => $data['guardian_email'] ?? null,
                    'emergency_contact_name' => $data['emergency_contact_name'],
                    'emergency_contact_phone' => $data['emergency_contact_phone'],
                ]);

                // Create student
                $student = Student::create([
                    'student_id' => $studentId,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'other_names' => $data['other_names'] ?? null,
                    'date_of_birth' => $data['date_of_birth'],
                    'gender' => $data['gender'],
                    'nationality' => $data['nationality'] ?? 'Cameroonian',
                    'place_of_birth' => $data['place_of_birth'] ?? null,
                    'religion' => $data['religion'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'home_address' => $data['home_address'] ?? null,
                    'guardian_id' => $guardian->id,
                    'previous_school' => $data['previous_school'] ?? null,
                    'previous_class' => $data['previous_class'] ?? null,
                    'status' => 'active',
                    'admission_date' => now()->toDateString(),
                ]);

                // Create enrollment
                // term_id is REQUIRED: marks entry, report cards and the report-card
                // listing all filter on it, so an enrollment without one is invisible
                // to the entire assessment pipeline.
                $enrollment = StudentEnrollment::create([
                    'student_id' => $student->id,
                    'academic_session_id' => $currentSession->id,
                    'term_id' => $currentTerm?->id,
                    'class_section_id' => $classSection->id,
                    'residence_type' => in_array($data['residence_type'] ?? '', ['day', 'boarding', 'half_boarding']) ? $data['residence_type'] : 'day',
                    'enrollment_date' => now()->toDateString(),
                    'status' => 'active',
                ]);

                // Register the form's core subjects, or the student arrives
                // enrolled in a class but studying nothing.
                $enrollment->syncCoreSubjects();

                $successCount++;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', "Upload failed at row {$lineNum}: " . $e->getMessage());
        }

        $message = "{$successCount} students imported successfully to {$classSection->name}.";
        if (!empty($errors)) {
            $message .= ' ' . count($errors) . ' rows had errors.';
        }

        return redirect()->route('admin.bulk-upload.index')
            ->with('success', $message)
            ->with('upload_errors', $errors);
    }
}
