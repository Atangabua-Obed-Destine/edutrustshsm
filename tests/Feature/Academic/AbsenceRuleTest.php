<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\TermResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Absence from a test works as it does in paxhitestbed.
 *
 * A finalised absence counts as 0 for that sequence and prints ABS. Absent
 * from every sequence of a subject, the subject is ABS and left out of the
 * term average. A mark nobody entered is not an absence. An absence only
 * counts once the marks are approved: an unreviewed default is not trusted.
 */
class AbsenceRuleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private Term $term;
    private Sequence $seq1;
    private Sequence $seq2;
    private ClassSection $section;
    private Subject $maths;
    private Subject $english;
    private StudentEnrollment $pupil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'both', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $this->seq1 = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1]);
        $this->seq2 = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Seq 2', 'sequence_number' => 2, 'weight' => 1]);
        $batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $this->section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true]);
        foreach ([$this->maths, $this->english] as $s) {
            DB::table('form_subject')->insert(['form_id' => $form->id, 'subject_id' => $s->id, 'coefficient' => 1, 'type' => 'core']);
        }
        foreach ([$this->seq1, $this->seq2] as $q) {
            DB::table('form_sequence')->insert(['form_id' => $form->id, 'term_id' => $this->term->id, 'sequence_id' => $q->id]);
        }
        GradeScale::create(['branch_id' => $this->branch->id, 'grade' => 'C', 'min_mark' => 0, 'max_mark' => 20, 'description' => 'Average', 'display_order' => 1]);

        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS1', 'batch_id' => $batch->id, 'first_name' => 'Bih', 'last_name' => 'Ngwa', 'date_of_birth' => '2012-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);
        $this->pupil = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id, 'term_id' => $this->term->id, 'class_section_id' => $this->section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);
        foreach ([$this->maths, $this->english] as $s) {
            StudentSubject::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->pupil->id, 'subject_id' => $s->id, 'coefficient' => 1]);
        }
    }

    private function mark(Subject $subject, Sequence $seq, ?float $score, bool $absent = false, string $status = 'approved'): void
    {
        Mark::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->pupil->id, 'subject_id' => $subject->id, 'sequence_id' => $seq->id, 'score' => $score, 'is_absent' => $absent, 'status' => $status]);
    }

    /** @return array<string, mixed> the pupil's subject row by subject name */
    private function subjects(): array
    {
        $student = app(TermResultCalculator::class)->compute($this->section, $this->term, $this->session->id)['students']->first();

        return collect($student->subjects)->keyBy('subject_name')->all() + ['_average' => $student->average];
    }

    public function test_an_absence_counts_as_zero_for_that_sequence(): void
    {
        $this->mark($this->maths, $this->seq1, 18);
        $this->mark($this->maths, $this->seq2, null, absent: true);
        $this->mark($this->english, $this->seq1, 12);
        $this->mark($this->english, $this->seq2, 12);

        $rows = $this->subjects();

        $this->assertSame(9.0, $rows['Mathematics']['term_average'], '(18 + 0) / 2, not 18');
        $this->assertSame([$this->seq2->id], $rows['Mathematics']['absent']);
        $this->assertSame(10.5, $rows['_average'], 'maths 9 and english 12');
    }

    public function test_absent_throughout_the_subject_is_abs_and_left_out_of_the_average(): void
    {
        $this->mark($this->maths, $this->seq1, null, absent: true);
        $this->mark($this->maths, $this->seq2, null, absent: true);
        $this->mark($this->english, $this->seq1, 12);
        $this->mark($this->english, $this->seq2, 14);

        $rows = $this->subjects();

        $this->assertSame('ABS', $rows['Mathematics']['grade']);
        $this->assertNull($rows['Mathematics']['term_average']);
        $this->assertSame(13.0, $rows['_average'], 'english alone');
    }

    public function test_a_mark_nobody_entered_is_not_an_absence(): void
    {
        $this->mark($this->maths, $this->seq1, 16);
        $this->mark($this->english, $this->seq1, 12);
        $this->mark($this->english, $this->seq2, 12);

        $rows = $this->subjects();

        $this->assertSame(16.0, $rows['Mathematics']['term_average'], 'seq 2 not entered: left out, not zero');
        $this->assertSame([], $rows['Mathematics']['absent']);
    }

    public function test_an_absence_not_yet_approved_does_not_count(): void
    {
        $this->mark($this->maths, $this->seq1, 18);
        $this->mark($this->maths, $this->seq2, null, absent: true, status: 'draft');
        $this->mark($this->english, $this->seq1, 12);

        $this->assertSame(18.0, $this->subjects()['Mathematics']['term_average']);
    }

    public function test_the_report_card_prints_abs(): void
    {
        $this->mark($this->maths, $this->seq1, 18);
        $this->mark($this->maths, $this->seq2, null, absent: true);
        $this->mark($this->english, $this->seq1, null, absent: true);
        $this->mark($this->english, $this->seq2, null, absent: true);

        app(TermResultCalculator::class)->persist($this->section, $this->term, $this->session->id);
        $result = TermResult::firstOrFail();

        $html = $this->get(route('admin.report-cards.show', $result))->assertSuccessful()->getContent();

        // Maths sequence 2, and English in both sequences.
        $this->assertSame(3, substr_count($html, '>'.__('ABS').'</td>'));
        $this->assertStringContainsString(__('Absent'), $html);
        $this->assertEquals(9.0, (float) $result->term_average, 'maths 9; english ABS left out');
    }
}
