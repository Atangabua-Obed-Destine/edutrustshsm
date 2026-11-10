<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EdutrustPaySetting;
use App\Models\Form;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\EdutrustPay\PeopleSummaryService;
use App\Services\EdutrustPay\PeriodReportBuilder;
use EdutrustPay\Contract\Validator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How many students each form has, and who is on the staff, as sent to the
 * body's console. Students are never named; staff are, by number, name,
 * department and post, and never with their pay.
 */
class EdutrustPayPeopleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $lastYear;

    private AcademicSession $thisYear;

    private Term $first;

    private Term $second;

    private ClassSection $formOne;

    private ClassSection $formTwo;

    private Batch $batch;

    private int $next = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-07 10:00:00');

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'ST JEROME', 'is_active' => true]);

        EdutrustPaySetting::create([
            'branch_id' => $this->branch->id,
            'enabled' => true,
            'endpoint' => 'https://console.example.test',
            'institution_ref' => '11111111-1111-4111-8111-111111111111',
            'key_id' => 'etp_test',
            'secret_ciphertext' => 'a-test-secret-of-sufficient-length',
        ]);

        $this->lastYear = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-08', 'end_date' => '2026-07-03', 'status' => 'past', 'is_current' => false]);
        $this->thisYear = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        $this->first = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-10-16', 'is_current' => false]);
        $this->second = Term::create(['branch_id' => $this->branch->id, 'term_number' => 2, 'name' => 'Second Term', 'start_date' => '2026-10-19', 'end_date' => '2026-12-18', 'is_current' => true]);
        $this->batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);
        $this->formOne = $this->section('Form 1', 1);
        $this->formTwo = $this->section('Form 2', 2);
    }

    public function test_a_promoted_student_counts_once_in_the_new_session_and_a_term_move_counts_once(): void
    {
        // Last year in Form 1, promoted; this year in Form 2, first term closed and second term open.
        $bih = $this->student('Bih');
        $this->enrol($bih, $this->lastYear, $this->first, $this->formOne, '2025-09-08', 'promoted');
        $this->enrol($bih, $this->thisYear, $this->first, $this->formTwo, '2026-09-07', 'completed');
        $this->enrol($bih, $this->thisYear, $this->second, $this->formTwo, '2026-10-19', 'active');

        $che = $this->student('Che');
        $this->enrol($che, $this->thisYear, $this->first, $this->formOne, '2026-09-07', 'active');

        $people = new PeopleSummaryService($this->branch->id, '2026-10');

        $this->assertSame('2026/2027', $people->session()->name);
        $this->assertSame(['Form 1' => 1, 'Form 2' => 1], $people->studentsByForm());
        $this->assertSame(2, $people->studentCount(), 'Four enrolment rows are two students.');
    }

    public function test_a_student_drops_out_only_from_the_month_they_left_and_counts_from_the_month_they_joined(): void
    {
        $leaver = $this->student('Ngwa');
        $this->enrol($leaver, $this->thisYear, $this->first, $this->formOne, '2026-09-07', 'withdrawn');
        $leaver->update(['status' => 'withdrawn', 'withdrawal_date' => '2026-10-20']);

        $late = $this->student('Tabi');
        $this->enrol($late, $this->thisYear, $this->first, $this->formOne, '2026-10-05', 'active');

        $this->assertSame(1, (new PeopleSummaryService($this->branch->id, '2026-09'))->studentCount(), 'In September only the leaver was there.');
        $this->assertSame(1, (new PeopleSummaryService($this->branch->id, '2026-10'))->studentCount(), 'In October only the late joiner, the leaver having gone.');
    }

    public function test_the_report_carries_students_per_form_and_agrees_with_the_headcount(): void
    {
        foreach (['Bih', 'Che', 'Ngwa'] as $name) {
            $this->enrol($this->student($name), $this->thisYear, $this->first, $this->formOne, '2026-09-07', 'active');
        }

        $payload = app(PeriodReportBuilder::class)->build($this->branch->id, '2026-10');

        $this->assertContains('students', $payload['capabilities']);
        $this->assertSame(['session' => '2026/2027', 'total' => 3, 'by_class' => [['level' => 'Form 1', 'students' => 3]]], $payload['operational']['students']);
        // No payment yet, so the fee part (which needs a cash share) is left out;
        // the students are sent all the same, and agree with the fee headcount.
        $this->assertArrayNotHasKey('enrolment', $payload['operational']);
        $this->assertSame(3, (new \App\Services\EdutrustPay\FeeSummaryService($this->branch->id, '2026-10'))->enrolment());
        $this->assertTrue((new Validator)->validate($payload)['valid']);
    }

    public function test_the_staff_list_is_the_staff_on_the_books_with_their_department_and_post_and_never_their_pay(): void
    {
        $sciences = Department::create(['branch_id' => $this->branch->id, 'name' => 'Sciences']);
        $teacher = Designation::create(['branch_id' => $this->branch->id, 'title' => 'Teacher', 'slug' => 'teacher', 'status' => true]);

        $this->staffMember('Ada', ['staff_id' => 'STF001', 'department_id' => $sciences->id, 'designation_id' => $teacher->id, 'joining_date' => '2024-09-01']);
        $this->staffMember('Ben', ['staff_id' => 'STF002', 'joining_date' => '2026-09-01']);
        $this->staffMember('Left', ['staff_id' => 'STF003', 'joining_date' => '2020-01-01', 'ending_date' => '2026-08-31']);
        $this->staffMember('Later', ['staff_id' => 'STF004', 'joining_date' => '2026-11-03']);
        $this->staffMember('Off', ['staff_id' => 'STF005', 'is_active' => false]);
        $this->staffMember('Head', ['role' => 'admin']);
        $this->staffMember('Elsewhere', ['staff_id' => 'STF006'], attach: false);

        $payload = app(PeriodReportBuilder::class)->build($this->branch->id, '2026-10');
        $staff = $payload['operational']['staff'];

        $this->assertSame(2, $staff['total']);
        $this->assertSame([
            ['department' => 'Sciences', 'designation' => 'Teacher', 'name' => 'Ada TEST', 'number' => 'STF001'],
            ['department' => null, 'designation' => null, 'name' => 'Ben TEST', 'number' => 'STF002'],
        ], $staff['people']);
        $this->assertSame([['name' => 'Sciences', 'staff' => 1], ['name' => null, 'staff' => 1]], $staff['by_department']);
        $this->assertStringNotContainsString('salary', json_encode($payload));
        $this->assertStringNotContainsString('250000', json_encode($payload));
        $this->assertTrue((new Validator)->validate($payload)['valid']);
    }

    private function section(string $form, int $order): ClassSection
    {
        $formModel = Form::create(['branch_id' => $this->branch->id, 'name' => $form, 'short_name' => 'F'.$order, 'level' => 'secondary', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => $order, 'is_active' => true]);

        return ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $formModel->id, 'section' => 'A', 'name' => 'F'.$order.'A', 'max_students' => 40, 'is_active' => true]);
    }

    private function student(string $firstName): Student
    {
        return Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS'.$this->next++, 'batch_id' => $this->batch->id, 'first_name' => $firstName, 'last_name' => 'TEST', 'date_of_birth' => '2012-05-15', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-08']);
    }

    private function enrol(Student $student, AcademicSession $session, Term $term, ClassSection $section, string $date, string $status): void
    {
        StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $session->id, 'term_id' => $term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => $date, 'status' => $status]);
    }

    private function staffMember(string $firstName, array $extra = [], bool $attach = true): User
    {
        $user = User::create(array_merge([
            'first_name' => $firstName,
            'last_name' => 'TEST',
            'email' => strtolower($firstName).'@school.test',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
            'basic_salary' => 250000,
        ], $extra));

        if ($attach) {
            $user->branches()->attach($this->branch->id, ['is_default' => true]);
        }

        return $user;
    }
}
