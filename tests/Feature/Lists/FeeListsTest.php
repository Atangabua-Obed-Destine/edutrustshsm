<?php

namespace Tests\Feature\Lists;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\FeeDiscount;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\ParentPaymentSubmission;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentRecorder;
use App\Services\PaymentReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared list toolbar (App\Support\Listing) on the fee lists: each opens
 * inside its redraw region, sorts by a column heading, and exports every
 * filtered row with amounts as plain numbers.
 *
 * Two pupils: AKO Alpha in Form 1A and ZULU Zed in Form 1B.
 */
class FeeListsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentEnrollment $ako;
    private StudentEnrollment $zulu;
    private StudentFee $akoTuition;
    private StudentFee $zuluTuition;
    private StudentFee $zuluUniform;
    private FeeCategory $uniform;
    private Payment $reversed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'secondary', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $a = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $b = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'B', 'name' => 'Form 1B', 'max_students' => 40, 'is_active' => true]);
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        $this->uniform = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Uniform', 'code' => 'UNI', 'is_tuition' => false, 'is_active' => true, 'is_mandatory' => false]);

        $pupil = function (string $id, string $first, string $last, ClassSection $class) use ($year, $term) {
            $guardian = Guardian::create(['branch_id' => $this->branch->id, 'guardian_name' => 'Parent of '.$first, 'guardian_phone' => '677000000', 'emergency_contact_name' => 'Parent of '.$first, 'emergency_contact_phone' => '677000000']);
            $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => $id, 'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '2014-05-15', 'gender' => 'female', 'guardian_id' => $guardian->id, 'status' => 'active', 'admission_date' => '2026-09-07']);

            return StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $year->id, 'term_id' => $term->id, 'class_section_id' => $class->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        };
        $this->ako = $pupil('TS260001', 'Alpha', 'AKO', $a);
        $this->zulu = $pupil('TS260002', 'Zed', 'ZULU', $b);

        $fee = fn (StudentEnrollment $e, FeeCategory $c, float $amount, string $due) => StudentFee::create([
            'branch_id' => $this->branch->id, 'student_enrollment_id' => $e->id, 'fee_category_id' => $c->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'net_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount, 'status' => 'unpaid', 'due_date' => $due,
        ]);
        $this->akoTuition = $fee($this->ako, $tuition, 60000, '2026-11-30');
        $this->zuluTuition = $fee($this->zulu, $tuition, 60000, '2026-11-30');

        // Receipts: AKO part-pays; ZULU pays in full and 5 000 over, which
        // becomes a credit; one more AKO receipt is reversed.
        $recorder = app(PaymentRecorder::class);
        $pay = fn (StudentEnrollment $e, StudentFee $f, float $amount, string $method, string $date) => $recorder->record([
            'student_enrollment_id' => $e->id, 'target_fee_id' => $f->id, 'amount' => $amount,
            'payment_method' => $method, 'payment_date' => $date,
        ]);
        $pay($this->ako, $this->akoTuition, 10000, 'cash', '2026-10-01');
        $pay($this->zulu, $this->zuluTuition, 25000, 'bank_transfer', '2026-10-05');
        $pay($this->zulu, $this->zuluTuition, 40000, 'cash', '2026-10-06');
        $this->reversed = $pay($this->ako, $this->akoTuition, 2000, 'cash', '2026-10-07');
        app(PaymentReversalService::class)->reverse($this->reversed, 'Keyed twice');

        // A fee ZULU has started on, due earlier than the tuition.
        $this->zuluUniform = $fee($this->zulu, $this->uniform, 15000, '2026-10-15');
        $this->zuluUniform->update(['paid_amount' => 5000, 'balance' => 10000, 'status' => 'partial']);
    }

    private function csv($response): array
    {
        $text = str_replace("\xEF\xBB\xBF", '', $response->streamedContent());
        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $text))));

        return array_map(fn ($l) => str_getcsv($l, ',', '"', ''), $lines);
    }

    /** One CSV column, by its heading, below the heading row. */
    private function column(array $rows, string $heading): array
    {
        $at = array_search($heading, $rows[0], true);
        $this->assertNotFalse($at, "the export has a '{$heading}' column");

        return array_map(fn ($r) => $r[$at], array_slice($rows, 1));
    }

    // ── Payments ────────────────────────────────────────────────────────

    public function test_payments_list(): void
    {
        $response = $this->get(route('admin.payments.index'))->assertOk()
            ->assertSee('data-list-region="payments"', false);
        $this->assertSame('2026-10-07', $response->viewData('payments')->first()->payment_date->toDateString(), 'newest first');

        $byAmount = $this->get(route('admin.payments.index', ['sort' => 'amount', 'dir' => 'desc']))->viewData('payments');
        $this->assertEquals([40000, 25000, 10000, 2000], $byAmount->getCollection()->map(fn ($p) => (float) $p->amount)->all());

        $byStudent = $this->get(route('admin.payments.index', ['sort' => 'student', 'dir' => 'asc']))->viewData('payments');
        $this->assertSame('AKO', $byStudent->first()->enrollment->student->last_name);
        $this->assertSame('ZULU', $byStudent->getCollection()->last()->enrollment->student->last_name);

        $rows = $this->csv($this->get(route('admin.payments.index', ['sort' => 'amount', 'dir' => 'asc', 'export' => 'csv']))->assertOk());
        $this->assertCount(1 + 4, $rows);
        $this->assertSame(['2000', '10000', '25000', '40000'], $this->column($rows, __('Amount (XAF)')));
        $this->assertSame(__('Reversed'), $this->column($rows, __('Status'))[0], 'a reversed receipt says so');
        $this->assertSame(__('Verified'), $this->column($rows, __('Status'))[1]);
        $this->assertSame(__('Bank Transfer'), $this->column($rows, __('Method'))[2]);

        // The search still narrows the export.
        $rows = $this->csv($this->get(route('admin.payments.index', ['search' => 'ZULU', 'export' => 'csv'])));
        $this->assertSame(['TS260002', 'TS260002'], $this->column($rows, __('Student ID')));
    }

    // ── Payment plans ───────────────────────────────────────────────────

    private function plans(): array
    {
        $older = PaymentPlan::create(['branch_id' => $this->branch->id, 'student_fee_id' => $this->akoTuition->id, 'total_amount' => 50000, 'number_of_installments' => 2, 'status' => 'active', 'created_by' => $this->admin->id]);
        PaymentPlanInstallment::create(['branch_id' => $this->branch->id, 'payment_plan_id' => $older->id, 'installment_number' => 1, 'amount' => 25000, 'due_date' => '2026-10-31', 'paid_amount' => 25000, 'status' => 'paid']);
        PaymentPlanInstallment::create(['branch_id' => $this->branch->id, 'payment_plan_id' => $older->id, 'installment_number' => 2, 'amount' => 25000, 'due_date' => '2026-11-30', 'paid_amount' => 0, 'status' => 'pending']);

        $newer = PaymentPlan::create(['branch_id' => $this->branch->id, 'student_fee_id' => $this->zuluUniform->id, 'total_amount' => 10000, 'number_of_installments' => 2, 'status' => 'active', 'created_by' => $this->admin->id]);
        PaymentPlanInstallment::create(['branch_id' => $this->branch->id, 'payment_plan_id' => $newer->id, 'installment_number' => 1, 'amount' => 5000, 'due_date' => '2026-11-15', 'paid_amount' => 0, 'status' => 'pending']);
        PaymentPlanInstallment::create(['branch_id' => $this->branch->id, 'payment_plan_id' => $newer->id, 'installment_number' => 2, 'amount' => 5000, 'due_date' => '2026-12-15', 'paid_amount' => 0, 'status' => 'pending']);

        return [$older, $newer];
    }

    public function test_payment_plans_list(): void
    {
        [$older, $newer] = $this->plans();

        $response = $this->get(route('admin.payment-plans.index'))->assertOk()
            ->assertSee('data-list-region="payment-plans"', false);
        $this->assertSame([$newer->id, $older->id], $response->viewData('plans')->getCollection()->pluck('id')->all(), 'newest first');

        // Remaining: 10 000 on the newer plan, 25 000 on the older.
        $asc = $this->get(route('admin.payment-plans.index', ['sort' => 'remaining', 'dir' => 'asc']))->viewData('plans');
        $this->assertSame([$newer->id, $older->id], $asc->getCollection()->pluck('id')->all());
        $desc = $this->get(route('admin.payment-plans.index', ['sort' => 'paid', 'dir' => 'desc']))->viewData('plans');
        $this->assertSame([$older->id, $newer->id], $desc->getCollection()->pluck('id')->all());
        $byStudent = $this->get(route('admin.payment-plans.index', ['sort' => 'student', 'dir' => 'desc']))->viewData('plans');
        $this->assertSame([$newer->id, $older->id], $byStudent->getCollection()->pluck('id')->all());

        $rows = $this->csv($this->get(route('admin.payment-plans.index', ['sort' => 'remaining', 'dir' => 'desc', 'export' => 'csv']))->assertOk());
        $this->assertCount(1 + 2, $rows);
        $this->assertSame(['50000', '10000'], $this->column($rows, __('Total Amount')));
        $this->assertSame(['25000', '0'], $this->column($rows, __('Paid')));
        $this->assertSame(['25000', '10000'], $this->column($rows, __('Remaining')));
        $this->assertSame(['1/2', '0/2'], $this->column($rows, __('Installments')));

        // The status filter still applies to the export.
        $older->update(['status' => 'completed']);
        $rows = $this->csv($this->get(route('admin.payment-plans.index', ['status' => 'active', 'export' => 'csv'])));
        $this->assertCount(1 + 1, $rows);
    }

    // ── Student credits ─────────────────────────────────────────────────

    public function test_student_credits_list(): void
    {
        // ZULU's over-payment made a 5 000 credit; add a smaller one for AKO.
        $over = StudentCredit::where('student_enrollment_id', $this->zulu->id)->sole();
        $this->assertEquals(5000, (float) $over->amount);
        $manual = StudentCredit::create(['student_enrollment_id' => $this->ako->id, 'amount' => 3000, 'used_amount' => 1000, 'refunded_amount' => 0, 'balance' => 2000, 'source' => 'manual', 'created_by' => $this->admin->id]);

        $response = $this->get(route('admin.student-credits.index'))->assertOk()
            ->assertSee('data-list-region="student-credits"', false);
        $this->assertSame([$manual->id, $over->id], $response->viewData('credits')->getCollection()->pluck('id')->all(), 'newest first');

        $byAvailable = $this->get(route('admin.student-credits.index', ['sort' => 'available', 'dir' => 'desc']))->viewData('credits');
        $this->assertSame([$over->id, $manual->id], $byAvailable->getCollection()->pluck('id')->all());
        $byClass = $this->get(route('admin.student-credits.index', ['sort' => 'class', 'dir' => 'asc']))->viewData('credits');
        $this->assertSame([$manual->id, $over->id], $byClass->getCollection()->pluck('id')->all(), 'Form 1A before Form 1B');

        $rows = $this->csv($this->get(route('admin.student-credits.index', ['sort' => 'available', 'dir' => 'desc', 'export' => 'csv']))->assertOk());
        $this->assertCount(1 + 2, $rows);
        $this->assertSame(['5000', '3000'], $this->column($rows, __('Amount')));
        $this->assertSame(['0', '1000'], $this->column($rows, __('Used')));
        $this->assertSame(['5000', '2000'], $this->column($rows, __('Available')));
        $this->assertNotSame('', $this->column($rows, __('Receipt #'))[0], 'the over-payment names its receipt');

        // Spent credits drop out when only available ones are asked for.
        $manual->update(['used_amount' => 3000, 'balance' => 0]);
        $this->assertCount(1 + 1, $this->csv($this->get(route('admin.student-credits.index', ['only_available' => 1, 'export' => 'csv']))));
    }

    // ── Discounts ───────────────────────────────────────────────────────

    public function test_fee_discounts_list(): void
    {
        $first = FeeDiscount::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->ako->id, 'discount_type' => 'percentage', 'value' => 10, 'reason' => 'scholarship', 'apply_to' => 'all_fees', 'approved_by' => $this->admin->id]);
        $second = FeeDiscount::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->zulu->id, 'discount_type' => 'fixed', 'value' => 5000, 'reason' => 'staff_child', 'apply_to' => 'specific_category', 'fee_category_id' => $this->uniform->id, 'approved_by' => $this->admin->id]);

        $this->get(route('admin.fee-discounts.index'))->assertOk()
            ->assertSee('data-list-region="fee-discounts"', false);

        $byStudent = $this->get(route('admin.fee-discounts.index', ['sort' => 'student', 'dir' => 'desc']))->viewData('discounts');
        $this->assertSame([$second->id, $first->id], $byStudent->getCollection()->pluck('id')->all());
        $byValue = $this->get(route('admin.fee-discounts.index', ['sort' => 'value', 'dir' => 'asc']))->viewData('discounts');
        $this->assertSame([$first->id, $second->id], $byValue->getCollection()->pluck('id')->all());

        $rows = $this->csv($this->get(route('admin.fee-discounts.index', ['sort' => 'student', 'dir' => 'asc', 'export' => 'csv']))->assertOk());
        $this->assertCount(1 + 2, $rows);
        $this->assertSame(['10', '5000'], $this->column($rows, __('Value')));
        $this->assertSame([__('Percentage'), __('Fixed')], $this->column($rows, __('Type')));
        $this->assertSame([__('All fees'), 'Uniform'], $this->column($rows, __('Applies To')));

        $rows = $this->csv($this->get(route('admin.fee-discounts.index', ['reason' => 'scholarship', 'export' => 'csv'])));
        $this->assertSame([__('Scholarship')], $this->column($rows, __('Reason')));
    }

    // ── Partial payments report ─────────────────────────────────────────

    public function test_partial_payments_list(): void
    {
        $response = $this->get(route('admin.fee-reports.partial'))->assertOk()
            ->assertSee('data-list-region="partial-payments"', false);
        // AKO's tuition (50 000 left) and ZULU's uniform (10 000 left); the
        // uniform falls due first.
        $this->assertSame([$this->zuluUniform->id, $this->akoTuition->id], $response->viewData('fees')->getCollection()->pluck('id')->all());
        $this->assertEquals(60000, (float) $response->viewData('stats')->remaining, 'the totals still cover every filtered fee');

        $byRemaining = $this->get(route('admin.fee-reports.partial', ['sort' => 'remaining', 'dir' => 'desc']))->viewData('fees');
        $this->assertSame([$this->akoTuition->id, $this->zuluUniform->id], $byRemaining->getCollection()->pluck('id')->all());
        $byCategory = $this->get(route('admin.fee-reports.partial', ['sort' => 'category', 'dir' => 'desc']))->viewData('fees');
        $this->assertSame([$this->zuluUniform->id, $this->akoTuition->id], $byCategory->getCollection()->pluck('id')->all());

        $rows = $this->csv($this->get(route('admin.fee-reports.partial', ['sort' => 'remaining', 'dir' => 'desc', 'export' => 'csv']))->assertOk());
        $this->assertCount(1 + 2, $rows);
        $this->assertSame(['60000', '15000'], $this->column($rows, __('Total')));
        $this->assertSame(['10000', '5000'], $this->column($rows, __('Paid')));
        $this->assertSame(['50000', '10000'], $this->column($rows, __('Remaining')));
        $this->assertSame(['1', '0'], $this->column($rows, __('Payments')), 'the reversed receipt is not counted');

        $rows = $this->csv($this->get(route('admin.fee-reports.partial', ['fee_category_id' => $this->uniform->id, 'export' => 'csv'])));
        $this->assertSame(['Uniform'], $this->column($rows, __('Fee Category')));
    }

    public function test_partial_payments_export_needs_the_export_permission(): void
    {
        $viewer = User::create(['first_name' => 'Vic', 'last_name' => 'Viewer', 'email' => 'viewer@example.test', 'password' => 'password', 'role' => 'accountant', 'is_active' => true]);
        $viewer->branches()->attach($this->branch->id, ['is_default' => true]);
        $role = Role::firstOrCreate(['name' => 'fee-report-reader'], ['display_name' => 'Reader', 'is_system' => false]);
        $grant = fn (string $name) => $role->permissions()->syncWithoutDetaching([
            Permission::firstOrCreate(['name' => $name], ['display_name' => $name, 'group_name' => 'Test'])->id,
        ]);
        $grant('fee-report.view');
        $viewer->roles()->sync([$role->id]);

        $this->actingAs($viewer)->get(route('admin.fee-reports.partial'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.fee-reports.partial', ['export' => 'csv']))->assertForbidden();

        $grant('fee-report.export');
        $this->actingAs($viewer->fresh())->get(route('admin.fee-reports.partial', ['export' => 'csv']))->assertOk();
    }

    // ── Parent payment submissions ──────────────────────────────────────

    public function test_parent_payment_submissions_list(): void
    {
        $submit = fn (StudentEnrollment $e, float $amount, string $method, string $status) => ParentPaymentSubmission::create([
            'branch_id' => $this->branch->id, 'guardian_id' => $e->student->guardian_id, 'student_enrollment_id' => $e->id,
            'amount' => $amount, 'payment_method' => $method, 'transaction_ref' => 'REF'.(int) $amount,
            'payment_date' => '2026-10-02', 'receipt_path' => 'r.jpg', 'status' => $status,
        ]);
        $akoSent = $submit($this->ako, 15000, 'mtn_momo', 'pending');
        $zuluSent = $submit($this->zulu, 20000, 'orange_money', 'pending');
        $submit($this->zulu, 7000, 'mtn_momo', 'rejected');

        $response = $this->get(route('admin.parent-payments.index'))->assertOk()
            ->assertSee('data-list-region="parent-payments"', false);
        $this->assertSame([$zuluSent->id, $akoSent->id], $response->viewData('submissions')->getCollection()->pluck('id')->all(), 'pending only, newest first');

        $byAmount = $this->get(route('admin.parent-payments.index', ['sort' => 'amount', 'dir' => 'asc']))->viewData('submissions');
        $this->assertSame([$akoSent->id, $zuluSent->id], $byAmount->getCollection()->pluck('id')->all());

        $rows = $this->csv($this->get(route('admin.parent-payments.index', ['sort' => 'amount', 'dir' => 'asc', 'export' => 'csv']))->assertOk());
        $this->assertCount(1 + 2, $rows);
        $this->assertSame(['15000', '20000'], $this->column($rows, __('Amount')));
        $this->assertSame(['MTN Mobile Money', 'Orange Money'], $this->column($rows, __('Method')));
        $this->assertSame(['REF15000', 'REF20000'], $this->column($rows, __('Reference')));

        $this->assertCount(1 + 3, $this->csv($this->get(route('admin.parent-payments.index', ['status' => 'all', 'export' => 'csv']))));
    }
}
