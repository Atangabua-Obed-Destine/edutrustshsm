<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 0 regression guards: these screens threw 500s because they referenced
 * columns and relations that do not exist in the schema —
 * class_sections.academic_session_id, class_sections.stream_id,
 * term_results.{class_section_id,overall_average,decision},
 * ExamSchedule::invigilators(), Sequence::marks().
 *
 * The fixture matters: with an empty database the offending queries are skipped
 * by `if ($currentSession)` / `if ($currentTerm)` guards and the screens pass
 * trivially. A current session AND a current term are required to reach them.
 */
class CriticalScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // The backfill_main_branch migration already seeds a MAIN branch.
        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main', 'is_active' => true]
        );

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($branch->id, ['is_default' => true]);

        $session = AcademicSession::create([
            'branch_id' => $branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);

        $term = Term::create([
            'branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);

        $form = Form::create([
            'branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);

        $section = ClassSection::create([
            'branch_id' => $branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id, 'student_id' => 'MAIN0001',
            'first_name' => 'Sam', 'last_name' => 'Student',
            'date_of_birth' => '2012-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        $enrollment = StudentEnrollment::create([
            'branch_id' => $branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        // Drives the Reports "class performance" aggregate and the Promotion
        // recommendation (which read term_average, not overall_average).
        TermResult::create([
            'branch_id' => $branch->id, 'student_enrollment_id' => $enrollment->id,
            'term_id' => $term->id, 'total_weighted_score' => 120, 'total_coefficient' => 10,
            'term_average' => 12.00, 'overall_grade' => 'B', 'is_published' => true,
        ]);
    }

    public static function screenProvider(): array
    {
        return [
            'promotion' => ['admin.promotion.index'],
            'bulk upload' => ['admin.bulk-upload.index'],
            'reports and analytics' => ['admin.reports.index'],
            'exam schedules' => ['admin.exam-schedules.index'],
            'timetable class schedule' => ['admin.timetable.class-schedule'],
            'students' => ['admin.students.index'],
        ];
    }

    #[DataProvider('screenProvider')]
    public function test_screen_renders(string $routeName): void
    {
        $this->actingAs($this->admin)
            ->get(route($routeName))
            ->assertSuccessful();
    }

    public function test_reports_class_performance_uses_term_average(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertSuccessful();
        $rows = $response->viewData('classPerformance');

        $this->assertCount(1, $rows);
        $this->assertSame('Form 1A', $rows->first()->class_section_name);
        $this->assertEquals(12.00, round((float) $rows->first()->avg_score, 2));
        $this->assertEquals(1, (int) $rows->first()->pass_count, '12.00 is above the default pass mark of 10');
    }

    public function test_promotion_recommends_from_term_average(): void
    {
        $section = ClassSection::first();

        $response = $this->actingAs($this->admin)->get(
            route('admin.promotion.index', ['class_section_id' => $section->id])
        );

        $response->assertSuccessful();
        $students = $response->viewData('students');

        $this->assertCount(1, $students);
        // Regression: this read avg('overall_average') — a column that does not
        // exist — so computed_average was always null and no decision was made.
        $this->assertEquals(12.00, $students->first()->computed_average);
        $this->assertSame('promote', $students->first()->recommended_decision);
    }

    public function test_promotion_lists_sections_that_have_enrollments_this_session(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.promotion.index'));

        $response->assertSuccessful();
        $this->assertCount(1, $response->viewData('classSections'));
    }
}
