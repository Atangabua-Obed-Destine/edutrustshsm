<?php

namespace Tests\Feature\Fees;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Form;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Migration in November: parents paid a first instalment in September, in
 * the old books. Their balances must show only what is still owed, and that
 * money must not appear as today's cash.
 */
class PriorPaymentImportTest extends TestCase
{
    use RefreshDatabase;

    private StudentFee $fee;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $year = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $batch = Batch::create(['branch_id' => $branch->id, 'name' => '2026', 'shortcode' => '26', 'is_active' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 3, 'is_active' => true]);
        $section = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        $tuition = FeeCategory::create(['branch_id' => $branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        FeeStructure::create(['branch_id' => $branch->id, 'academic_session_id' => $year->id, 'fee_category_id' => $tuition->id, 'form_id' => $form->id, 'amount' => 60000]);

        $student = Student::create(['branch_id' => $branch->id, 'student_id' => 'TS260001', 'batch_id' => $batch->id, 'first_name' => 'Bih', 'last_name' => 'NGWA', 'date_of_birth' => '2018-05-15', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2026-09-07']);
        $enrollment = StudentEnrollment::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'academic_session_id' => $year->id, 'term_id' => $term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $this->fee = StudentFee::where('student_enrollment_id', $enrollment->id)->firstOrFail();
    }

    private function upload(string $csv)
    {
        return $this->post(route('admin.prior-payments.upload'), ['csv_file' => UploadedFile::fake()->createWithContent('paid.csv', $csv)]);
    }

    public function test_old_receipts_reduce_the_balance_and_are_not_todays_cash(): void
    {
        $this->upload("Matricule;Montant;Date;Reçu\r\nTS260001;25 000;08/09/2026;0457\r\nTS260001;10000;02/10/2026;0611\r\nNOPE;5000;08/09/2026;0458\r\n")
            ->assertSessionHas('success');

        $this->assertEquals(25000, (float) $this->fee->fresh()->balance, '60,000 less 35,000 paid before');
        $this->assertSame(2, Payment::where('payment_method', 'prior_system')->count());
        $this->assertStringContainsString('NOPE', implode("\n", session('upload_errors')));

        // The year's fees count it as collected; the cash received this
        // month, by method, does not.
        $this->get(route('admin.fee-reports.index'))->assertSuccessful()
            ->assertViewHas('totalCollected', fn ($v) => (float) $v === 35000.0)
            ->assertViewHas('byMethod', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('monthlyTrend', fn ($rows) => $rows->isEmpty());

        // The parent's receipt says where the money was paid.
        $payment = Payment::where('transaction_ref', '0457')->firstOrFail();
        $this->get(route('admin.collect-fees.receipt', $payment))->assertSuccessful()->assertSee(__('Paid before the switch'));
    }

    public function test_sending_the_file_again_records_nothing_twice(): void
    {
        $csv = "matricule,amount,date,old_receipt\nTS260001,25000,08/09/2026,0457\n";
        $this->upload($csv);
        $this->upload($csv)->assertSessionHas('error');

        $this->assertSame(1, Payment::count());
        $this->assertEquals(35000, (float) $this->fee->fresh()->balance);
    }

    public function test_more_than_is_owed_is_refused(): void
    {
        $this->upload("matricule,amount,date\nTS260001,70000,08/09/2026\n")->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
        $this->assertStringContainsString('60,000', implode("\n", session('upload_errors')));
    }
}
