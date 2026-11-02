<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\ClassSection;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Support\SchoolCsv;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class BulkUploadController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'bulk-upload';

    /** Columns of the template, in order. Starred ones are required. */
    public const COLUMNS = [
        'matricule', 'last_name*', 'first_name*', 'other_names', 'date_of_birth*', 'gender*',
        'place_of_birth', 'nationality', 'religion', 'residence_type', 'admission_date',
        'father_name', 'father_phone', 'mother_name', 'mother_phone',
        'guardian_name', 'guardian_phone',
        'emergency_contact_name', 'emergency_contact_phone',
        'home_address', 'previous_school', 'previous_class',
    ];

    /** Headings people actually write, mapped to ours. */
    private const ALIASES = [
        'student_id' => 'matricule', 'matricule_no' => 'matricule', 'mat' => 'matricule',
        'surname' => 'last_name', 'nom' => 'last_name', 'family_name' => 'last_name',
        'given_name' => 'first_name', 'given_names' => 'first_name', 'prenom' => 'first_name', 'prénom' => 'first_name', 'prenoms' => 'first_name', 'prénoms' => 'first_name',
        'dob' => 'date_of_birth', 'birth_date' => 'date_of_birth', 'date_de_naissance' => 'date_of_birth', 'born_on' => 'date_of_birth',
        'sex' => 'gender', 'sexe' => 'gender',
        'lieu_de_naissance' => 'place_of_birth', 'pob' => 'place_of_birth',
        'parent_name' => 'guardian_name', 'parent_phone' => 'guardian_phone',
        'phone' => 'guardian_phone', 'telephone' => 'guardian_phone', 'téléphone' => 'guardian_phone',
    ];

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
            ->get()
            ->sortBy(fn ($s) => [$s->form->display_order, $s->name])->values();
        $batches = Batch::where('is_active', true)->orderByDesc('id')->get();

        return view('admin.students.bulk-upload', compact('classSections', 'currentSession', 'batches'));
    }

    public function template()
    {
        // "sep=," makes Excel open the columns correctly whatever the
        // computer's language; a French Excel otherwise expects semicolons.
        $example = [
            '', 'NGWA', 'Bih Mary', '', '15/05/2018', 'F',
            'Bamenda', 'Cameroonian', 'Christian', 'day', '',
            'Ngwa Peter', '677000001', 'Ngwa Grace', '677000002',
            '', '',
            '', '',
            'Mile 3 Nkwen', 'Little Angels Nursery', 'Nursery 2',
        ];

        $out = fopen('php://temp', 'w+');
        fwrite($out, "sep=,\n");
        fputcsv($out, array_map(fn ($c) => rtrim($c, '*'), self::COLUMNS), ',', '"', '');
        fputcsv($out, $example, ',', '"', '');
        rewind($out);

        return response(stream_get_contents($out), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pupils_template.csv"',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'class_section_id' => ['required', 'exists:class_sections,id'],
            'batch_id' => ['required', 'exists:batches,id'],
        ]);

        $currentSession = AcademicSession::current();
        if (! $currentSession) {
            return back()->with('error', __('No active academic session found.'));
        }

        $currentTerm = Term::where('is_current', true)->first();
        if (! $currentTerm) {
            return back()->with('error', __('No current term is set. Set one before importing students.'));
        }

        $classSection = ClassSection::with('form')->findOrFail($request->class_section_id);
        $batch = Batch::findOrFail($request->batch_id);

        [$headers, $rows] = SchoolCsv::read($request->file('csv_file')->getRealPath(), self::ALIASES);

        $missing = array_diff(['last_name', 'first_name', 'date_of_birth', 'gender'], $headers);
        if ($missing) {
            return back()->with('error', __('The file has no column for: :columns. Start from the template.', ['columns' => implode(', ', $missing)]));
        }

        $imported = 0;
        $skipped = [];
        $errors = [];
        $lineNum = null;

        DB::beginTransaction();

        try {
            foreach ($rows as $lineNum => $row) {
                $data = SchoolCsv::combine($headers, $row);
                $get = fn (string $key) => $data[$key] ?? '';

                if (implode('', $data) === '') {
                    continue; // a blank line Excel left at the end
                }

                $name = trim($get('last_name').' '.$get('first_name'));
                $problems = [];

                if ($get('last_name') === '' || $get('first_name') === '') {
                    $problems[] = __('a first and a last name are needed');
                }
                $dob = SchoolCsv::date($get('date_of_birth'));
                if (! $dob || $dob->isFuture() || $dob->year < 1950) {
                    $problems[] = __('date of birth ":value" is not a date (use DD/MM/YYYY)', ['value' => $get('date_of_birth')]);
                }
                $gender = $this->parseGender($get('gender'));
                if (! $gender) {
                    $problems[] = __('sex ":value" should be M or F', ['value' => $get('gender')]);
                }
                $admitted = $get('admission_date') !== '' ? SchoolCsv::date($get('admission_date')) : now();
                if (! $admitted) {
                    $problems[] = __('admission date ":value" is not a date (use DD/MM/YYYY)', ['value' => $get('admission_date')]);
                }

                // Someone to call: the emergency contact, else a parent.
                $contactName = collect([$get('emergency_contact_name'), $get('guardian_name'), $get('father_name'), $get('mother_name')])->first(fn ($v) => $v !== '');
                $contactPhone = collect([$get('emergency_contact_phone'), $get('guardian_phone'), $get('father_phone'), $get('mother_phone')])->first(fn ($v) => $v !== '');
                if (! $contactPhone) {
                    $problems[] = __('no phone number for a parent or guardian');
                }

                $matricule = $get('matricule');
                if (mb_strlen($matricule) > 20) {
                    $problems[] = __('matricule ":value" is longer than 20 characters', ['value' => $matricule]);
                }

                if ($problems) {
                    $errors[] = __('Line :line (:name): :problems', ['line' => $lineNum, 'name' => $name ?: '—', 'problems' => implode('; ', $problems)]);
                    continue;
                }

                // Already here? Uploading the same file twice must not
                // register the same children twice.
                $existing = $matricule !== ''
                    ? Student::where('student_id', $matricule)->first()
                    : Student::where('last_name', $get('last_name'))->where('first_name', $get('first_name'))
                        ->whereDate('date_of_birth', $dob->toDateString())->first();
                if ($existing) {
                    $skipped[] = __('Line :line (:name): already registered as :matricule', ['line' => $lineNum, 'name' => $name, 'matricule' => $existing->student_id]);
                    continue;
                }

                $guardian = app(\App\Services\GuardianResolver::class)->resolve([
                    'father_name' => $get('father_name') ?: null,
                    'father_phone' => $get('father_phone') ?: null,
                    'mother_name' => $get('mother_name') ?: null,
                    'mother_phone' => $get('mother_phone') ?: null,
                    'guardian_name' => $get('guardian_name') ?: null,
                    'guardian_phone' => $get('guardian_phone') ?: null,
                    'emergency_contact_name' => $contactName ?: $name,
                    'emergency_contact_phone' => $contactPhone,
                ]);

                $student = Student::create([
                    'student_id' => $matricule !== '' ? $matricule : Student::nextMatricule($batch),
                    'batch_id' => $batch->id,
                    'first_name' => $get('first_name'),
                    'last_name' => $get('last_name'),
                    'other_names' => $get('other_names') ?: null,
                    'date_of_birth' => $dob->toDateString(),
                    'gender' => $gender,
                    'nationality' => $get('nationality') ?: 'Cameroonian',
                    'place_of_birth' => $get('place_of_birth') ?: null,
                    'religion' => $get('religion') ?: null,
                    'home_address' => $get('home_address') ?: null,
                    'guardian_id' => $guardian->id,
                    'previous_school' => $get('previous_school') ?: null,
                    'previous_class' => $get('previous_class') ?: null,
                    'status' => 'active',
                    'admission_date' => $admitted->toDateString(),
                ]);

                // term_id is REQUIRED: marks entry, report cards and the report-card
                // listing all filter on it, so an enrollment without one is invisible
                // to the entire assessment pipeline. Creating it also prices the
                // pupil's fees (StudentEnrollmentObserver).
                $enrollment = StudentEnrollment::create([
                    'student_id' => $student->id,
                    'academic_session_id' => $currentSession->id,
                    'term_id' => $currentTerm->id,
                    'class_section_id' => $classSection->id,
                    'residence_type' => in_array($get('residence_type'), ['day', 'boarding', 'half_boarding'], true) ? $get('residence_type') : 'day',
                    'enrollment_date' => now()->toDateString(),
                    'status' => 'active',
                ]);

                // Register the form's core subjects, or the student arrives
                // enrolled in a class but studying nothing.
                $enrollment->syncCoreSubjects();

                $imported++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', __('Nothing was imported: line :line could not be saved (:reason).', ['line' => $lineNum ?? '?', 'reason' => $e->getMessage()]));
        }

        $message = trans_choice('{0} No new pupils were added to :class.|{1} 1 pupil added to :class.|[2,*] :count pupils added to :class.', $imported, ['count' => $imported, 'class' => $classSection->name]);
        if ($skipped) {
            $message .= ' '.trans_choice('{1} 1 was already registered and was left as it was.|[2,*] :count were already registered and were left as they were.', count($skipped), ['count' => count($skipped)]);
        }
        if ($errors) {
            $message .= ' '.trans_choice('{1} 1 line needs correcting: fix it and upload the file again.|[2,*] :count lines need correcting: fix them and upload the file again.', count($errors), ['count' => count($errors)]);
        }

        return redirect()->route('admin.bulk-upload.index')
            ->with($imported > 0 ? 'success' : 'error', $message)
            ->with('upload_errors', array_merge($errors, $skipped));
    }

    private function parseGender(string $value): ?string
    {
        $v = mb_strtolower(trim($value));

        return match (true) {
            in_array($v, ['m', 'male', 'boy', 'masculin', 'garçon', 'garcon', 'h', 'homme'], true) => 'male',
            in_array($v, ['f', 'female', 'girl', 'féminin', 'feminin', 'fille', 'femme'], true) => 'female',
            default => null,
        };
    }
}
