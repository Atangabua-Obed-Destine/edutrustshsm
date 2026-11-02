<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassCouncil;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\JournalEntry;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\ClassCouncilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * What the November QA pass found, pinned so it cannot quietly return.
 */
class QaRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $year1;
    private Term $term;
    private Sequence $seq;
    private Form $form;
    private ClassSection $section;
    private Subject $maths;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'both', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->year1 = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $this->seq = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1]);
        $this->batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $this->form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $this->section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        DB::table('form_subject')->insert(['form_id' => $this->form->id, 'subject_id' => $this->maths->id, 'coefficient' => 4, 'type' => 'core']);
        DB::table('form_sequence')->insert(['form_id' => $this->form->id, 'term_id' => $this->term->id, 'sequence_id' => $this->seq->id]);
    }

    private function pupil(AcademicSession $year, string $id): StudentEnrollment
    {
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => $id, 'batch_id' => $this->batch->id, 'first_name' => 'Pupil', 'last_name' => $id, 'date_of_birth' => '2012-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);
        $e = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $year->id, 'term_id' => $this->term->id, 'class_section_id' => $this->section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);
        StudentSubject::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $e->id, 'subject_id' => $this->maths->id, 'coefficient' => 4]);

        return $e;
    }

    private function save(StudentEnrollment $e, float $score)
    {
        return $this->post(route('admin.marks.save'), [
            'class_section_id' => $this->section->id, 'subject_id' => $this->maths->id, 'sequence_id' => $this->seq->id,
            'marks' => [['enrollment_id' => $e->id, 'score' => $score]],
        ]);
    }

    // ------------------------------------------------------------ the second school year

    public function test_last_years_published_marks_do_not_lock_this_years(): void
    {
        $this->save($this->pupil($this->year1, 'A1'), 14)->assertSessionMissing('error');
        MarksSubmission::firstOrFail()->update(['status' => 'published']);

        $this->year1->update(['is_current' => false]);
        $year2 = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);

        $this->save($this->pupil($year2, 'B1'), 11)->assertSessionMissing('error');

        $this->assertSame(2, MarksSubmission::count(), 'one submission per year');
        $this->assertSame('published', MarksSubmission::where('academic_session_id', $this->year1->id)->value('status'), 'last year untouched');
        $this->assertSame('draft', MarksSubmission::where('academic_session_id', $year2->id)->value('status'));
    }

    public function test_each_year_holds_its_own_class_council(): void
    {
        $first = app(ClassCouncilService::class)->open($this->section, $this->term);

        $this->year1->update(['is_current' => false]);
        AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);

        $second = app(ClassCouncilService::class)->open($this->section, $this->term);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, ClassCouncil::count());
    }

    // ------------------------------------------------------------ grades

    public function test_an_average_between_two_bands_still_gets_a_grade(): void
    {
        GradeScale::create(['branch_id' => $this->branch->id, 'grade' => 'B+', 'min_mark' => 15, 'max_mark' => 15.9, 'description' => 'Good', 'display_order' => 2]);
        GradeScale::create(['branch_id' => $this->branch->id, 'grade' => 'A', 'min_mark' => 16, 'max_mark' => 17.9, 'description' => 'Very Good', 'display_order' => 1]);

        $this->assertSame('B+', GradeScale::getGrade(15.95)?->grade);
        $this->assertSame('A', GradeScale::getGrade(17.99)?->grade);
    }

    // ------------------------------------------------------------ publishing

    public function test_report_cards_are_not_published_while_a_subject_is_unapproved(): void
    {
        $this->save($this->pupil($this->year1, 'A1'), 14);

        $this->post(route('admin.report-cards.publish'), ['class_section_id' => $this->section->id, 'term_id' => $this->term->id])
            ->assertSessionHas('error')
            ->assertSessionHas('incomplete', ['Mathematics']);

        MarksSubmission::firstOrFail()->update(['status' => 'approved']);

        $this->post(route('admin.report-cards.publish'), ['class_section_id' => $this->section->id, 'term_id' => $this->term->id])
            ->assertSessionHas('success');
    }

    // ------------------------------------------------------------ money

    public function test_a_discount_cannot_swallow_money_already_paid(): void
    {
        $e = $this->pupil($this->year1, 'A1');
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        $fee = StudentFee::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $e->id, 'fee_category_id' => $tuition->id, 'original_amount' => 100000, 'net_amount' => 100000, 'paid_amount' => 100000, 'balance' => 0, 'status' => 'paid']);

        $this->post(route('admin.fee-discounts.store'), [
            'student_enrollment_id' => $e->id, 'discount_type' => 'percentage', 'value' => 50,
            'reason' => 'scholarship', 'apply_to' => 'all_fees',
        ])->assertSessionHas('error');

        $this->assertEquals(100000, (float) $fee->fresh()->net_amount);
    }

    public function test_a_percentage_discount_is_in_whole_francs(): void
    {
        $e = $this->pupil($this->year1, 'A1');
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        $fee = StudentFee::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $e->id, 'fee_category_id' => $tuition->id, 'original_amount' => 75500, 'net_amount' => 75500, 'paid_amount' => 0, 'balance' => 75500, 'status' => 'pending']);

        $this->post(route('admin.fee-discounts.store'), [
            'student_enrollment_id' => $e->id, 'discount_type' => 'percentage', 'value' => 12.5,
            'reason' => 'sibling', 'apply_to' => 'all_fees',
        ])->assertSessionMissing('error');

        $this->assertSame(0.0, fmod((float) $fee->fresh()->net_amount, 1.0), 'no half francs');
    }

    public function test_journal_numbers_keep_counting_past_9999(): void
    {
        $year = now()->year;
        DB::table('journal_entries')->insert([
            ['entry_number' => "JE-$year-9999", 'entry_date' => now()->toDateString(), 'journal_type' => 'general', 'description' => 'x', 'total_debit' => 0, 'total_credit' => 0, 'is_posted' => false, 'created_at' => now(), 'updated_at' => now()],
            ['entry_number' => "JE-$year-10000", 'entry_date' => now()->toDateString(), 'journal_type' => 'general', 'description' => 'y', 'total_debit' => 0, 'total_credit' => 0, 'is_posted' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->assertSame("JE-$year-10001", JournalEntry::generateEntryNumber());
    }
}
