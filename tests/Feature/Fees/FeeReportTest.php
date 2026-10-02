<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use App\Services\PaymentReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The partial payment report the reference system has, and the fee report's
 * collection figures — which counted reversed receipts as money received, and
 * used a MySQL-only date function that kept the screen from running under test.
 */
class FeeReportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private Term $term;
    private ClassSection $sectionA;
    private ClassSection $sectionB;
    private int $seq = 0;

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

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $this->sectionA = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->sectionB = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'B', 'name' => 'Form 1B', 'max_students' => 40, 'is_active' => true,
        ]);
    }

    private function enrol(string $name, ?ClassSection $section = null): StudentEnrollment
    {
        $this->seq++;
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => sprintf('MAIN%04d', $this->seq),
            'first_name' => $name, 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => ($section ?? $this->sectionA)->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
    }

    private function fee(StudentEnrollment $enrollment, float $amount, ?string $due = '2025-10-01', string $category = 'Tuition'): StudentFee
    {
        $this->seq++;
        $cat = FeeCategory::firstOrCreate(
            ['name' => $category],
            ['branch_id' => $this->branch->id, 'code' => strtoupper(substr($category, 0, 3)).$this->seq, 'is_active' => true]
        );

        return StudentFee::create([
            'branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id,
            'fee_category_id' => $cat->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'fine_amount' => 0, 'net_amount' => $amount, 'paid_amount' => 0,
            'balance' => $amount, 'status' => 'unpaid', 'due_date' => $due,
        ]);
    }

    private function pay(StudentEnrollment $enrollment, float $amount, string $method = 'cash', string $date = '2025-10-15'): Payment
    {
        return app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $enrollment->id,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => $date,
        ]);
    }

    // ------------------------------------------------------------ partial report

    public function test_only_started_but_unfinished_fees_are_listed(): void
    {
        $partial = $this->enrol('Partial');
        $this->fee($partial, 10000);
        $this->pay($partial, 4000);

        $unpaid = $this->enrol('Unpaid');
        $this->fee($unpaid, 10000);

        $settled = $this->enrol('Settled');
        $this->fee($settled, 10000);
        $this->pay($settled, 10000);

        $response = $this->get(route('admin.fee-reports.partial'))->assertSuccessful();

        $fees = $response->viewData('fees');
        $this->assertCount(1, $fees);
        $this->assertSame($partial->id, $fees->first()->student_enrollment_id);

        $stats = $response->viewData('stats');
        $this->assertEquals(10000, (float) $stats->due);
        $this->assertEquals(4000, (float) $stats->paid);
        $this->assertEquals(6000, (float) $stats->remaining);
    }

    public function test_oldest_due_date_comes_first(): void
    {
        $late = $this->enrol('Late');
        $this->fee($late, 10000, '2025-09-15');
        $this->pay($late, 1000);

        $soon = $this->enrol('Soon');
        $this->fee($soon, 10000, '2026-03-01');
        $this->pay($soon, 1000);

        $fees = $this->get(route('admin.fee-reports.partial'))->viewData('fees');

        $this->assertSame([$late->id, $soon->id], $fees->pluck('student_enrollment_id')->all());
    }

    public function test_filters_narrow_the_list(): void
    {
        $a = $this->enrol('Alice', $this->sectionA);
        $this->fee($a, 10000, '2025-10-01', 'Tuition');
        $this->pay($a, 1000);

        $b = $this->enrol('Bob', $this->sectionB);
        $this->fee($b, 5000, '2025-10-01', 'Boarding');
        $this->pay($b, 1000);

        $this->assertCount(1, $this->get(route('admin.fee-reports.partial', ['class_section_id' => $this->sectionB->id]))->viewData('fees'));
        $this->assertCount(1, $this->get(route('admin.fee-reports.partial', ['search' => 'Alice']))->viewData('fees'));
        $this->assertCount(1, $this->get(route('admin.fee-reports.partial', [
            'fee_category_id' => FeeCategory::where('name', 'Boarding')->value('id'),
        ]))->viewData('fees'));
    }

    public function test_the_payment_count_ignores_reversed_receipts(): void
    {
        $e = $this->enrol('Carol');
        $this->fee($e, 10000);
        $this->pay($e, 2000);
        $mistake = $this->pay($e, 3000);
        app(PaymentReversalService::class)->reverse($mistake, 'Recorded twice');

        $fee = $this->get(route('admin.fee-reports.partial'))->viewData('fees')->first();

        $this->assertSame(1, $fee->receipts_count);
        $this->assertEquals(2000, (float) $fee->paid_amount);
    }

    public function test_the_csv_export(): void
    {
        $e = $this->enrol('Dora');
        $this->fee($e, 10000);
        $this->pay($e, 4000);

        $csv = $this->get(route('admin.fee-reports.partial.csv'))->assertSuccessful()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Dora Test', $csv);
        $this->assertStringContainsString('6000.00', $csv);
    }

    // ------------------------------------------------------------ fee report

    public function test_the_fee_report_renders_and_ignores_reversed_receipts(): void
    {
        $e = $this->enrol('Eve');
        $this->fee($e, 20000);
        $this->pay($e, 5000, 'cash', '2025-10-15');
        $mistake = $this->pay($e, 7000, 'bank_transfer', '2025-11-02');
        app(PaymentReversalService::class)->reverse($mistake, 'Wrong student');

        // Regression: DATE_FORMAT kept this screen from running outside MySQL.
        $response = $this->get(route('admin.fee-reports.index'))->assertSuccessful();

        // Regression: reversed receipts were counted as money received.
        $methods = $response->viewData('byMethod');
        $this->assertSame(['cash'], $methods->pluck('payment_method')->all());
        $this->assertEquals(5000, (float) $methods->first()->total);

        $trend = $response->viewData('monthlyTrend');
        $this->assertSame(['2025-10'], $trend->pluck('month')->all());
    }

    public function test_the_export_needs_the_permission(): void
    {
        $clerk = User::create([
            'first_name' => 'Cal', 'last_name' => 'Clerk',
            'email' => 'clerk@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true,
        ]);
        $clerk->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->actingAs($clerk)->get(route('admin.fee-reports.partial.csv'))->assertForbidden();
    }
}
