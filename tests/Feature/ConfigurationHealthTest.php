<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\ConfigurationHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The most useful screen an incomplete system can offer is "what have you not
 * configured yet". These assert the checks actually fire on the conditions that
 * silently break the system today — sequences mapped to no form, students with
 * no term or no subjects, payments with no account mapping to post through.
 */
class ConfigurationHealthTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

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
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        return app(ConfigurationHealthService::class)->report();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function titles(array $items): string
    {
        return collect($items)->pluck('title')->join(' | ');
    }

    private function currentYear(): AcademicSession
    {
        return AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
    }

    private function settings(): void
    {
        SchoolSetting::create([
            'branch_id' => $this->branch->id,
            'school_name' => 'Test School', 'school_code' => 'TS',
        ]);
    }

    public function test_an_empty_install_reports_the_essentials_as_missing(): void
    {
        $report = $this->report();
        $titles = $this->titles($report['errors']);

        $this->assertStringContainsString('No current academic year', $titles);
        $this->assertStringContainsString('No terms configured', $titles);
        $this->assertStringContainsString('No active classes', $titles);
        $this->assertSame(0, $report['summary']['healthy']);
    }

    public function test_two_current_academic_years_is_an_error(): void
    {
        $this->currentYear();
        AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2026/2027',
            'start_date' => '2026-09-01', 'end_date' => '2027-07-31',
            'status' => 'active', 'is_current' => true,
        ]);

        $this->assertStringContainsString(
            'More than one current academic year',
            $this->titles($this->report()['errors'])
        );
    }

    public function test_sequences_that_are_mapped_to_no_class_are_an_error(): void
    {
        $this->currentYear();
        Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        Sequence::create([
            'branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 50,
        ]);

        // form_sequence is what decides which sequences count toward a term
        // average. Unmapped sequences mean the average is computed over nothing.
        $this->assertStringContainsString(
            'No sequences assigned to any class',
            $this->titles($this->report()['errors'])
        );
    }

    public function test_identical_sequence_weights_are_flagged(): void
    {
        $this->currentYear();
        $term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);

        foreach ([1, 2] as $n) {
            $sequence = Sequence::create([
                'branch_id' => $this->branch->id, 'name' => "Seq {$n}",
                'sequence_number' => $n, 'weight' => 50,
            ]);
            DB::table('form_sequence')->insert([
                'form_id' => $form->id, 'term_id' => $term->id, 'sequence_id' => $sequence->id,
            ]);
        }

        // Every live sequence sits at the same weight because no UI field writes
        // it, which makes the "weighted" term average an unweighted mean.
        $this->assertStringContainsString(
            'All sequences carry the same weight',
            $this->titles($this->report()['warnings'])
        );
    }

    public function test_a_class_with_no_subjects_is_an_error(): void
    {
        $this->currentYear();
        Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);

        $errors = $this->report()['errors'];

        $this->assertStringContainsString('have no subjects', $this->titles($errors));
        $this->assertStringContainsString(
            'Form 1',
            collect($errors)->firstWhere(fn ($e) => str_contains($e['title'], 'no subjects'))['message']
        );
    }

    public function test_students_with_no_term_or_no_subjects_are_reported(): void
    {
        $session = $this->currentYear();
        $this->settings();

        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Ann', 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => null,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        $titles = $this->titles($this->report()['errors']);

        // Both conditions make a student invisible to the screens meant to serve
        // them, while the student list still looks perfectly fine.
        $this->assertStringContainsString('have no term', $titles);
        $this->assertStringContainsString('registered for no subjects', $titles);
    }

    public function test_a_fully_configured_school_reports_healthy(): void
    {
        $session = $this->currentYear();
        $this->settings();

        $term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Maths', 'code' => 'MTH', 'is_active' => true,
        ]);
        DB::table('form_subject')->insert([
            'form_id' => $form->id, 'subject_id' => $subject->id,
            'coefficient' => 4, 'type' => 'core',
        ]);
        $sequence = Sequence::create([
            'branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1,
        ]);
        DB::table('form_sequence')->insert([
            'form_id' => $form->id, 'term_id' => $term->id, 'sequence_id' => $sequence->id,
        ]);
        GradeScale::create([
            'branch_id' => $this->branch->id, 'grade' => 'A',
            'min_mark' => 0, 'max_mark' => 20, 'description' => 'Pass', 'display_order' => 1,
        ]);

        $report = $this->report();

        $this->assertSame(0, $report['summary']['errors'], $this->titles($report['errors']));
        $this->assertGreaterThan(0, $report['summary']['healthy']);
    }

    public function test_the_screen_renders(): void
    {
        $this->get(route('admin.configuration-health.index'))
            ->assertSuccessful()
            ->assertSee(__('Configuration Health'), false);
    }
}
