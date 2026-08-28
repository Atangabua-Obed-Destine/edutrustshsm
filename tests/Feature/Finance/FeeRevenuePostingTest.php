<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\ClassSection;
use App\Models\DefaultAccountMapping;
use App\Models\FeeCategory;
use App\Models\FiscalYear;
use App\Models\Form;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fee revenue posts one allocation at a time. Posting the payment as a whole
 * could only use a single catch-all rule, so the ledger could not tell tuition
 * from boarding — which makes per-category revenue and receivables ageing
 * impossible.
 */
class FeeRevenuePostingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private StudentEnrollment $enrollment;
    private FeeCategory $tuition;
    private FeeCategory $boarding;
    private ChartOfAccount $cash;
    private ChartOfAccount $tuitionRevenue;
    private ChartOfAccount $boardingRevenue;

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

        $fy = FiscalYear::create([
            'branch_id' => $this->branch->id, 'name' => 'FY2026',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'is_active' => true, 'is_closed' => false,
        ]);
        AccountingPeriod::create([
            'branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id,
            'name' => 'January 2026', 'period_number' => 1,
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'is_closed' => false,
        ]);

        $this->cash = $this->account('521', 5, 'debit', 'Bank');
        $this->tuitionRevenue = $this->account('7061', 7, 'credit', 'Tuition Revenue');
        $this->boardingRevenue = $this->account('7062', 7, 'credit', 'Boarding Revenue');

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
            'class_section_id' => $section->id, 'residence_type' => 'boarding',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        $this->tuition = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_active' => true,
        ]);
        $this->boarding = FeeCategory::create([
            'branch_id' => $this->branch->id, 'name' => 'Boarding', 'code' => 'BRD', 'is_active' => true,
        ]);
    }

    private function account(string $code, int $class, string $normal, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'branch_id' => $this->branch->id, 'account_code' => $code,
            'account_name' => $name, 'class_number' => $class,
            'account_type' => 'detail', 'normal_balance' => $normal, 'is_active' => true,
        ]);
    }

    private function map(?int $categoryId, ChartOfAccount $credit): void
    {
        DefaultAccountMapping::create([
            'branch_id' => $this->branch->id,
            'mapping_type' => 'fee_payment',
            'category_id' => $categoryId,
            'debit_account_id' => $this->cash->id,
            'credit_account_id' => $credit->id,
            'status' => 'active',
        ]);
    }

    private function fee(FeeCategory $category, float $amount): StudentFee
    {
        return StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount,
            'status' => 'unpaid',
        ]);
    }

    private function creditedTo(ChartOfAccount $account): float
    {
        return (float) JournalEntryLine::where('account_id', $account->id)->sum('credit');
    }

    public function test_a_split_payment_credits_each_category_to_its_own_account(): void
    {
        $this->map($this->tuition->id, $this->tuitionRevenue);
        $this->map($this->boarding->id, $this->boardingRevenue);

        $tuitionFee = $this->fee($this->tuition, 10000);
        $this->fee($this->boarding, 8000);

        app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $tuitionFee->id,
            'amount' => 15000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        // 10,000 tuition + 5,000 spill-over to boarding, each to its own account.
        $this->assertEquals(10000, $this->creditedTo($this->tuitionRevenue));
        $this->assertEquals(5000, $this->creditedTo($this->boardingRevenue));
        $this->assertEquals(15000, (float) JournalEntryLine::where('account_id', $this->cash->id)->sum('debit'));
    }

    public function test_a_category_without_its_own_rule_falls_back_to_the_catch_all(): void
    {
        $this->map(null, $this->tuitionRevenue);          // catch-all
        $this->map($this->boarding->id, $this->boardingRevenue);

        $this->fee($this->tuition, 10000);

        app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $this->assertEquals(10000, $this->creditedTo($this->tuitionRevenue));
        $this->assertEquals(0, $this->creditedTo($this->boardingRevenue));
    }

    public function test_every_posted_entry_balances(): void
    {
        $this->map(null, $this->tuitionRevenue);
        $this->fee($this->tuition, 10000);
        $this->fee($this->boarding, 8000);

        app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 18000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $this->assertGreaterThan(0, JournalEntry::count());

        foreach (JournalEntry::with('lines')->get() as $entry) {
            $this->assertEquals(
                (float) $entry->lines->sum('debit'),
                (float) $entry->lines->sum('credit'),
                "entry {$entry->entry_number} does not balance"
            );
        }
    }

    public function test_posted_revenue_equals_what_was_allocated(): void
    {
        $this->map(null, $this->tuitionRevenue);
        $this->fee($this->tuition, 10000);

        // More than the student owes: only the allocated 10,000 is revenue.
        $payment = app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 25000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        $allocated = (float) $payment->allocations()->sum('amount');

        $this->assertEquals(10000, $allocated);
        $this->assertEquals($allocated, $this->creditedTo($this->tuitionRevenue));
    }

    public function test_nothing_posts_when_no_mapping_is_configured(): void
    {
        $this->fee($this->tuition, 10000);

        app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'amount' => 10000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-15',
        ]);

        // Unposted, and therefore listed for backfill rather than lost.
        $this->assertSame(0, JournalEntry::count());
    }
}
