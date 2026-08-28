<?php

namespace Tests\Feature\Finance;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\FeeFine;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use App\Services\FeeFineService;
use App\Services\PaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Late-payment penalties accrue on a schedule and are RECOMPUTED from the
 * bands, never incremented — so a double run costs nothing and removing a band
 * undoes its charge.
 */
class FeeFineTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private StudentEnrollment $enrollment;
    private int $seq = 0;

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
    }

    private function fee(float $amount, ?string $dueDate): StudentFee
    {
        $this->seq++;
        $category = FeeCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Fee '.$this->seq, 'code' => 'F'.$this->seq, 'is_active' => true,
        ]);

        return StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $this->enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => $amount, 'discount_amount' => 0, 'waiver_amount' => 0,
            'fine_amount' => 0, 'net_amount' => $amount, 'paid_amount' => 0,
            'balance' => $amount, 'due_date' => $dueDate, 'status' => 'unpaid',
        ]);
    }

    private function band(array $attrs = []): FeeFine
    {
        return FeeFine::create(array_merge([
            'branch_id' => $this->branch->id,
            'title' => 'Late', 'start_day' => 1, 'end_day' => null,
            'type' => 'fixed', 'amount' => 500, 'is_active' => true,
        ], $attrs));
    }

    private function service(): FeeFineService
    {
        return app(FeeFineService::class);
    }

    public function test_an_overdue_fee_attracts_the_penalty(): void
    {
        $this->band(['type' => 'fixed', 'amount' => 500]);
        $fee = $this->fee(10000, '2026-01-01');

        $this->service()->accrueAll('2026-02-01');
        $fee->refresh();

        $this->assertEquals(500, (float) $fee->fine_amount);
        $this->assertEquals(10500, (float) $fee->net_amount, 'the fine increases what is owed');
        $this->assertEquals(10500, (float) $fee->balance);
    }

    public function test_a_fee_that_is_not_yet_due_attracts_nothing(): void
    {
        $this->band();
        $fee = $this->fee(10000, '2026-12-31');

        $this->service()->accrueAll('2026-02-01');
        $fee->refresh();

        $this->assertEquals(0, (float) $fee->fine_amount);
        $this->assertEquals(10000, (float) $fee->net_amount);
    }

    public function test_accrual_is_a_recompute_not_an_increment(): void
    {
        $this->band(['amount' => 500]);
        $fee = $this->fee(10000, '2026-01-01');

        // Regression: the obvious implementation adds the fine every run, so
        // the amount owed grows each night without anything changing.
        $this->service()->accrueAll('2026-02-01');
        $this->service()->accrueAll('2026-02-01');
        $this->service()->accrueAll('2026-02-01');

        $fee->refresh();
        $this->assertEquals(500, (float) $fee->fine_amount);
    }

    public function test_deactivating_a_band_removes_its_charge(): void
    {
        $band = $this->band(['amount' => 500]);
        $fee = $this->fee(10000, '2026-01-01');

        $this->service()->accrueAll('2026-02-01');
        $this->assertEquals(500, (float) $fee->fresh()->fine_amount);

        $band->update(['is_active' => false]);
        $this->service()->accrueAll('2026-02-01');

        $this->assertEquals(0, (float) $fee->fresh()->fine_amount);
        $this->assertEquals(10000, (float) $fee->fresh()->net_amount);
    }

    public function test_the_most_specific_overlapping_band_wins(): void
    {
        $this->band(['title' => 'Month 1', 'start_day' => 1, 'end_day' => 30, 'amount' => 500]);
        $this->band(['title' => 'Beyond', 'start_day' => 31, 'end_day' => null, 'amount' => 2000]);

        $fee = $this->fee(10000, '2026-01-01');

        // 31 days late -> the later-starting band applies.
        $this->service()->accrueAll('2026-02-01');

        $this->assertEquals(2000, (float) $fee->fresh()->fine_amount);
    }

    public function test_a_percentage_band_charges_against_the_original_amount(): void
    {
        $this->band(['type' => 'percentage', 'amount' => 5]);
        $fee = $this->fee(10000, '2026-01-01');

        $this->service()->accrueAll('2026-02-01');

        $this->assertEquals(500, (float) $fee->fresh()->fine_amount);
    }

    public function test_part_paying_does_not_shrink_the_penalty(): void
    {
        $this->band(['type' => 'percentage', 'amount' => 5]);
        $fee = $this->fee(10000, '2026-01-01');

        app(PaymentRecorder::class)->record([
            'student_enrollment_id' => $this->enrollment->id,
            'target_fee_id' => $fee->id,
            'amount' => 6000,
            'payment_method' => 'cash',
            'payment_date' => '2026-01-20',
        ]);

        $this->service()->accrueAll('2026-02-01');
        $fee->refresh();

        // 5% of the 10,000 originally owed, not of the 4,000 still outstanding.
        $this->assertEquals(500, (float) $fee->fine_amount);
    }

    public function test_a_band_only_touches_the_categories_it_is_attached_to(): void
    {
        $tuition = $this->fee(10000, '2026-01-01');
        $levy = $this->fee(2000, '2026-01-01');

        $band = $this->band(['amount' => 500]);
        $band->feeCategories()->sync([$tuition->fee_category_id]);

        $this->service()->accrueAll('2026-02-01');

        $this->assertEquals(500, (float) $tuition->fresh()->fine_amount);
        $this->assertEquals(0, (float) $levy->fresh()->fine_amount, 'a PTA levy should not attract a tuition penalty');
    }

    public function test_a_settled_fee_attracts_nothing(): void
    {
        $this->band(['amount' => 500]);
        $fee = $this->fee(10000, '2026-01-01');
        $fee->update(['paid_amount' => 10000, 'balance' => 0, 'status' => 'paid']);

        $this->service()->accrueAll('2026-02-01');

        $this->assertEquals(0, (float) $fee->fresh()->fine_amount);
    }

    public function test_the_command_runs(): void
    {
        $this->band(['amount' => 500]);
        $this->fee(10000, '2026-01-01');

        $this->artisan('fees:accrue-fines', ['--as-of' => '2026-02-01'])->assertSuccessful();
    }

    public function test_the_screen_renders_and_a_band_can_be_added(): void
    {
        $this->get(route('admin.fee-fines.index'))->assertSuccessful();

        $this->post(route('admin.fee-fines.store'), [
            'title' => 'First month late',
            'start_day' => 1,
            'end_day' => 30,
            'type' => 'percentage',
            'amount' => 5,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(1, FeeFine::count());
    }

    public function test_an_end_day_before_the_start_day_is_rejected(): void
    {
        $this->post(route('admin.fee-fines.store'), [
            'title' => 'Backwards', 'start_day' => 30, 'end_day' => 10,
            'type' => 'fixed', 'amount' => 100,
        ])->assertSessionHasErrors('end_day');

        $this->assertSame(0, FeeFine::count());
    }
}
