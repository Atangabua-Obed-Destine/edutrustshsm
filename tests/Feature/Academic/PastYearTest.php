<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\ClassSection;
use App\Models\DefaultAccountMapping;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\FiscalYear;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\JournalEntryLine;
use App\Models\RecordedSheet;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentFee;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\PaymentRecorder;
use App\Services\TermResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The school began last year; it moves to the system this year. Last year
 * is recorded from the paper: where each pupil was, their marks as the
 * report cards gave them, and what was left unpaid — without disturbing the
 * present, and without a second, recomputed version of anyone's rank.
 */
class PastYearTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $secretary;
    private User $head;
    private AcademicSession $now;
    private ClassSection $p2a;
    private ClassSection $p3a;
    private Form $p3;
    /** @var array<int, Term> */
    private array $terms = [];
    private Student $bih;
    private Student $che;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        foreach (['secretary' => 'Sec', 'head' => 'Head'] as $key => $first) {
            $this->$key = User::create(['first_name' => $first, 'last_name' => 'User', 'email' => $key.'@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
            $this->$key->branches()->attach($this->branch->id, ['is_default' => true]);
        }
        $this->actingAs($this->secretary);

        $this->now = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        foreach ([1 => 'First Term', 2 => 'Second Term', 3 => 'Third Term'] as $n => $name) {
            $this->terms[$n] = Term::create(['branch_id' => $this->branch->id, 'term_number' => $n, 'name' => $name, 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => $n === 1]);
        }
        $batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $p2 = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary 2', 'short_name' => 'P2', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 5, 'is_active' => true]);
        $this->p3 = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 6, 'is_active' => true]);
        $this->p2a = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $p2->id, 'section' => 'A', 'name' => 'P2A', 'max_students' => 40, 'is_active' => true]);
        $this->p3a = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->p3->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        foreach ([['ENG', 'English', 2], ['MATH', 'Mathematics', 3]] as [$code, $name, $coef]) {
            $subject = Subject::create(['branch_id' => $this->branch->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
            $p2->subjects()->attach($subject->id, ['coefficient' => $coef, 'type' => 'core']);
        }

        foreach (['bih' => ['Bih', 'NGWA', 'TS250001'], 'che' => ['Che', 'FRU', 'TS250002']] as $key => [$first, $last, $mat]) {
            $this->$key = Student::create(['branch_id' => $this->branch->id, 'student_id' => $mat, 'batch_id' => $batch->id, 'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '2018-05-15', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-08']);
            StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $this->$key->id, 'academic_session_id' => $this->now->id, 'term_id' => $this->terms[1]->id, 'class_section_id' => $this->p3a->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        }
    }

    private function lastYear(): AcademicSession
    {
        $this->post(route('admin.past-years.store'), ['name' => '2025/2026', 'start_date' => '2025-09-08', 'end_date' => '2026-07-03'])->assertSessionHas('success');

        return AcademicSession::where('name', '2025/2026')->firstOrFail();
    }

    private function placeBoth(AcademicSession $year, ?int $cheIn = null): void
    {
        $this->post(route('admin.past-years.place', [$year, $this->p3a]), ['placement' => [
            $this->bih->id => $this->p2a->id,
            $this->che->id => $cheIn ?? $this->p2a->id,
        ]])->assertSessionHas('success');
    }

    private function sheet(AcademicSession $year, int $term, string $csv, int $outOf = 20)
    {
        return $this->post(route('admin.past-years.upload', [$year, $this->p2a, $this->terms[$term]]), [
            'csv_file' => UploadedFile::fake()->createWithContent('sheet.csv', $csv), 'out_of' => $outOf,
        ]);
    }

    public function test_a_past_year_must_end_before_the_current_one_and_can_never_become_current(): void
    {
        $this->post(route('admin.past-years.store'), ['name' => '2025/2026', 'start_date' => '2025-09-08', 'end_date' => '2026-10-01'])->assertSessionHas('error');

        $year = $this->lastYear();
        $this->assertTrue($year->is_recorded);
        $this->assertFalse($year->is_current);

        $this->post(route('admin.sessions.activate', $year))->assertSessionHas('error');
        $this->assertTrue($this->now->fresh()->is_current);
    }

    public function test_placing_pupils_bills_nothing_and_marks_repeaters(): void
    {
        $year = $this->lastYear();
        // A fee structure for P2 that year must not bill anyone: the year is history.
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true]);
        FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $year->id, 'fee_category_id' => $tuition->id, 'form_id' => $this->p2a->form_id, 'amount' => 50000]);

        $this->get(route('admin.past-years.placement', [$year, $this->p3a]))->assertSuccessful()
            ->assertViewHas('rows', fn ($rows) => $rows->every(fn ($r) => $r['suggested'] === $this->p2a->id));

        $this->placeBoth($year, cheIn: $this->p3a->id);

        $bih = StudentEnrollment::where('academic_session_id', $year->id)->where('student_id', $this->bih->id)->with('term')->get()->sortBy('term.term_number');
        $this->assertSame(['completed', 'completed', 'promoted'], $bih->pluck('status')->values()->all());
        $this->assertSame('repeated', StudentEnrollment::where('academic_session_id', $year->id)->where('student_id', $this->che->id)->where('term_id', $this->terms[3]->id)->value('status'));
        $this->assertSame(0, StudentFee::count());
        $this->assertSame($this->now->id, $this->bih->fresh()->currentEnrollment->academic_session_id);

        $this->get(route('admin.class-list.index', ['class_section_id' => $this->p3a->id]))->assertSuccessful()
            ->assertViewHas('rows', fn ($rows) => $rows->firstWhere('matricule', 'TS250002')['repeater'] === true
                && $rows->firstWhere('matricule', 'TS250001')['repeater'] === false);
    }

    public function test_a_mark_sheet_keeps_what_the_report_card_said_and_works_out_the_rest(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);

        // Bih's card said 14.00 and 1st; Che's average and rank were left blank.
        $this->sheet($year, 1, "matricule;name;ENG;MATH;average;rank;conduct\r\nTS250001;NGWA Bih;12;15,5;14;1;très bien\r\nTS250002;FRU Che;10;9;;;bien\r\n")
            ->assertSessionHas('success');

        $bih = TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $this->bih->id))->firstOrFail();
        $che = TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $this->che->id))->firstOrFail();
        $this->assertEquals(14.0, (float) $bih->term_average, 'the written average is kept');
        $this->assertSame(1, $bih->class_rank);
        $this->assertSame('very_good', $bih->conduct);
        $this->assertEquals(9.4, (float) $che->term_average, '(10x2 + 9x3) / 5');
        $this->assertSame(2, $che->class_rank);
        $this->assertSame('recorded', $che->source);
        $this->assertFalse((bool) $che->is_published, 'nobody sees it before it is verified');

        // The written 14.00 disagrees with its own marks (14.10): the verifier is told.
        $this->get(route('admin.past-years.sheet', [$year, $this->p2a, $this->terms[1]]))
            ->assertViewHas('discrepancies', fn ($lines) => count($lines) === 1 && str_contains($lines[0], 'NGWA'));
    }

    public function test_marks_out_of_ten_are_brought_to_the_schools_scale(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);

        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,6,8\nTS250002,5,5\n", outOf: 10)->assertSessionHas('success');

        $this->assertEquals(14.4, (float) TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $this->bih->id))->value('term_average'), '(12x2 + 16x3) / 5');
    }

    public function test_a_bad_line_records_nothing(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);

        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,12,25\nTS250002,10,9\nNOPE,10,10\n")->assertSessionHas('error');

        $this->assertSame(0, TermResult::count());
        $this->assertCount(2, session('upload_errors'));
    }

    public function test_someone_else_verifies_and_only_then_parents_see_it(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);
        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,12,15\nTS250002,10,9\n");
        $result = TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $this->bih->id))->firstOrFail();

        $guardian = Guardian::create(['branch_id' => $this->branch->id, 'portal_access' => true, 'emergency_contact_name' => 'Mami Ngwa', 'emergency_contact_phone' => '677000111']);
        $guardian->students()->attach($this->bih->id, ['relationship' => 'Mother', 'is_primary' => true, 'can_access_portal' => true]);
        $this->actingAs($guardian, 'guardians')->get(route('parent.report-cards.show', [$this->bih->id, $result]))->assertForbidden();

        // The person who entered it cannot verify it.
        $this->actingAs($this->secretary)->post(route('admin.past-years.verify', [$year, $this->p2a, $this->terms[1]]))->assertSessionHas('error');
        $this->actingAs($this->head)->post(route('admin.past-years.verify', [$year, $this->p2a, $this->terms[1]]))->assertSessionHas('success');
        $this->assertTrue((bool) $result->fresh()->is_published);

        $this->actingAs($guardian, 'guardians')->get(route('parent.report-cards.index', $this->bih->id))->assertSuccessful()->assertSee(__('Recorded'));
        $this->actingAs($guardian, 'guardians')->get(route('parent.report-cards.show', [$this->bih->id, $result]))->assertSuccessful()
            ->assertSee(__('Record of results'))->assertSee('Mathematics');

        // Locked: a new file is refused until the head reopens it, with a reason.
        $this->actingAs($this->secretary);
        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,1,1\nTS250002,1,1\n")->assertSessionHas('error');
        $this->actingAs($this->head)->post(route('admin.past-years.reopen', [$year, $this->p2a, $this->terms[1]]), ['reason' => 'MATH copied from the wrong column'])->assertSessionHas('success');
        $this->assertFalse((bool) $result->fresh()->is_published);
        $this->assertSame(1, AuditLog::where('action', 'recorded_sheet_reopened')->count());
    }

    public function test_the_annual_figures_follow_the_terms_unless_the_card_gave_them(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);
        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,10,10\nTS250002,12,12\n");
        $this->sheet($year, 2, "matricule,ENG,MATH\nTS250001,12,12\nTS250002,12,12\n");
        $this->sheet($year, 3, "matricule,ENG,MATH,annual_average,annual_rank\nTS250001,14,14,,\nTS250002,10,10,11.75,1\n");

        $final = fn (Student $s) => StudentEnrollment::where('academic_session_id', $year->id)->where('student_id', $s->id)->where('term_id', $this->terms[3]->id)->firstOrFail();
        $this->assertEquals(12.0, (float) $final($this->bih)->final_average, 'mean of 10, 12, 14');
        $this->assertEquals(11.75, (float) $final($this->che)->final_average, 'as written on the card');
        $this->assertSame(1, $final($this->che)->final_rank);

        // Sending the first term again does not touch what the card wrote.
        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,10,10\nTS250002,13,13\n")->assertSessionHas('success');
        $this->assertEquals(11.75, (float) $final($this->che)->final_average);
    }

    public function test_nothing_computes_over_a_recorded_year(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);
        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,12,15\nTS250002,10,9\n");

        $this->assertSame(0, app(TermResultCalculator::class)->persist($this->p2a, $this->terms[1], $year->id));
        $this->assertEquals(13.8, (float) TermResult::whereHas('enrollment', fn ($q) => $q->where('student_id', $this->bih->id))->value('term_average'));

        // A year with pupils in it cannot be deleted out from under them.
        $this->delete(route('admin.sessions.destroy', $year))->assertSessionHas('error');
    }

    public function test_history_shows_one_block_per_year_and_the_record_prints(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);
        $this->sheet($year, 1, "matricule,ENG,MATH\nTS250001,12,15\nTS250002,10,9\n");
        StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $this->bih->id, 'academic_session_id' => $this->now->id, 'term_id' => $this->terms[2]->id, 'class_section_id' => $this->p3a->id, 'residence_type' => 'day', 'enrollment_date' => '2027-01-04', 'status' => 'active']);

        $this->get(route('admin.students.history', $this->bih))->assertSuccessful()
            ->assertViewHas('history', fn ($h) => count($h['years']) === 2 && $h['years'][0]['recorded'] === true && $h['years'][1]['recorded'] === false)
            ->assertSee(__('Record of results'));

        $this->get(route('admin.past-years.record', [$year, $this->bih]))->assertSuccessful()->assertSee('13.80')->assertSee('P2A');
    }

    public function test_last_years_unpaid_fees_are_collected_first_and_clear_the_receivable(): void
    {
        $year = $this->lastYear();
        $this->placeBoth($year);
        $tuition = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true]);
        $thisYear = StudentFee::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->bih->currentEnrollment->id, 'fee_category_id' => $tuition->id, 'original_amount' => 60000, 'discount_amount' => 0, 'waiver_amount' => 0, 'net_amount' => 60000, 'paid_amount' => 0, 'balance' => 60000, 'status' => 'unpaid']);

        $this->post(route('admin.past-years.arrears', $year), ['csv_file' => UploadedFile::fake()->createWithContent('owed.csv', "matricule;category;amount\r\nTS250001;;15 000\r\nNOPE;;5000\r\n")])
            ->assertSessionHas('success');
        $this->assertCount(1, session('upload_errors'));
        $arrear = StudentFee::where('student_enrollment_id', '!=', $this->bih->currentEnrollment->id)->firstOrFail();
        $this->assertEquals(15000, (float) $arrear->balance);

        // Books: the arrear was last year's revenue, now a receivable.
        $fy = FiscalYear::create(['branch_id' => $this->branch->id, 'name' => 'FY2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_active' => true, 'is_closed' => false]);
        AccountingPeriod::create(['branch_id' => $this->branch->id, 'fiscal_year_id' => $fy->id, 'name' => 'October 2026', 'period_number' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'is_closed' => false]);
        $acc = fn ($code, $class, $normal) => ChartOfAccount::create(['branch_id' => $this->branch->id, 'account_code' => $code, 'account_name' => $code, 'class_number' => $class, 'account_type' => 'detail', 'normal_balance' => $normal, 'is_active' => true]);
        [$cash, $revenue, $receivable] = [$acc('571', 5, 'debit'), $acc('7061', 7, 'credit'), $acc('4111', 4, 'debit')];
        DefaultAccountMapping::create(['branch_id' => $this->branch->id, 'mapping_type' => 'fee_payment', 'category_id' => null, 'debit_account_id' => $cash->id, 'credit_account_id' => $revenue->id, 'status' => 'active']);
        DefaultAccountMapping::create(['branch_id' => $this->branch->id, 'mapping_type' => 'arrears_collected', 'category_id' => null, 'debit_account_id' => $cash->id, 'credit_account_id' => $receivable->id, 'status' => 'active']);

        app(PaymentRecorder::class)->record(['student_enrollment_id' => $this->bih->currentEnrollment->id, 'amount' => 20000, 'payment_method' => 'cash', 'payment_date' => '2026-10-05']);

        $this->assertEquals(0, (float) $arrear->fresh()->balance, 'the old debt is paid first');
        $this->assertEquals(55000, (float) $thisYear->fresh()->balance);
        $this->assertEquals(15000, (float) JournalEntryLine::where('account_id', $receivable->id)->sum('credit'));
        $this->assertEquals(5000, (float) JournalEntryLine::where('account_id', $revenue->id)->sum('credit'));
    }
}
