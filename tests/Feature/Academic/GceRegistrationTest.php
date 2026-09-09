<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\GceCandidate;
use App\Models\GceRegistrationSession;
use App\Models\GceSubject;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\GceRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Every Cameroonian secondary school files GCE Ordinary and Advanced Level
 * entries with the Board each year, and the system had no notion of them — the
 * whole exercise ran on a spreadsheet beside a system that already holds the
 * students, classes and subjects it needs.
 *
 * The rules asserted here are the ones the Board enforces weeks later, when a
 * wrong entry is expensive to fix.
 */
class GceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private Form $formFive;
    private ClassSection $section;
    private GceRegistrationSession $series;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        SchoolSetting::create([
            'branch_id' => $this->branch->id,
            'school_name' => 'Test College', 'school_code' => 'TC',
        ]);

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->formFive = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 5', 'short_name' => 'F5',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 5, 'is_active' => true,
        ]);
        $this->section = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $this->formFive->id,
            'section' => 'A', 'name' => 'Form 5A', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->series = GceRegistrationSession::create([
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'name' => 'June 2026 O-Level', 'level' => 'o_level', 'exam_year' => 2026,
            'centre_number' => '12345',
            'min_subjects' => 6, 'max_subjects' => 9,
            'base_fee' => 5000, 'fee_per_subject' => 1000,
            'status' => 'open',
        ]);
        $this->series->forms()->sync([$this->formFive->id]);

        $this->term = $term;
    }

    private Term $term;

    /** @return array<int, int> ids of freshly created board subjects */
    private function boardSubjects(int $count, string $level = 'o_level'): array
    {
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $ids[] = GceSubject::create([
                'branch_id' => $this->branch->id,
                'code' => $level === 'o_level' ? '05'.str_pad((string) $i, 2, '0', STR_PAD_LEFT) : '07'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'name' => 'Subject '.$i,
                'level' => $level,
                'is_active' => true,
            ])->id;
        }

        return $ids;
    }

    private function student(string $first, string $id): Student
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => $id,
            'first_name' => $first, 'last_name' => 'Test',
            'date_of_birth' => '2010-01-01', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);

        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->section->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        return $student;
    }

    private function service(): GceRegistrationService
    {
        return app(GceRegistrationService::class);
    }

    // ------------------------------------------------------------ eligibility

    public function test_only_students_in_the_series_classes_are_eligible(): void
    {
        $this->student('Ann', 'MAIN0001');

        $otherForm = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);
        $otherSection = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $otherForm->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $younger = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'MAIN0002',
            'first_name' => 'Ben', 'last_name' => 'Test',
            'date_of_birth' => '2014-01-01', 'gender' => 'male',
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $younger->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $otherSection->id, 'residence_type' => 'day',
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        $eligible = $this->service()->eligible($this->series);

        $this->assertCount(1, $eligible);
        $this->assertSame('MAIN0001', $eligible->first()->student->student_id);
    }

    public function test_a_series_with_no_classes_offers_nobody(): void
    {
        $this->student('Ann', 'MAIN0001');
        $this->series->forms()->sync([]);

        // Otherwise it would silently offer the entire school.
        $this->assertCount(0, $this->service()->eligible($this->series->fresh()));
    }

    // ------------------------------------------------------------ the rules

    public function test_too_few_subjects_is_refused(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(9);

        $this->expectExceptionMessage('At least 6');
        $this->service()->register($this->series, $student, array_slice($subjects, 0, 3));
    }

    public function test_too_many_subjects_is_refused(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(11);

        $this->expectExceptionMessage('No more than 9');
        $this->service()->register($this->series, $student, $subjects);
    }

    public function test_subjects_from_the_wrong_level_do_not_count(): void
    {
        $student = $this->student('Ann', 'MAIN0001');

        // Six A-Level subjects do not make a legal O-Level entry.
        $this->expectException(RuntimeException::class);
        $this->service()->register($this->series, $student, $this->boardSubjects(6, 'a_level'));
    }

    public function test_a_student_cannot_be_entered_twice(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(7);

        $this->service()->register($this->series, $student, $subjects);

        $this->expectExceptionMessage('already entered');
        $this->service()->register($this->series, $student, $subjects);
    }

    public function test_entries_are_refused_while_the_series_is_not_open(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(7);
        $this->series->update(['status' => 'closed']);

        $this->expectExceptionMessage('Closed');
        $this->service()->register($this->series->fresh(), $student, $subjects);
    }

    // ------------------------------------------------------------ the entry

    public function test_a_valid_entry_is_numbered_and_priced(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(7);

        $candidate = $this->service()->register($this->series, $student, $subjects);

        // Numbers run within the series, prefixed by the Board's centre number.
        $this->assertSame('12345-0001', $candidate->candidate_number);

        // base 5000 + 7 x 1000.
        $this->assertEquals(12000.0, (float) $candidate->fee_amount);
        $this->assertCount(7, $candidate->subjects);
        $this->assertSame('draft', $candidate->status);
    }

    public function test_candidate_numbers_do_not_collide(): void
    {
        $subjects = $this->boardSubjects(7);

        $first = $this->service()->register($this->series, $this->student('Ann', 'MAIN0001'), $subjects);
        $second = $this->service()->register($this->series, $this->student('Ben', 'MAIN0002'), $subjects);

        $this->assertSame('12345-0001', $first->candidate_number);
        $this->assertSame('12345-0002', $second->candidate_number);
    }

    public function test_changing_subjects_reprices_the_entry(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(9);

        $candidate = $this->service()->register($this->series, $student, $subjects);
        $this->assertEquals(14000.0, (float) $candidate->fee_amount);

        // Dropping two subjects must lower what the school owes the Board.
        $this->service()->updateSubjects($candidate, array_slice($subjects, 0, 7));

        $this->assertEquals(12000.0, (float) $candidate->fresh()->fee_amount);
        $this->assertCount(7, $candidate->fresh()->subjects);
    }

    public function test_an_unpaid_entry_cannot_be_confirmed(): void
    {
        $candidate = $this->service()->register(
            $this->series, $this->student('Ann', 'MAIN0001'), $this->boardSubjects(7)
        );

        $this->expectExceptionMessage('not fully paid');
        $this->service()->setStatus($candidate, 'confirmed');
    }

    public function test_a_paid_entry_can_be_confirmed(): void
    {
        $candidate = $this->service()->register(
            $this->series, $this->student('Ann', 'MAIN0001'), $this->boardSubjects(7)
        );

        $candidate->update(['amount_paid' => 12000]);

        $this->assertSame('confirmed', $this->service()->setStatus($candidate->fresh(), 'confirmed')->status);
    }

    // ------------------------------------------------------------- the screens

    public function test_the_candidate_list_loads(): void
    {
        $this->service()->register($this->series, $this->student('Ann', 'MAIN0001'), $this->boardSubjects(7));

        $this->get(route('admin.gce.candidates.index', $this->series))
            ->assertSuccessful()
            ->assertSee('12345-0001');
    }

    public function test_entering_a_candidate_through_the_screen(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(7);

        $this->post(route('admin.gce.candidates.store', $this->series), [
            'student_id' => $student->id,
            'gce_subject_ids' => $subjects,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(1, GceCandidate::count());
    }

    public function test_the_screen_reports_a_refused_entry_instead_of_crashing(): void
    {
        $student = $this->student('Ann', 'MAIN0001');
        $subjects = $this->boardSubjects(9);

        $this->post(route('admin.gce.candidates.store', $this->series), [
            'student_id' => $student->id,
            'gce_subject_ids' => array_slice($subjects, 0, 2),
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, GceCandidate::count());
    }

    public function test_the_board_csv_has_one_row_per_candidate(): void
    {
        $this->service()->register($this->series, $this->student('Ann', 'MAIN0001'), $this->boardSubjects(7));

        $csv = $this->get(route('admin.gce.candidates.export', $this->series))
            ->assertSuccessful()
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('12345', $csv);
        $this->assertStringContainsString('MAIN0001', $csv);

        // The Board's template is one row per candidate, with the codes in a
        // single cell — not one row per subject.
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1);
    }

    public function test_the_entry_slip_and_list_render(): void
    {
        $candidate = $this->service()->register(
            $this->series, $this->student('Ann', 'MAIN0001'), $this->boardSubjects(7)
        );

        $this->get(route('admin.gce.candidates.slip', $candidate))
            ->assertSuccessful()->assertHeader('content-type', 'application/pdf');

        $this->get(route('admin.gce.candidates.entry-list', $this->series))
            ->assertSuccessful()->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_series_with_candidates_cannot_be_deleted(): void
    {
        $this->service()->register($this->series, $this->student('Ann', 'MAIN0001'), $this->boardSubjects(7));

        $this->delete(route('admin.gce.sessions.destroy', $this->series))
            ->assertSessionHas('error');

        $this->assertSame(1, GceRegistrationSession::count());
    }
}
