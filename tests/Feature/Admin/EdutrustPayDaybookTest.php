<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\EdutrustPayOutbox;
use App\Models\EdutrustPaySetting;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Services\EdutrustPay\DaybookExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sending a branch's daybook to the console: the right lines, for the right school.
 */
class EdutrustPayDaybookTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Branch $other;

    private StudentEnrollment $here;

    private StudentEnrollment $there;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-07 10:00:00');

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'ST JEROME', 'is_active' => true]);
        $this->other = Branch::create(['code' => 'OTHER', 'name' => 'OTHER CAMPUS', 'is_active' => true]);

        EdutrustPaySetting::create([
            'branch_id' => $this->branch->id,
            'enabled' => true,
            'endpoint' => 'https://console.example.test',
            'institution_ref' => '11111111-1111-4111-8111-111111111111',
            'key_id' => 'etp_test',
            'secret_ciphertext' => 'a-test-secret-of-sufficient-length',
        ]);

        $this->here = $this->enrol($this->branch, 'Bih', 'TS1');
        $this->there = $this->enrol($this->other, 'Other', 'TS2');
    }

    public function test_the_daybook_holds_this_branch_and_not_another_campus(): void
    {
        $this->payment($this->here, 'R-1', 50000, 'cash');
        $this->payment($this->there, 'R-2', 999999, 'cash');

        $document = app(DaybookExporter::class)->document($this->branch->id, '2026-10');

        $this->assertCount(1, $document['lines']);
        $this->assertSame('50000.00', $document['money_in']);
        $this->assertSame('R-1', $document['lines'][0]['ref']);
        $this->assertSame('fee', $document['lines'][0]['kind']);
        $this->assertSame('cash', $document['lines'][0]['method']);
        $this->assertStringContainsString('Bih', $document['lines'][0]['description']);
    }

    public function test_a_refund_belongs_to_the_branch_of_the_pupil(): void
    {
        $this->refund($this->here, 3000);
        $this->refund($this->there, 7000);

        $document = app(DaybookExporter::class)->document($this->branch->id, '2026-10');

        $this->assertSame('3000.00', $document['money_out']);
    }

    public function test_mobile_money_is_recognised(): void
    {
        $this->payment($this->here, 'R-1', 50000, 'mtn_momo');

        $this->assertSame('mobile_money', app(DaybookExporter::class)->document($this->branch->id, '2026-10')['lines'][0]['method']);
    }

    public function test_it_goes_to_its_own_address_and_not_with_the_reports(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);
        $this->payment($this->here, 'R-1', 50000, 'cash');

        $this->artisan('edutrustpay:daybook 2026-10 --send')->assertSuccessful();

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/v1/daybook'));
        $this->assertSame(EdutrustPayOutbox::DELIVERED, EdutrustPayOutbox::where('kind', 'daybook')->sole()->status);
    }

    public function test_an_unchanged_month_is_not_sent_again_and_a_changed_one_is(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);
        $this->payment($this->here, 'R-1', 50000, 'cash');

        $this->artisan('edutrustpay:daybook 2026-10 --send');
        $this->artisan('edutrustpay:daybook 2026-10 --send');
        $this->assertSame(1, EdutrustPayOutbox::where('kind', 'daybook')->count());

        $this->payment($this->here, 'R-2', 20000, 'cash');
        $this->artisan('edutrustpay:daybook 2026-10 --send');

        $latest = EdutrustPayOutbox::where('kind', 'daybook')->orderByDesc('sequence')->first();
        $this->assertSame(2, (int) $latest->sequence);
        $this->assertStringContainsString('70000.00', $latest->payload);
    }

    public function test_a_conflict_is_filed_again_at_the_next_sequence(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'sequence_conflict', 'current_sequence' => 3], 409)
            ->push(['status' => 'accepted'], 202);

        $this->payment($this->here, 'R-1', 50000, 'cash');
        $this->artisan('edutrustpay:daybook 2026-10 --send');

        $latest = EdutrustPayOutbox::where('kind', 'daybook')->orderByDesc('sequence')->first();

        $this->assertSame(4, (int) $latest->sequence);
        $this->assertSame(EdutrustPayOutbox::DELIVERED, $latest->status);
    }

    public function test_it_does_not_disturb_the_monthly_report(): void
    {
        Http::fake(['*' => Http::response(['status' => 'accepted'], 202)]);
        $this->payment($this->here, 'R-1', 50000, 'cash');

        $this->artisan('edutrustpay:report 2026-10 --send');
        $this->artisan('edutrustpay:daybook 2026-10 --send');
        $this->artisan('edutrustpay:report 2026-10 --send');

        $this->assertSame(1, EdutrustPayOutbox::where('kind', 'report')->where('period', '2026-10')->count());
    }

    private function enrol(Branch $branch, string $name, string $number): StudentEnrollment
    {
        $session = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        $term = Term::firstOrCreate(['term_number' => 1], ['branch_id' => $branch->id, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $batch = Batch::firstOrCreate(['name' => '2025'], ['branch_id' => $branch->id, 'shortcode' => '25', 'is_active' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'secondary', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'F1A', 'max_students' => 40, 'is_active' => true]);
        $student = Student::create(['branch_id' => $branch->id, 'student_id' => $number, 'batch_id' => $batch->id, 'first_name' => $name, 'last_name' => 'TEST', 'date_of_birth' => '2012-05-15', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-08']);

        return StudentEnrollment::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'academic_session_id' => $session->id, 'term_id' => $term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
    }

    private function payment(StudentEnrollment $enrollment, string $receipt, int $amount, string $method): void
    {
        DB::table('payments')->insert([
            'branch_id' => $enrollment->branch_id,
            'receipt_number' => $receipt,
            'student_enrollment_id' => $enrollment->id,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => '2026-10-15',
            'verification_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function refund(StudentEnrollment $enrollment, int $amount): void
    {
        $credit = DB::table('student_credits')->insertGetId([
            'student_enrollment_id' => $enrollment->id,
            'amount' => $amount,
            'used_amount' => 0,
            'refunded_amount' => $amount,
            'balance' => 0,
            'source' => 'overpayment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('student_credit_refunds')->insert([
            'student_credit_id' => $credit,
            'amount' => $amount,
            'reason' => 'Left the school',
            'status' => 'processed',
            'requested_at' => now(),
            'processed_at' => '2026-10-20 10:00:00',
            'method' => 'cash',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
