<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every intake path — the student form, admissions, bulk upload and promotion —
 * created an enrollment and stopped there, never registering the form's core
 * subjects. Since report cards take student_subjects as the subject universe, a
 * newly admitted student had an empty timetable and an empty report card until
 * somebody happened to open marks entry for their class, which repaired it as a
 * side effect.
 */
class StudentIntakeSubjectsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Term $term;
    private Form $form;
    private ClassSection $section;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $this->form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->batch = Batch::create([
            'name' => '2025 Intake', 'shortcode' => '2025', 'is_active' => true,
        ]);

        foreach ([['Maths', 'MTH', 4], ['English', 'ENG', 3]] as [$name, $code, $coefficient]) {
            $subject = Subject::create([
                'branch_id' => $this->branch->id, 'name' => $name, 'code' => $code, 'is_active' => true,
            ]);

            DB::table('form_subject')->insert([
                'form_id' => $this->form->id, 'subject_id' => $subject->id,
                'coefficient' => $coefficient, 'type' => 'core',
            ]);
        }
    }

    public function test_admitting_a_student_registers_the_forms_core_subjects(): void
    {
        $this->post(route('admin.students.store'), [
            'batch_id' => $this->batch->id,
            'admission_date' => '2025-09-01',
            'form_id' => $this->form->id,
            'class_section_id' => $this->section->id,
            'term_id' => $this->term->id,
            'residence_type' => 'day',
            'first_name' => 'Ann',
            'last_name' => 'Test',
            'date_of_birth' => '2012-01-01',
            'gender' => 'female',
            'emergency_contact_name' => 'Ada Guardian',
            'emergency_contact_phone' => '670000000',
        ])->assertSessionHasNoErrors();

        $student = Student::firstOrFail();
        $enrollment = StudentEnrollment::where('student_id', $student->id)->firstOrFail();

        $this->assertSame($this->term->id, $enrollment->term_id);

        // Regression: the enrollment was created and the subjects never were, so
        // the student's report card had nothing to aggregate.
        $subjects = StudentSubject::where('student_enrollment_id', $enrollment->id)->get();

        $this->assertCount(2, $subjects);
        $this->assertEqualsCanonicalizing([4.0, 3.0], $subjects->pluck('coefficient')->map(fn ($c) => (float) $c)->all());
    }
}
