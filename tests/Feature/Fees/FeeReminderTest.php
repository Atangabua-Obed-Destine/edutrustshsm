<?php

namespace Tests\Feature\Fees;

use App\Mail\FeeReminder;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The system had complete fee, discount, fine and payment-plan modules and no
 * way at all to tell a parent they owed anything — every reminder was a phone
 * call or a letter sent home with the student.
 */
class FeeReminderTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private Term $term;
    private ClassSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id,
            'school_name' => 'Test College', 'school_code' => 'TC',
        ]);

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
        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
    }

    private function guardian(?string $email = 'parent@example.test'): Guardian
    {
        return Guardian::create([
            'branch_id' => $this->branch->id,
            'guardian_name' => 'Grace Guardian',
            'guardian_email' => $email,
            'guardian_phone' => '670000000',
            'emergency_contact_name' => 'Grace Guardian',
            'emergency_contact_phone' => '670000000',
        ]);
    }

    private function fee(Guardian $guardian, string $studentId, float $balance, ?string $dueDate = '2025-10-01'): StudentFee
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => $studentId,
            'first_name' => 'Ann', 'last_name' => $studentId,
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
            'guardian_id' => $guardian->id,
        ]);

        $enrollment = StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        $category = FeeCategory::create([
            'branch_id' => $this->branch->id,
            'name' => 'Tuition '.$studentId,
            'code' => 'TUI'.substr($studentId, -4),
            'is_active' => true,
        ]);

        return StudentFee::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'fee_category_id' => $category->id,
            'original_amount' => 50000, 'discount_amount' => 0, 'waiver_amount' => 0,
            'fine_amount' => 0, 'net_amount' => 50000,
            'paid_amount' => 50000 - $balance, 'balance' => $balance,
            'status' => $balance > 0 ? 'partial' : 'paid',
            'due_date' => $dueDate,
        ]);
    }

    public function test_an_overdue_fee_reaches_the_guardian(): void
    {
        $this->fee($this->guardian(), 'MAIN0001', 20000);

        $this->artisan('fees:remind')->assertSuccessful();

        Mail::assertQueued(FeeReminder::class, fn ($mail) => $mail->hasTo('parent@example.test'));
    }

    public function test_a_settled_fee_is_not_chased(): void
    {
        $this->fee($this->guardian(), 'MAIN0001', 0);

        $this->artisan('fees:remind')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_a_fee_not_yet_due_is_not_chased(): void
    {
        $this->fee($this->guardian(), 'MAIN0001', 20000, now()->addMonth()->toDateString());

        $this->artisan('fees:remind')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_siblings_are_gathered_into_one_message(): void
    {
        $guardian = $this->guardian();
        $this->fee($guardian, 'MAIN0001', 20000);
        $this->fee($guardian, 'MAIN0002', 15000);
        $this->fee($guardian, 'MAIN0003', 5000);

        $this->artisan('fees:remind')->assertSuccessful();

        // One letter with three balances, not three letters — the fastest way
        // to have reminders ignored is to send too many of them.
        Mail::assertQueuedCount(1);
        Mail::assertQueued(FeeReminder::class, fn ($mail) => $mail->lines->count() === 3 && (int) $mail->total === 40000);
    }

    public function test_each_guardian_gets_their_own_message(): void
    {
        $this->fee($this->guardian('one@example.test'), 'MAIN0001', 20000);
        $this->fee($this->guardian('two@example.test'), 'MAIN0002', 15000);

        $this->artisan('fees:remind')->assertSuccessful();

        Mail::assertQueuedCount(2);
    }

    public function test_a_guardian_with_no_email_is_reported_not_crashed(): void
    {
        $this->fee($this->guardian(null), 'MAIN0001', 20000);

        $this->artisan('fees:remind')
            ->expectsOutputToContain('no email address on file')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_a_fee_chased_recently_is_left_alone(): void
    {
        $fee = $this->fee($this->guardian(), 'MAIN0001', 20000);

        $this->artisan('fees:remind')->assertSuccessful();
        Mail::assertQueuedCount(1);

        $this->assertNotNull($fee->fresh()->last_reminded_at);

        // A daily schedule must not mail the same parent every morning.
        $this->artisan('fees:remind')->assertSuccessful();
        Mail::assertQueuedCount(1);
    }

    public function test_a_fee_can_be_chased_again_once_the_interval_passes(): void
    {
        $fee = $this->fee($this->guardian(), 'MAIN0001', 20000);

        $this->artisan('fees:remind')->assertSuccessful();

        $fee->update(['last_reminded_at' => now()->subDays(30)]);

        $this->artisan('fees:remind')->assertSuccessful();

        Mail::assertQueuedCount(2);
    }

    public function test_a_dry_run_sends_nothing_and_stamps_nothing(): void
    {
        $fee = $this->fee($this->guardian(), 'MAIN0001', 20000);

        $this->artisan('fees:remind --dry-run')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertNull($fee->fresh()->last_reminded_at);
    }

    public function test_reminders_can_be_switched_off(): void
    {
        $this->fee($this->guardian(), 'MAIN0001', 20000);

        setting(['fees.reminders_enabled' => false]);

        $this->artisan('fees:remind')
            ->expectsOutputToContain('switched off')
            ->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_the_days_overdue_option_narrows_the_run(): void
    {
        $this->fee($this->guardian(), 'MAIN0001', 20000, now()->subDays(3)->toDateString());

        $this->artisan('fees:remind --days-overdue=30')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->artisan('fees:remind --days-overdue=1')->assertSuccessful();
        Mail::assertQueuedCount(1);
    }
}
