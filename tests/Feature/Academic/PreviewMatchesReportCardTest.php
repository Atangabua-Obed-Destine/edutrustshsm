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
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The exam-publishing preview and the printed report card disagreed: they had
 * separate copies of the arithmetic using a different subject universe, a
 * different coefficient source, and — on the preview — subjects the student was
 * not registered for feeding the average.
 *
 * A teacher approving results saw one number and the parent received another.
 * This asserts the two now agree.
 */
class PreviewMatchesReportCardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private Term $term;
    private Form $form;
    private ClassSection $section;
    private User $admin;

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

        GradeScale::create([
            'branch_id' => $this->branch->id, 'grade' => 'A',
            'min_mark' => 0, 'max_mark' => 20, 'description' => 'Pass', 'display_order' => 1,
        ]);
    }

    public function test_the_publishing_preview_and_the_report_card_agree(): void
    {
        // Two sequences with different weights, two subjects with different
        // coefficients, and a stray mark in a subject nobody is registered for
        // — the exact shape that made the two paths disagree.
        $seq1 = Sequence::create([
            'branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1,
        ]);
        $seq2 = Sequence::create([
            'branch_id' => $this->branch->id, 'name' => 'Seq 2', 'sequence_number' => 2, 'weight' => 3,
        ]);

        foreach ([$seq1, $seq2] as $sequence) {
            DB::table('form_sequence')->insert([
                'form_id' => $this->form->id, 'term_id' => $this->term->id, 'sequence_id' => $sequence->id,
            ]);
        }

        $maths = $this->subject('Maths', 'MTH', 4);
        $art = $this->subject('Art', 'ART', 1);
        $extra = $this->subject('Latin', 'LAT', 2);   // on the form, nobody registered

        $enrollment = $this->student('Ann');
        $this->register($enrollment, $maths, 4);
        $this->register($enrollment, $art, 1);

        $this->mark($enrollment, $maths, $seq1, 8);
        $this->mark($enrollment, $maths, $seq2, 16);
        $this->mark($enrollment, $art, $seq1, 12);
        $this->mark($enrollment, $art, $seq2, 12);
        // A stray mark that must not touch the average.
        $this->mark($enrollment, $extra, $seq1, 2);

        // What the teacher sees on the publishing screen.
        $preview = $this->get(route('admin.exam-publishing.index', [
            'academic_session_id' => $this->session->id,
            'form_id' => $this->form->id,
            'class_section_id' => $this->section->id,
            'term_id' => $this->term->id,
            'sequence_id' => $seq1->id,
        ]))->assertSuccessful()->viewData('previewStudents');

        $previewAverage = $preview[0]->overall_avg;

        // What the parent receives.
        $this->post(route('admin.report-cards.generate'), [
            'class_section_id' => $this->section->id,
            'term_id' => $this->term->id,
            'form_id' => $this->form->id,
            'academic_session_id' => $this->session->id,
        ])->assertRedirect();

        $reportCardAverage = (float) TermResult::firstOrFail()->term_average;

        // Maths (8*1 + 16*3)/4 = 14, Art 12. Overall (14*4 + 12*1)/5 = 13.6.
        $this->assertEquals(13.6, $previewAverage);
        $this->assertEquals(13.6, $reportCardAverage);
        $this->assertEquals($previewAverage, $reportCardAverage);
    }

    private function subject(string $name, string $code, float $coefficient): Subject
    {
        $subject = Subject::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'code' => $code, 'is_active' => true,
        ]);

        DB::table('form_subject')->insert([
            'form_id' => $this->form->id, 'subject_id' => $subject->id,
            'coefficient' => $coefficient, 'type' => 'core',
        ]);

        return $subject;
    }

    private function student(string $name): StudentEnrollment
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0001',
            'first_name' => $name, 'last_name' => 'Test',
            'date_of_birth' => '2012-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        return StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);
    }

    private function register(StudentEnrollment $enrollment, Subject $subject, float $coefficient): void
    {
        StudentSubject::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $enrollment->id,
            'subject_id' => $subject->id,
            'coefficient' => $coefficient,
        ]);
    }

    private function mark(StudentEnrollment $e, Subject $s, Sequence $q, float $score): void
    {
        Mark::create([
            'branch_id' => $this->branch->id,
            'student_enrollment_id' => $e->id,
            'subject_id' => $s->id,
            'sequence_id' => $q->id,
            'score' => $score,
            'is_absent' => false,
            'status' => 'approved',
        ]);
    }
}
