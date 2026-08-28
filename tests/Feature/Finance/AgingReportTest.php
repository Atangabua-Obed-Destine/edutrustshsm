<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\AgingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgingReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private StudentEnrollment $enrollment;
    private FeeCategory $tuition;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
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
            'first_name' => 'Sam', 'last_name' => 'Student',
            'date_of_birth' => '2012-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        $this->enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
        $this->tuition = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true,
        ]);
    }

    private int $categorySeq = 0;

    /**
     * student_fees is unique on (enrollment, category), so each fee in a test
     * needs its own category — one charge per category per enrollment.
     */
    private function fee(float $balance, ?string $dueDate): StudentFee
    {
        $this->categorySeq++;
        $category = FeeCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Fee '.$this->categorySeq,
            'code' => 'F'.$this->categorySeq,
            'is_active' => true,
        ]);

        return StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => $balance, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $balance, 'paid_amount' => 0, 'balance' => $balance,
            'due_date' => $dueDate, 'status' => 'unpaid',
        ]);
    }

    public function test_fees_land_in_the_right_age_bucket(): void
    {
        $asOf = Carbon::parse('2026-06-01');

        $this->fee(1000, '2026-05-20');  // 12 days  -> 0-30
        $this->fee(2000, '2026-04-20');  // 42 days  -> 31-60
        $this->fee(3000, '2026-03-20');  // 73 days  -> 61-90
        $this->fee(4000, '2025-10-01');  // 243 days -> 90+

        $report = app(AgingReportService::class)->studentFees($asOf->toDateString());

        $this->assertEquals(1000, $report['totals']['0-30']);
        $this->assertEquals(2000, $report['totals']['31-60']);
        $this->assertEquals(3000, $report['totals']['61-90']);
        $this->assertEquals(4000, $report['totals']['90+']);
        $this->assertEquals(10000, $report['totals']['total']);
    }

    public function test_a_fee_not_yet_due_is_current_not_overdue(): void
    {
        $this->fee(5000, '2026-12-31');

        $report = app(AgingReportService::class)->studentFees('2026-06-01');

        $this->assertEquals(5000, $report['totals']['0-30']);
        $this->assertSame(0, $report['rows']->first()->days_overdue);
    }

    public function test_a_fee_with_no_due_date_is_treated_as_current(): void
    {
        // Rather than guessing an age from created_at.
        $this->fee(5000, null);

        $report = app(AgingReportService::class)->studentFees('2026-06-01');

        $this->assertEquals(5000, $report['totals']['0-30']);
        $this->assertSame(0, $report['rows']->first()->days_overdue);
    }

    public function test_settled_fees_are_excluded(): void
    {
        $paid = $this->fee(1000, '2026-01-01');
        $paid->update(['paid_amount' => 1000, 'balance' => 0, 'status' => 'paid']);

        $this->fee(2000, '2026-01-01');

        $report = app(AgingReportService::class)->studentFees('2026-06-01');

        $this->assertCount(1, $report['rows']);
        $this->assertEquals(2000, $report['totals']['total']);
    }

    public function test_the_report_totals_match_the_sum_of_outstanding_fees(): void
    {
        $this->fee(1500, '2026-01-01');
        $this->fee(2500, '2026-04-01');
        $this->fee(3000, null);

        $report = app(AgingReportService::class)->studentFees('2026-06-01');

        $this->assertEquals(
            (float) StudentFee::where('balance', '>', 0)->sum('balance'),
            $report['totals']['total']
        );
    }

    public function test_every_report_screen_renders(): void
    {
        $this->fee(1000, '2026-01-01');

        foreach ([
            'admin.accounting-reports.index',
            'admin.accounting-reports.student-fee-aging',
            'admin.accounting-reports.receivables-aging',
            'admin.accounting-reports.payables-aging',
            'admin.accounting-reports.budget-vs-actual',
        ] as $route) {
            $this->get(route($route))->assertSuccessful();
        }
    }

    public function test_the_reports_require_permission(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $staff = User::create([
            'first_name' => 'Sam', 'last_name' => 'Staff',
            'email' => 'staff@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);

        $this->actingAs($staff)
            ->get(route('admin.accounting-reports.student-fee-aging'))
            ->assertForbidden();
    }
}
