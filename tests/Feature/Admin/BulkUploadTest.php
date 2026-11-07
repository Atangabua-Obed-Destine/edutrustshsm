<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Migration day: a school's pupil list arrives as whatever Excel saved, in
 * French or English, and may be uploaded twice while it is being corrected.
 */
class BulkUploadTest extends TestCase
{
    use RefreshDatabase;

    private ClassSection $section;
    private Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'nursery_primary', 'pass_mark' => 10, 'max_mark' => 20]);
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $admin->branches()->attach($branch->id, ['is_default' => true]);
        $this->actingAs($admin);

        $year = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $this->batch = Batch::create(['branch_id' => $branch->id, 'name' => '2026', 'shortcode' => '26', 'is_active' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Primary 3', 'short_name' => 'P3', 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => 3, 'is_active' => true]);
        $this->section = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P3A', 'max_students' => 40, 'is_active' => true]);

        $tuition = FeeCategory::create(['branch_id' => $branch->id, 'name' => 'Tuition', 'code' => 'TUI', 'is_tuition' => true, 'is_active' => true, 'is_mandatory' => true]);
        FeeStructure::create(['branch_id' => $branch->id, 'academic_session_id' => $year->id, 'fee_category_id' => $tuition->id, 'form_id' => $form->id, 'amount' => 60000]);
    }

    private function upload(string $contents)
    {
        $file = UploadedFile::fake()->createWithContent('pupils.csv', $contents);

        return $this->post(route('admin.bulk-upload.upload'), [
            'csv_file' => $file, 'class_section_id' => $this->section->id, 'batch_id' => $this->batch->id,
        ]);
    }

    /** What a French Excel saves: semicolons, Windows-1252, day-first dates. */
    private function frenchExcelFile(): string
    {
        $csv = "Matricule;Nom;Prénom;Date de naissance;Sexe;Lieu de naissance;Father_Phone\r\n"
            ."OLD-0042;NGOÉ;Marie;05/06/2017;F;Bafoussam;677111222\r\n"
            .";TABI;Che;15/05/18;M;Bamenda;677333444\r\n"
            .";FRU;Ada;31/02/2017;X;;\r\n"
            .";;;;;;\r\n";

        return mb_convert_encoding($csv, 'Windows-1252', 'UTF-8');
    }

    public function test_a_french_excel_file_imports_with_fees_matricules_and_clear_errors(): void
    {
        $this->upload($this->frenchExcelFile())->assertRedirect()->assertSessionHas('success');

        $this->assertSame(2, Student::count());

        $marie = Student::where('student_id', 'OLD-0042')->firstOrFail();
        $this->assertSame('NGOÉ', $marie->last_name, 'accents survive the Windows encoding');
        $this->assertSame('2017-06-05', $marie->date_of_birth->toDateString(), 'day first: 5 June, not 6 May');
        $this->assertSame('female', $marie->gender);
        $this->assertSame('677111222', $marie->guardian->father_phone);

        $che = Student::where('first_name', 'Che')->firstOrFail();
        $this->assertSame('TS260001', $che->student_id, 'same matricule rule as the registration form');
        $this->assertSame('2018-05-15', $che->date_of_birth->toDateString());
        $this->assertSame($this->batch->id, $che->batch_id);

        $this->assertSame(2, StudentFee::count(), 'each pupil is billed the class fees');

        $errors = implode("\n", session('upload_errors'));
        $this->assertStringContainsString('FRU Ada', $errors);
        $this->assertStringContainsString('31/02/2017', $errors);
        $this->assertStringContainsString('"X"', $errors);
        $this->assertStringContainsString('phone', $errors);
    }

    public function test_uploading_the_same_file_again_adds_nobody_twice(): void
    {
        $this->upload($this->frenchExcelFile());
        $this->upload($this->frenchExcelFile())->assertSessionHas('error');

        $this->assertSame(2, Student::count());
        $this->assertStringContainsString('OLD-0042', implode("\n", session('upload_errors')));
    }

    public function test_the_template_round_trips(): void
    {
        $template = $this->get(route('admin.bulk-upload.template'))->assertSuccessful()->getContent();

        $this->upload("\xEF\xBB\xBF".$template)->assertSessionHas('success');

        $pupil = Student::firstOrFail();
        $this->assertSame('NGWA', $pupil->last_name);
        $this->assertSame('2018-05-15', $pupil->date_of_birth->toDateString());
    }
}
