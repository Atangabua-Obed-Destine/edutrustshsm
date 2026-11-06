<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassCouncil;
use App\Models\ClassCouncilEntry;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Form;
use App\Models\MarksSubmission;
use App\Models\Role;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Stream;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TermResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A Sixth Form class is one stream: Lower Sixth Science. Every way a student
 * gets into it must make them a Science student, with the Science subjects
 * and fees, and nothing about the class may wait on the Arts subjects.
 */
class SixthFormStreamTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $year;
    private Term $term;
    private Form $lowerSixth;
    private Stream $science;
    private Stream $arts;
    private ClassSection $lss;
    private Batch $batch;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test College', 'school_code' => 'TC', 'school_level_mode' => 'secondary', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Head', 'last_name' => 'User', 'email' => 'head@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->year = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-07', 'end_date' => '2027-07-02', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $this->batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2026', 'shortcode' => '26', 'is_active' => true]);

        $this->lowerSixth = Form::create(['branch_id' => $this->branch->id, 'name' => 'Lower Sixth', 'short_name' => 'LS', 'level' => 'second_cycle', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => true, 'display_order' => 6, 'is_active' => true]);
        $this->science = Stream::create(['branch_id' => $this->branch->id, 'name' => 'Science', 'code' => 'SCI', 'is_active' => true]);
        $this->arts = Stream::create(['branch_id' => $this->branch->id, 'name' => 'Arts', 'code' => 'ART', 'is_active' => true]);
        $this->lowerSixth->streams()->attach([$this->science->id, $this->arts->id]);
        foreach ([['MAT', 'Mathematics', $this->science], ['PHY', 'Physics', $this->science], ['LIT', 'Literature in English', $this->arts], ['HIS', 'History', $this->arts]] as [$code, $name, $stream]) {
            $subject = Subject::create(['branch_id' => $this->branch->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
            DB::table('form_subject')->insert(['form_id' => $this->lowerSixth->id, 'subject_id' => $subject->id, 'stream_id' => $stream->id, 'coefficient' => 5, 'type' => 'core']);
        }
        $this->lss = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $this->lowerSixth->id, 'stream_id' => $this->science->id, 'section' => 'S', 'name' => 'LSS', 'max_students' => 40, 'is_active' => true]);
    }

    private function codesOf(StudentEnrollment $enrollment): array
    {
        return $enrollment->studentSubjects()->join('subjects', 'subjects.id', '=', 'student_subjects.subject_id')->orderBy('subjects.code')->pluck('subjects.code')->all();
    }

    public function test_the_spreadsheet_import_makes_science_students_of_the_science_class(): void
    {
        $lab = FeeCategory::create(['branch_id' => $this->branch->id, 'name' => 'Lab Fee', 'code' => 'LAB', 'is_active' => true]);
        FeeStructure::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->year->id, 'form_id' => $this->lowerSixth->id, 'stream_id' => $this->science->id, 'fee_category_id' => $lab->id, 'amount' => 15000]);

        $this->post(route('admin.bulk-upload.upload'), [
            'class_section_id' => $this->lss->id, 'batch_id' => $this->batch->id,
            'csv_file' => UploadedFile::fake()->createWithContent('lss.csv', "Nom;Prénoms;Date de naissance;Sexe;Téléphone\r\nASHU;Precious;19/06/2009;F;677400001\r\n"),
        ])->assertSessionHasNoErrors()->assertSessionMissing('error')->assertSessionHas('success');

        $enrollment = StudentEnrollment::sole();
        $this->assertSame($this->science->id, $enrollment->stream_id);
        $this->assertSame(['MAT', 'PHY'], $this->codesOf($enrollment));
        $this->assertSame(15000.0, (float) $enrollment->fees()->sum('net_amount'), 'the Science laboratory fee');
    }

    public function test_any_placement_into_the_class_takes_its_stream(): void
    {
        // As promotion does it: a Form 5 student, who had no stream, moves up into LSS.
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TC260001', 'batch_id' => $this->batch->id, 'first_name' => 'Gladys', 'last_name' => 'BIH', 'date_of_birth' => '2010-08-25', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2026-09-07']);
        $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->year->id, 'term_id' => $this->term->id, 'class_section_id' => $this->lss->id, 'stream_id' => null, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $enrollment->syncCoreSubjects();

        $this->assertSame($this->science->id, $enrollment->fresh()->stream_id);
        $this->assertSame(['MAT', 'PHY'], $this->codesOf($enrollment));
    }

    public function test_a_class_can_only_be_a_stream_its_form_has(): void
    {
        $formFive = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 5', 'short_name' => 'F5', 'level' => 'first_cycle', 'school_level' => 'secondary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 5, 'is_active' => true]);

        $this->post(route('admin.class-sections.store'), ['form_id' => $formFive->id, 'stream_id' => $this->science->id, 'section' => 'A', 'name' => 'F5A', 'max_students' => 40])
            ->assertSessionHasErrors('stream_id');
        $this->post(route('admin.class-sections.store'), ['form_id' => $this->lowerSixth->id, 'stream_id' => $this->arts->id, 'section' => 'A', 'name' => 'LSA', 'max_students' => 40])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->arts->id, ClassSection::where('name', 'LSA')->value('stream_id'));
    }

    public function test_who_teaches_what_lists_only_the_streams_subjects(): void
    {
        $this->get(route('admin.teaching-assignments.index'))->assertSuccessful()
            ->assertViewHas('subjectsBySection', fn ($bySection) => $bySection->get($this->lss->id)->pluck('code')->sort()->values()->all() === ['MAT', 'PHY']);
    }

    public function test_report_cards_publish_without_waiting_for_the_other_streams_marks(): void
    {
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TC260002', 'batch_id' => $this->batch->id, 'first_name' => 'Daniel', 'last_name' => 'EWANE', 'date_of_birth' => '2010-02-12', 'gender' => 'male', 'status' => 'active', 'admission_date' => '2026-09-07']);
        $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->year->id, 'term_id' => $this->term->id, 'class_section_id' => $this->lss->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $enrollment->syncCoreSubjects();
        $sequence = Sequence::create(['branch_id' => $this->branch->id, 'sequence_number' => 1, 'name' => 'First Sequence', 'weight' => 1]);
        DB::table('form_sequence')->insert(['form_id' => $this->lowerSixth->id, 'term_id' => $this->term->id, 'sequence_id' => $sequence->id]);
        foreach (Subject::whereIn('code', ['MAT', 'PHY'])->pluck('id') as $subjectId) {
            MarksSubmission::create(['branch_id' => $this->branch->id, 'class_section_id' => $this->lss->id, 'academic_session_id' => $this->year->id, 'subject_id' => $subjectId, 'sequence_id' => $sequence->id, 'teacher_id' => $this->admin->id, 'status' => 'approved']);
        }
        $result = TermResult::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'term_id' => $this->term->id, 'term_average' => 12.5]);

        $this->post(route('admin.report-cards.publish'), ['class_section_id' => $this->lss->id, 'term_id' => $this->term->id])
            ->assertSessionHas('success')->assertSessionMissing('error');
        $this->assertTrue((bool) $result->fresh()->is_published);
    }

    public function test_a_teacher_who_keeps_the_bursary_is_still_offered_as_a_class_teacher(): void
    {
        $bursar = User::create(['first_name' => 'Mirabel', 'last_name' => 'Ngum', 'email' => 'ngum@example.test', 'password' => 'password', 'role' => User::primaryRoleFor(['teacher', 'accountant']), 'is_active' => true]);
        $bursar->roles()->sync(Role::whereIn('name', ['teacher', 'accountant'])->pluck('id'));

        $this->get(route('admin.class-sections.create'))->assertSuccessful()
            ->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $bursar->id));
        $this->get(route('admin.teaching-assignments.index'))->assertViewHas('teachers', fn ($teachers) => $teachers->contains('id', $bursar->id));
    }

    public function test_the_class_councils_decision_is_printed_on_the_report_card(): void
    {
        $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TC260003', 'batch_id' => $this->batch->id, 'first_name' => 'Ruth', 'last_name' => 'NKENG', 'date_of_birth' => '2010-04-17', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2026-09-07']);
        $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->year->id, 'term_id' => $this->term->id, 'class_section_id' => $this->lss->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);
        $result = TermResult::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'term_id' => $this->term->id, 'term_average' => 15.2, 'is_published' => true]);
        $council = ClassCouncil::create(['branch_id' => $this->branch->id, 'class_section_id' => $this->lss->id, 'term_id' => $this->term->id, 'academic_session_id' => $this->year->id, 'status' => 'open']);
        ClassCouncilEntry::create(['branch_id' => $this->branch->id, 'class_council_id' => $council->id, 'student_id' => $student->id, 'term_result_id' => $result->id, 'average' => 15.2, 'rank' => 1, 'award' => ClassCouncilEntry::HONOUR_ROLL, 'conduct' => 'very_good']);

        // Not while the council is still sitting.
        $this->get(route('admin.report-cards.show', $result))->assertSuccessful()->assertDontSee('Class council: Honour Roll');

        $council->update(['status' => ClassCouncil::CONCLUDED]);
        $this->get(route('admin.report-cards.show', $result))->assertSuccessful()
            ->assertSee('Class council: Honour Roll')
            ->assertSee('Very good');
    }
}
