<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\LevelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registering a nursery or primary pupil, end to end through the controller.
 *
 * The screen was built for secondary intake, where an entry certificate and a
 * cycle exist. A pupil starting Nursery 1 has neither, so the question is
 * whether anything on the way to the database still insists on them.
 */
class NurseryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Batch $batch;
    private Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both',
        ]);

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
        $this->batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true,
        ]);

        LevelContext::set('nursery_primary');
    }

    private function nurseryForm(): Form
    {
        return Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Nursery 1', 'short_name' => 'N1',
            'level' => 'nursery', 'school_level' => 'nursery_primary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => 1, 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(ClassSection $section): array
    {
        return [
            'batch_id' => $this->batch->id,
            'admission_date' => '2025-09-05',
            'form_id' => $section->form_id,
            'class_section_id' => $section->id,
            'term_id' => $this->term->id,
            'residence_type' => 'day',
            'cycle' => 'nursery',
            'education_system' => 'english',
            'first_name' => 'Bih', 'last_name' => 'Ngwa',
            // A three-year-old starting Nursery 1.
            'date_of_birth' => '2022-04-11',
            'gender' => 'female',
            'emergency_contact_name' => 'Mami Ngwa',
            'emergency_contact_phone' => '677000111',
            'mother_name' => 'Mami Ngwa',
            'mother_phone' => '677000111',
        ];
    }

    public function test_a_nursery_pupil_registers_without_an_entry_certificate(): void
    {
        $form = $this->nurseryForm();
        $section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Nursery 1A', 'max_students' => 25, 'is_active' => true,
        ]);

        $this->post(route('admin.students.store'), $this->payload($section))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $student = Student::firstOrFail();
        $this->assertSame('TS250001', $student->student_id);

        // The pupil must land in a class, not merely exist.
        $enrollment = $student->enrollments()->firstOrFail();
        $this->assertSame($section->id, $enrollment->class_section_id);
        $this->assertNull($enrollment->stream_id);
        $this->assertSame('active', $enrollment->status);
    }

    public function test_the_gce_certificate_is_not_demanded_of_a_primary_pupil(): void
    {
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Class 6', 'short_name' => 'C6',
            'level' => 'primary', 'school_level' => 'nursery_primary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => 6, 'is_active' => true,
        ]);
        $section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Class 6A', 'max_students' => 40, 'is_active' => true,
        ]);

        $payload = $this->payload($section);
        $payload['cycle'] = 'primary';

        $this->post(route('admin.students.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, Student::count());
    }
    public function test_the_screen_says_so_when_the_level_has_no_classes_yet(): void
    {
        // Nursery/Primary mode with no nursery classes created: the Form dropdown
        // can only be empty, so the screen has to say why.
        $this->get(route('admin.students.create'))
            ->assertSuccessful()
            ->assertSee(__('No classes have been set up for :level yet, so nobody can be registered into one.', [
                'level' => __('Nursery / Primary'),
            ]), false);
    }

    public function test_the_education_system_opens_on_one_that_has_classes(): void
    {
        // A French-language nursery. Opening on English would show an empty list.
        Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Maternelle 1', 'short_name' => 'M1',
            'level' => 'nursery', 'school_level' => 'nursery_primary',
            'education_system' => 'french', 'has_streams' => false,
            'display_order' => 1, 'is_active' => true,
        ]);

        $response = $this->get(route('admin.students.create'))->assertSuccessful();

        $this->assertSame('french', $response->viewData('educationSystem'));
        $response->assertSee('value="french" selected', false);
    }
}
