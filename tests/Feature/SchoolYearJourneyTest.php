<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Guardian;
use App\Models\MarksSubmission;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\BudgetLineTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One school year, driven through the real screens in the order a school
 * would do it: set up, price the fees, register pupils, collect money,
 * teach and mark, write the report cards, publish them to parents.
 *
 * Every step asserts on what the next step depends on, so a break anywhere
 * in the chain is named at the step where it happens.
 */
class SchoolYearJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $session;
    private Term $term;
    private Batch $batch;
    private Form $primary;
    private ClassSection $p3;
    private Form $nursery;
    private ClassSection $n1;
    private Subject $maths;
    private Subject $english;
    private Sequence $seq1;
    private Sequence $seq2;
    private FeeCategory $tuition;
    private FeeCategory $pta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'St Monica Nursery & Primary', 'school_code' => 'SM',
            'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20, 'promotion_threshold' => 10,
            'student_id_prefix' => 'SM',
        ]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $this->batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);

        $this->primary = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 3, 'is_active' => true]);
        $this->p3 = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->primary->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        $this->nursery = Form::create(['branch_id' => $this->branch->id, 'name' => 'Nursery 1', 'short_name' => 'N1', 'level' => 'nursery', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $this->n1 = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->nursery->id, 'section' => 'A', 'name' => 'N1', 'max_students' => 30, 'is_active' => true]);

        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MATH', 'school_level' => 'nursery_primary', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English Language', 'code' => 'ENG', 'school_level' => 'nursery_primary', 'is_active' => true]);
        foreach ([$this->maths, $this->english] as $subject) {
            DB::table('form_subject')->insert(['form_id' => $this->primary->id, 'subject_id' => $subject->id, 'coefficient' => 1, 'type' => 'core']);
        }

        $this->seq1 = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'First Sequence', 'sequence_number' => 1, 'weight' => 1]);
        $this->seq2 = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Second Sequence', 'sequence_number' => 2, 'weight' => 1]);
        foreach ([$this->seq1, $this->seq2] as $seq) {
            DB::table('form_sequence')->insert(['form_id' => $this->primary->id, 'term_id' => $this->term->id, 'sequence_id' => $seq->id]);
        }

        foreach ([['A', 16, 20, 'Excellent'], ['B', 14, 15.99, 'Good'], ['C', 10, 13.99, 'Average'], ['D', 0, 9.99, 'Weak']] as $i => [$g, $min, $max, $d]) {
            GradeScale::create(['branch_id' => $this->branch->id, 'grade' => $g, 'min_mark' => $min, 'max_mark' => $max, 'description' => $d, 'display_order' => $i + 1]);
        }

        $this->tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition Fee', 'code' => 'TUI', 'is_tuition' => true, 'is_mandatory' => true, 'is_active' => true]);
        $this->pta = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'PTA Levy', 'code' => 'PTA', 'is_tuition' => false, 'is_mandatory' => true, 'is_active' => true]);
    }

    private function register(string $first, string $last, ClassSection $section, string $dob, string $phone): Student
    {
        $this->post(route('admin.students.store'), [
            'batch_id' => $this->batch->id, 'admission_date' => '2025-09-05',
            'form_id' => $section->form_id, 'class_section_id' => $section->id, 'term_id' => $this->term->id,
            'residence_type' => 'day', 'cycle' => $section->form->level, 'education_system' => 'english',
            'first_name' => $first, 'last_name' => $last, 'date_of_birth' => $dob, 'gender' => 'female',
            'emergency_contact_name' => 'Mami '.$last, 'emergency_contact_phone' => $phone,
            'mother_name' => 'Mami '.$last, 'mother_phone' => $phone,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Student::where('first_name', $first)->where('last_name', $last)->firstOrFail();
    }

    public function test_one_school_year_from_setup_to_published_report_cards(): void
    {
        // ------------------------------------------------ 1. price the year
        $this->post(route('admin.fee-structures.store'), [
            'form_id' => $this->primary->id,
            'fees' => [
                ['category_id' => $this->tuition->id, 'amount' => 150000],
                ['category_id' => $this->pta->id, 'amount' => 5000],
            ],
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.fee-structures.store'), [
            'form_id' => $this->nursery->id,
            'fees' => [['category_id' => $this->tuition->id, 'amount' => 100000]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('fee_structures')->count(), 'step 1: fee structures priced');

        // ------------------------------------------------ 2. the sheet behind the year
        app(BudgetLineTemplate::class)->load();
        $this->post(route('admin.budget-line.link', BudgetLine::where('code', '615')->firstOrFail()), ['type' => 'fee_payment', 'category_id' => $this->pta->id])->assertSessionHas('success');
        $this->post(route('admin.budget-sheet.store'), ['title' => 'I&E 2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31'])->assertSessionMissing('error');
        $sheet = Budget::where('is_institutional', true)->firstOrFail();

        // ------------------------------------------------ 3. register pupils
        $bih = $this->register('Bih', 'Ngwa', $this->p3, '2017-04-11', '677000111');
        $ada = $this->register('Ada', 'Tabi', $this->p3, '2017-06-02', '677000222');
        $tiny = $this->register('Che', 'Fru', $this->n1, '2022-02-14', '677000333');

        $this->assertStringStartsWith('SM', $bih->student_id, 'step 3: matricule uses the school prefix');
        $bihEnrol = $bih->enrollments()->firstOrFail();
        $this->assertSame($this->p3->id, $bihEnrol->class_section_id);

        // Registering into a priced class bills the pupil at once.
        $fees = StudentFee::where('student_enrollment_id', $bihEnrol->id)->get();
        $this->assertCount(2, $fees, 'step 3: fees assigned from the structure on enrolment');
        $this->assertEquals(155000, $fees->sum('net_amount'));
        // And registers the class's subjects.
        $this->assertSame(2, $bihEnrol->studentSubjects()->count(), 'step 3: core subjects registered');

        $this->get(route('admin.students.index'))->assertSuccessful()->assertSee('Ngwa')->assertSee('Fru');

        // ------------------------------------------------ 4. collect money
        $tuitionFee = $fees->firstWhere('fee_category_id', $this->tuition->id);
        $this->post(route('admin.collect-fees.store-payment'), [
            'student_fee_id' => $tuitionFee->id, 'amount' => 100000, 'payment_method' => 'cash',
            'payment_date' => '2025-09-10', 'payer_name' => 'Mami Ngwa',
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');

        $payment = Payment::firstOrFail();
        $this->assertEquals(100000, (float) $payment->amount);
        $this->assertNotEmpty($payment->receipt_number, 'step 4: a receipt number is issued');
        $this->assertEquals(50000, (float) $tuitionFee->fresh()->balance, 'step 4: the balance moves');
        $this->get(route('admin.collect-fees.receipt', $payment))->assertSuccessful()->assertSee($payment->receipt_number)->assertSee('100,000');

        // The money shows in the cash book and lands on the tuition line of the sheet.
        $this->get(route('admin.daybook.index', ['month' => '2025-09']))->assertSuccessful()->assertSee('100,000')->assertSee('611');
        $this->get(route('admin.budget-sheet.show', $sheet))->assertSuccessful()->assertSee('100,000');
        $this->assertSame(0, (int) array_sum(app(\App\Services\BudgetActualsService::class)->forPeriod('2025-09-01', '2026-07-31')['unallocated']), 'step 4: nothing unallocated');

        // ------------------------------------------------ 5. marks, two sequences, two subjects
        foreach ([$this->seq1, $this->seq2] as $i => $seq) {
            foreach ([[$this->maths, [15, 12]], [$this->english, [11, 17]]] as [$subject, $scores]) {
                $this->post(route('admin.marks.save'), [
                    'class_section_id' => $this->p3->id, 'subject_id' => $subject->id, 'sequence_id' => $seq->id,
                    'marks' => [
                        ['enrollment_id' => $bihEnrol->id, 'score' => $scores[0] + $i],
                        ['enrollment_id' => $ada->enrollments()->firstOrFail()->id, 'score' => $scores[1] + $i],
                    ],
                ])->assertSessionHasNoErrors()->assertSessionMissing('error');
            }
        }
        $this->assertSame(4, MarksSubmission::count(), 'step 5: one submission per subject per sequence');

        foreach (MarksSubmission::all() as $submission) {
            $this->post(route('admin.marks.submit', $submission))->assertSessionMissing('error');
            $this->post(route('admin.marks.approve', $submission))->assertSessionMissing('error');
            $this->assertSame('approved', $submission->fresh()->status);
        }

        // ------------------------------------------------ 6. report cards
        $this->post(route('admin.report-cards.generate'), [
            'class_section_id' => $this->p3->id, 'term_id' => $this->term->id,
            'form_id' => $this->primary->id, 'academic_session_id' => $this->session->id,
        ])->assertSessionMissing('error');

        $result = TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $bih->id))->firstOrFail();
        $adaResult = TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $ada->id))->firstOrFail();
        // Bih: maths (15+16)/2 = 15.5, english (11+12)/2 = 11.5 → 13.5.
        // Ada: maths (12+13)/2 = 12.5, english (17+18)/2 = 17.5 → 15.0.
        $this->assertEquals(13.5, (float) $result->term_average, 'step 6: the average is the mean of equal-coefficient subjects');
        $this->assertEquals(15.0, (float) $adaResult->term_average);
        $this->assertSame(1, (int) $adaResult->class_rank, 'step 6: the higher average ranks first');
        $this->assertSame(2, (int) $result->class_rank);
        $this->assertSame(2, (int) $result->total_students);
        $this->assertEquals(14.25, (float) $result->class_average, 'step 6: class average of 13.5 and 15.0');

        $this->get(route('admin.report-cards.show', $result))->assertSuccessful()
            ->assertSee('Ngwa')->assertSee('13.50')->assertSee('Mathematics')->assertDontSee(__('Coeff'));

        // ------------------------------------------------ 7. the nursery child is assessed, not marked
        $this->post(route('admin.nursery.competences.seed'))->assertSessionHas('success');
        $tinyEnrol = $tiny->enrollments()->firstOrFail();
        $competence = \App\Models\NurseryCompetence::firstOrFail();
        $this->post(route('admin.nursery.update', [$tinyEnrol, $this->term]), [
            'ratings' => [$competence->id => 'acquired'], 'class_teacher_remark' => 'Settling in well.', 'then' => 'stay',
        ])->assertSessionHas('success');
        $tinyResult = TermResult::where('student_enrollment_id', $tinyEnrol->id)->firstOrFail();
        $this->assertNull($tinyResult->term_average, 'step 7: no average for a nursery child');

        // ------------------------------------------------ 8. publish, and the parent sees it
        $this->post(route('admin.report-cards.publish'), ['class_section_id' => $this->p3->id, 'term_id' => $this->term->id])->assertSessionHas('success');
        $this->assertTrue($result->fresh()->is_published);
        $this->assertFalse($tinyResult->fresh()->is_published, 'step 8: publishing P3 does not publish N1');

        $guardian = Guardian::whereHas('students', fn ($q) => $q->where('students.id', $bih->id))->first()
            ?? Guardian::where('mother_phone', '677000111')->first();
        $this->assertNotNull($guardian, 'step 8: registration created a guardian record for the mother');
        $guardian->update(['portal_access' => true]);
        if (! $guardian->students()->where('students.id', $bih->id)->exists()) {
            $guardian->students()->attach($bih->id, ['relationship' => 'Mother', 'is_primary' => true, 'can_access_portal' => true]);
        }

        $this->actingAs($guardian, 'guardians')->get(route('parent.report-cards.index', $bih->id))->assertSuccessful()->assertSee('First Term');
        $this->actingAs($guardian, 'guardians')->get(route('parent.report-cards.show', [$bih->id, $result]))->assertSuccessful()->assertSee('13.50');
        // ...but not the other family's child.
        $this->actingAs($guardian, 'guardians')->get(route('parent.report-cards.show', [$ada->id, $adaResult]))->assertForbidden();

        // ------------------------------------------------ 9. the year's money, at a glance
        $this->actingAs($this->admin);
        $r = $this->get(route('admin.daybook.summary', ['budget' => $sheet->id, 'month' => '2025-09']))->assertSuccessful();
        $r->assertSee('100,000');
        $this->get(route('admin.dashboard'))->assertSuccessful();
    }
}
