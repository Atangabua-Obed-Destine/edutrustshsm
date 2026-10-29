<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Mark;
use App\Models\Payment;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One click on "Delete" used to cascade through enrolments into fees,
 * receipts, marks and attendance. Anything a pupil's history hangs off is
 * refused, with the reason, and the record is still there afterwards.
 */
class DestructionGuardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Term $term;
    private AcademicSession $session;
    private Form $form;
    private ClassSection $section;
    private StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary']);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $this->form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 3, 'is_active' => true]);
        $this->section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->form->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS0001', 'batch_id' => $batch->id, 'first_name' => 'Bih', 'last_name' => 'Ngwa', 'date_of_birth' => '2017-04-11', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);
        $this->enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id, 'term_id' => $this->term->id, 'class_section_id' => $this->section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);
    }

    public function test_a_section_that_held_a_pupil_cannot_be_deleted(): void
    {
        Payment::create(['branch_id' => $this->branch->id, 'receipt_number' => 'R1', 'student_enrollment_id' => $this->enrollment->id, 'amount' => 50000, 'payment_method' => 'cash', 'payment_date' => '2025-09-10', 'verification_status' => 'verified']);

        $this->delete(route('admin.class-sections.destroy', $this->section))->assertSessionHas('error');

        $this->assertNotNull($this->section->fresh());
        $this->assertSame(1, Payment::count(), 'the receipt survived');
    }

    public function test_a_class_with_sections_cannot_be_deleted(): void
    {
        $this->delete(route('admin.forms.destroy', $this->form))->assertSessionHas('error');
        $this->assertNotNull($this->form->fresh());
    }

    public function test_a_term_with_results_cannot_be_deleted(): void
    {
        TermResult::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->enrollment->id, 'term_id' => $this->term->id, 'term_average' => 12]);

        $this->delete(route('admin.terms.destroy', $this->term))->assertSessionHas('error');
        $this->assertSame(1, TermResult::count());
    }

    public function test_a_subject_with_marks_cannot_be_deleted(): void
    {
        $subject = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Maths', 'code' => 'MTH', 'is_active' => true]);
        $seq = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1]);
        Mark::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->enrollment->id, 'subject_id' => $subject->id, 'sequence_id' => $seq->id, 'score' => 14, 'status' => 'approved']);

        $this->delete(route('admin.subjects.destroy', $subject))->assertSessionHas('error');
        $this->assertSame(1, Mark::count());
    }

    public function test_an_unused_record_still_deletes(): void
    {
        $empty = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->form->id, 'section' => 'B', 'name' => 'P3B', 'max_students' => 40, 'is_active' => true]);

        $this->delete(route('admin.class-sections.destroy', $empty))->assertSessionHas('success');
        $this->assertNull($empty->fresh());
    }

    public function test_a_deactivated_account_is_stopped_at_the_next_request(): void
    {
        $this->get(route('admin.dashboard'))->assertSuccessful();

        $this->admin->update(['is_active' => false]);

        $this->get(route('admin.dashboard'))->assertForbidden();
    }
}
