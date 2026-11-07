<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\EdutrustPayOutbox;
use App\Models\EdutrustPaySetting;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Services\EdutrustPay\PeriodReportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What the school tells the body it charges, so the body can compare it with the
 * fees it has set. Amounts and head counts only, never a student.
 */
class EdutrustPayFeesChargedTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private Term $term;

    private Form $form;

    private ClassSection $section;

    private FeeCategory $tuition;

    private Batch $batch;

    private int $next = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-15 10:00:00');

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'ST JEROME', 'is_active' => true]);

        EdutrustPaySetting::create([
            'branch_id' => $this->branch->id,
            'enabled' => true,
            'endpoint' => 'https://console.example.test',
            'institution_ref' => '11111111-1111-4111-8111-111111111111',
            'key_id' => 'etp_test',
            'secret_ciphertext' => 'a-test-secret-of-sufficient-length',
        ]);

        $this->session = $this->sessionFor($this->branch);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $this->batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $this->form = $this->formFor($this->branch);
        $this->section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->form->id, 'section' => 'A', 'name' => 'F1A', 'max_students' => 40, 'is_active' => true]);
        $this->tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true]);
    }

    public function test_fees_are_reported_per_form_with_head_counts_and_no_names(): void
    {
        FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $this->form->id, 'fee_category_id' => $this->tuition->id, 'amount' => 200000]);
        $this->bill('Bih', 200000);
        $this->bill('Che', 200000);
        $this->bill('Ngwa', 5000);

        $this->travelTo('2026-11-07 10:00:00');
        $payload = app(PeriodReportBuilder::class)->build($this->branch->id, '2026-10');

        $block = $payload['operational']['fees_charged'];

        $this->assertContains('fees_charged', $payload['capabilities']);
        $this->assertSame('2026/2027', $block['session']);
        $this->assertSame(
            [['Form 1', 'Tuition', '5000.00', 1], ['Form 1', 'Tuition', '200000.00', 2]],
            array_map(fn ($r) => [$r['level'], $r['category'], (string) $r['amount'], $r['students']], $block['rows'])
        );

        $this->assertStringNotContainsString('Bih', json_encode($payload));
    }

    public function test_a_structure_nobody_has_been_billed_on_still_says_what_is_intended(): void
    {
        FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $this->form->id, 'fee_category_id' => $this->tuition->id, 'amount' => 200000]);

        $this->travelTo('2026-11-07 10:00:00');
        $rows = app(PeriodReportBuilder::class)->build($this->branch->id, '2026-10')['operational']['fees_charged']['rows'];

        $this->assertSame(0, $rows[0]['students']);
        $this->assertSame('200000.00', (string) $rows[0]['amount']);
    }

    public function test_a_school_with_no_fees_set_omits_the_block_rather_than_sending_a_zero(): void
    {
        $this->travelTo('2026-11-07 10:00:00');

        $this->artisan('edutrustpay:report 2026-10 --dry-run');

        $this->assertArrayNotHasKey('fees_charged', $this->buildOrEmpty()['operational'] ?? []);
    }

    public function test_another_campus_is_not_included(): void
    {
        $other = Branch::create(['code' => 'OTHER', 'name' => 'OTHER', 'is_active' => true]);
        $session = $this->sessionFor($other);
        $form = $this->formFor($other);
        $category = FeeCategory::create(['branch_id' => $other->id, 'name' => 'Tuition', 'code' => 'TUI2', 'is_tuition' => true, 'is_active' => true]);
        FeeStructure::create(['branch_id' => $other->id, 'academic_session_id' => $session->id, 'form_id' => $form->id, 'fee_category_id' => $category->id, 'amount' => 999999]);
        FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $this->form->id, 'fee_category_id' => $this->tuition->id, 'amount' => 200000]);

        $this->travelTo('2026-11-07 10:00:00');
        $rows = app(PeriodReportBuilder::class)->build($this->branch->id, '2026-10')['operational']['fees_charged']['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame('200000.00', (string) $rows[0]['amount']);
    }

    public function test_a_fee_set_after_the_month_ended_is_not_in_that_months_report(): void
    {
        $this->travelTo('2026-11-02 10:00:00');
        FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $this->form->id, 'fee_category_id' => $this->tuition->id, 'amount' => 200000]);

        $this->travelTo('2026-11-07 10:00:00');

        $this->assertArrayNotHasKey('fees_charged', $this->buildOrEmpty()['operational'] ?? []);
    }

    public function test_changing_a_fee_refiles_a_month_still_open(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        $structure = FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $this->form->id, 'fee_category_id' => $this->tuition->id, 'amount' => 200000]);

        $this->travelTo('2026-11-07 10:00:00');
        $this->artisan('edutrustpay:report 2026-10 --send');

        $this->assertSame(1, EdutrustPayOutbox::where('period', '2026-10')->count());

        $this->artisan('edutrustpay:report 2026-10 --send');

        $this->assertSame(1, EdutrustPayOutbox::where('period', '2026-10')->count(), 'Nothing changed, so nothing is filed.');

        $structure->update(['amount' => 250000]);
        $this->artisan('edutrustpay:report 2026-10 --send');

        $latest = EdutrustPayOutbox::where('period', '2026-10')->orderByDesc('sequence')->first();

        $this->assertSame(2, (int) $latest->sequence);
        $this->assertStringContainsString('250000.00', $latest->payload);
    }

    public function test_changing_a_fee_does_not_reopen_a_closed_month(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);

        $structure = FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'form_id' => $this->form->id, 'fee_category_id' => $this->tuition->id, 'amount' => 200000]);

        $this->travelTo('2026-11-07 10:00:00');
        $this->artisan('edutrustpay:report 2026-09 --send');

        $structure->update(['amount' => 250000]);
        $this->artisan('edutrustpay:report 2026-09 --send');

        $this->assertSame(1, EdutrustPayOutbox::where('period', '2026-09')->count());
    }

    /**
     * The report can legitimately have nothing in it for a school this empty.
     *
     * @return array<string, mixed>
     */
    private function buildOrEmpty(): array
    {
        try {
            return app(PeriodReportBuilder::class)->build($this->branch->id, '2026-10');
        } catch (\EdutrustPay\Contract\Exceptions\CapabilityMismatchException) {
            return [];
        }
    }

    private function sessionFor(Branch $branch): AcademicSession
    {
        return AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
    }

    private function formFor(Branch $branch): Form
    {
        return Form::create(['branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'secondary', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
    }

    private function bill(string $firstName, int $amount): void
    {
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS'.$this->next++, 'batch_id' => $this->batch->id, 'first_name' => $firstName, 'last_name' => 'TEST', 'date_of_birth' => '2012-05-15', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-08']);

        $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id, 'term_id' => $this->term->id, 'class_section_id' => $this->section->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);

        // Enrolling bills the student from the structure, so this sets that bill.
        StudentFee::updateOrCreate([
            'student_enrollment_id' => $enrollment->id,
            'fee_category_id' => $this->tuition->id,
        ], [
            'branch_id' => $this->branch->id,
            'original_amount' => $amount,
            'discount_amount' => 0,
            'waiver_amount' => 0,
            'fine_amount' => 0,
            'net_amount' => $amount,
            'paid_amount' => 0,
            'balance' => $amount,
            'status' => 'unpaid',
            'due_date' => '2026-10-15',
        ]);
    }
}
