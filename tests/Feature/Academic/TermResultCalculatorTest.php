<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\SubjectTermResult;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use App\Services\TermResultCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The term-result arithmetic existed twice — in report-card generation and in
 * the exam-publishing preview — with three differences, so the number a teacher
 * approved was routinely not the number that reached the report card.
 */
class TermResultCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private Term $term;
    private Form $form;
    private ClassSection $section;
    private User $admin;
    /** @var array<int, Sequence> */
    private array $sequences = [];
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

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $this->form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);

        foreach ([['A', 10], ['B', 20], ['C', 30]] as $i => [$g, $min]) {
            GradeScale::create([
                'branch_id' => $this->branch->id, 'grade' => $g,
                'min_mark' => $min === 10 ? 15 : ($min === 20 ? 10 : 0),
                'max_mark' => $min === 10 ? 20 : ($min === 20 ? 14.99 : 9.99),
                'description' => $g, 'display_order' => $i + 1,
            ]);
        }
    }

    /** Add a sequence mapped to this form and term. */
    private function sequence(string $name, float $weight = 50): Sequence
    {
        $this->seq++;
        $sequence = Sequence::create([
            'branch_id' => $this->branch->id,
            'name' => $name,
            'sequence_number' => $this->seq,
            'weight' => $weight,
        ]);

        DB::table('form_sequence')->insert([
            'form_id' => $this->form->id,
            'term_id' => $this->term->id,
            'sequence_id' => $sequence->id,
        ]);

        $this->sequences[] = $sequence;

        return $sequence;
    }

    private function subject(string $name, string $code, float $coefficient = 1): Subject
    {
        $subject = Subject::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);

        DB::table('form_subject')->insert([
            'form_id' => $this->form->id,
            'subject_id' => $subject->id,
            'coefficient' => $coefficient,
            'type' => 'core',
        ]);

        return $subject;
    }

    private function student(string $name): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN'.str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT).uniqid(),
            'first_name' => $name, 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
    }

    private function register(StudentEnrollment $enrollment, Subject $subject, float $coefficient = 1): void
    {
        StudentSubject::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'coefficient' => $coefficient,
        ]);
    }

    private function mark(StudentEnrollment $e, Subject $s, Sequence $q, ?float $score, string $status = 'approved'): void
    {
        Mark::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $e->id,
            'subject_id' => $s->id,
            'sequence_id' => $q->id,
            'score' => $score,
            'is_absent' => $score === null,
            'status' => $status,
        ]);
    }

    private function calc(): TermResultCalculator
    {
        return app(TermResultCalculator::class);
    }

    private function compute(): array
    {
        return $this->calc()->compute($this->section, $this->term, $this->session->id);
    }

    public function test_a_subject_average_is_weighted_by_sequence_weight(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $s2 = $this->sequence('Seq 2', 3);
        $maths = $this->subject('Maths', 'MTH');

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths);
        $this->mark($enrollment, $maths, $s1, 8);
        $this->mark($enrollment, $maths, $s2, 16);

        $result = $this->compute();
        $subject = $result['students']->first()->subjects[0];

        // (8*1 + 16*3) / 4 = 14
        $this->assertEquals(14, $subject['term_average']);
    }

    public function test_the_overall_average_is_weighted_by_coefficient(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH', 4);
        $art = $this->subject('Art', 'ART', 1);

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths, 4);
        $this->register($enrollment, $art, 1);
        $this->mark($enrollment, $maths, $s1, 15);
        $this->mark($enrollment, $art, $s1, 5);

        // (15*4 + 5*1) / 5 = 13
        $this->assertEquals(13, $this->compute()['students']->first()->average);
    }

    public function test_only_approved_marks_count(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths);
        $this->mark($enrollment, $maths, $s1, 18, 'draft');

        // The whole draft -> approved -> published workflow is meaningless if
        // drafts reach results.
        $this->assertNull($this->compute()['students']->first()->average);
    }

    public function test_an_absence_is_excluded_rather_than_scored_zero(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $s2 = $this->sequence('Seq 2', 1);
        $maths = $this->subject('Maths', 'MTH');

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths);
        $this->mark($enrollment, $maths, $s1, 16);
        $this->mark($enrollment, $maths, $s2, null);   // absent

        // 16, not 8 — an absence is not a zero.
        $this->assertEquals(16, $this->compute()['students']->first()->subjects[0]['term_average']);
    }

    public function test_a_subject_the_student_is_not_registered_for_is_ignored(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');
        $art = $this->subject('Art', 'ART');

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths);       // Art deliberately not registered
        $this->mark($enrollment, $maths, $s1, 15);
        $this->mark($enrollment, $art, $s1, 2);     // a stray mark

        $student = $this->compute()['students']->first();

        // The exam-publishing preview used to let this drag the average down.
        $this->assertCount(1, $student->subjects);
        $this->assertEquals(15, $student->average);
    }

    public function test_students_are_dense_ranked_with_ties_sharing_a_place(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');

        foreach (['Ann' => 18, 'Ben' => 15, 'Cid' => 15, 'Dee' => 9] as $name => $score) {
            $e = $this->student($name);
            $this->register($e, $maths);
            $this->mark($e, $maths, $s1, $score);
        }

        $ranks = $this->compute()['students']
            ->mapWithKeys(fn ($s) => [$s->student->first_name => $s->rank]);

        $this->assertSame(1, $ranks['Ann']);
        $this->assertSame(2, $ranks['Ben']);
        $this->assertSame(2, $ranks['Cid'], 'equal averages share a rank');
        $this->assertSame(4, $ranks['Dee'], 'the next rank skips the tie');
    }

    public function test_a_student_with_no_marks_is_left_out_of_ranking(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');

        $ann = $this->student('Ann');
        $this->register($ann, $maths);
        $this->mark($ann, $maths, $s1, 15);

        $ben = $this->student('Ben');
        $this->register($ben, $maths);   // no marks at all

        $result = $this->compute();

        // Regression: statistics filtered these out but ranking did not, so
        // class_rank could exceed total_students.
        $this->assertSame(1, $result['class']['count']);
        $this->assertNull($result['students']->firstWhere('enrollment_id', $ben->id)->rank);
        $this->assertLessThanOrEqual(
            $result['class']['count'],
            $result['students']->firstWhere('enrollment_id', $ann->id)->rank
        );
    }

    public function test_class_statistics_ignore_students_without_marks(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');

        foreach (['Ann' => 18, 'Ben' => 12] as $name => $score) {
            $e = $this->student($name);
            $this->register($e, $maths);
            $this->mark($e, $maths, $s1, $score);
        }

        $unmarked = $this->student('Cid');
        $this->register($unmarked, $maths);

        $stats = $this->compute()['class'];

        $this->assertSame(2, $stats['count']);
        $this->assertEquals(15, $stats['average']);
        $this->assertEquals(18, $stats['highest']);
        $this->assertEquals(12, $stats['lowest']);
    }

    public function test_more_than_three_sequences_are_all_retained(): void
    {
        $sequences = [
            $this->sequence('Seq 1', 1),
            $this->sequence('Seq 2', 1),
            $this->sequence('Seq 3', 1),
            $this->sequence('Seq 4', 1),
        ];
        $maths = $this->subject('Maths', 'MTH');

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths);
        foreach ($sequences as $i => $sequence) {
            $this->mark($enrollment, $maths, $sequence, 10 + $i);
        }

        $this->calc()->persist($this->section, $this->term, $this->session->id);

        $row = SubjectTermResult::firstOrFail();

        // The fixed sequence_1..3 columns silently dropped the fourth, even
        // though it counted towards the average.
        $this->assertCount(4, $row->sequence_scores);
        $this->assertEquals(11.5, (float) $row->term_average);
    }

    public function test_the_weights_used_are_snapshotted(): void
    {
        $s1 = $this->sequence('Seq 1', 2);
        $maths = $this->subject('Maths', 'MTH', 3);

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths, 3);
        $this->mark($enrollment, $maths, $s1, 12);

        $this->calc()->persist($this->section, $this->term, $this->session->id);
        $row = SubjectTermResult::firstOrFail();

        // Changing a weight later must not silently rewrite published results.
        $this->assertEquals(3, $row->resolved_weights['coefficient']);
        $this->assertEquals(2, $row->resolved_weights['sequences'][$s1->id]);
    }

    public function test_persisting_writes_results_and_ranks(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');

        foreach (['Ann' => 18, 'Ben' => 12] as $name => $score) {
            $e = $this->student($name);
            $this->register($e, $maths);
            $this->mark($e, $maths, $s1, $score);
        }

        $count = $this->calc()->persist($this->section, $this->term, $this->session->id);

        $this->assertSame(2, $count);
        $this->assertSame(2, TermResult::count());
        $this->assertSame(2, SubjectTermResult::count());
        $this->assertSame(1, TermResult::where('class_rank', 1)->count());
        $this->assertSame(2, SubjectTermResult::whereNotNull('subject_rank')->count());
    }

    public function test_regenerating_does_not_unpublish(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $maths = $this->subject('Maths', 'MTH');

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths);
        $this->mark($enrollment, $maths, $s1, 15);

        $this->calc()->persist($this->section, $this->term, $this->session->id);
        TermResult::query()->update(['is_published' => true]);

        $this->calc()->persist($this->section, $this->term, $this->session->id);

        $this->assertTrue((bool) TermResult::firstOrFail()->is_published);
    }

    public function test_computing_a_class_does_not_query_per_mark(): void
    {
        $s1 = $this->sequence('Seq 1', 1);
        $s2 = $this->sequence('Seq 2', 1);
        $subjects = [
            $this->subject('Maths', 'MTH'),
            $this->subject('English', 'ENG'),
            $this->subject('Science', 'SCI'),
        ];

        for ($i = 0; $i < 10; $i++) {
            $e = $this->student('S'.$i);
            foreach ($subjects as $subject) {
                $this->register($e, $subject);
                $this->mark($e, $subject, $s1, 12);
                $this->mark($e, $subject, $s2, 14);
            }
        }

        DB::enableQueryLog();
        $this->compute();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 10 students x 3 subjects x 2 sequences was 60 separate mark lookups
        // before; the whole computation is now a handful of queries.
        $this->assertLessThan(15, $queries, "computing the class issued {$queries} queries");
    }
}
