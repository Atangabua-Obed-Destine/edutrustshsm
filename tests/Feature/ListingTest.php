<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared list toolbar (App\Support\Listing), on the student list: sort by
 * a column, choose rows per page, and export every row the filters select.
 */
class ListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Saint Joseph College', 'school_code' => 'SJC', 'school_level_mode' => 'secondary', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($branch->id, ['is_default' => true]);

        $year = AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        $term = Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
        $form = Form::create(['branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $a = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $b = ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'B', 'name' => 'Form 1B', 'max_students' => 40, 'is_active' => true]);

        // Thirty pupils: names run Pupil00 … Pupil29; the even ones in 1B.
        for ($i = 0; $i < 30; $i++) {
            $student = Student::create([
                'branch_id' => $branch->id, 'student_id' => sprintf('SJC26%04d', $i),
                'first_name' => 'Ann', 'last_name' => sprintf('Pupil%02d', $i),
                'date_of_birth' => '2014-01-01', 'gender' => $i % 3 ? 'female' : 'male',
                'status' => 'active', 'admission_date' => '2026-09-07',
            ]);
            StudentEnrollment::create([
                'branch_id' => $branch->id, 'student_id' => $student->id, 'academic_session_id' => $year->id,
                'term_id' => $term->id, 'class_section_id' => $i % 2 ? $a->id : $b->id,
                'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active',
            ]);
        }

        $this->actingAs($this->admin);
    }

    private function names($response): array
    {
        return $response->viewData('students')->getCollection()->map(fn ($s) => $s->last_name)->all();
    }

    public function test_the_list_opens_sorted_by_name_with_the_toolbar(): void
    {
        $response = $this->get(route('admin.students.index'))->assertOk()
            ->assertSee('data-list-region="students"', false)
            ->assertSee(__('Showing :from–:to of :total', ['from' => 1, 'to' => 25, 'total' => 30]));

        $this->assertSame('Pupil00', $this->names($response)[0]);
        $this->assertCount(25, $this->names($response));
    }

    public function test_a_column_heading_sorts_both_ways(): void
    {
        $desc = $this->get(route('admin.students.index', ['sort' => 'name', 'dir' => 'desc']));
        $this->assertSame('Pupil29', $this->names($desc)[0]);

        $byClass = $this->get(route('admin.students.index', ['sort' => 'class', 'dir' => 'asc', 'per_page' => 50]));
        $classes = $byClass->viewData('students')->getCollection()->map(fn ($s) => $s->currentEnrollment->classSection->name)->all();
        $this->assertSame(array_merge(array_fill(0, 15, 'Form 1A'), array_fill(0, 15, 'Form 1B')), $classes);
    }

    public function test_an_unknown_sort_is_ignored(): void
    {
        $response = $this->get(route('admin.students.index', ['sort' => 'password', 'dir' => 'desc']))->assertOk();

        $this->assertSame('Pupil29', $this->names($response)[0], 'falls back to the default column, in the direction asked');
    }

    public function test_rows_per_page_are_remembered(): void
    {
        $this->assertCount(10, $this->names($this->get(route('admin.students.index', ['per_page' => 10]))));
        $this->assertCount(10, $this->names($this->get(route('admin.students.index'))), 'the next visit keeps 10');

        // A size that is not offered is not taken.
        $this->assertCount(10, $this->names($this->get(route('admin.students.index', ['per_page' => 100000]))));
    }

    public function test_excel_has_every_filtered_row_not_just_the_page(): void
    {
        $response = $this->get(route('admin.students.index', ['per_page' => 10, 'export' => 'xlsx']))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));

        $file = $response->getFile()->getPathname();
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        $this->assertSame('Saint Joseph College', $sheet->getCell('A1')->getValue());
        $this->assertSame(__('Student ID'), $sheet->getCell('A5')->getValue());
        $this->assertSame('SJC260000', $sheet->getCell('A6')->getValue());
        $this->assertSame(5 + 30, $sheet->getHighestRow());
    }

    public function test_csv_follows_the_filters_and_the_sort(): void
    {
        $classB = ClassSection::where('name', 'Form 1B')->value('id');
        $response = $this->get(route('admin.students.index', ['class_section_id' => $classB, 'sort' => 'name', 'dir' => 'desc', 'export' => 'csv']))->assertOk();

        $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $response->streamedContent()))));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $lines[0], 'a byte-order mark for Excel');
        $this->assertCount(1 + 15, $lines);
        $this->assertStringContainsString('Pupil28', $lines[1]);
        $this->assertStringContainsString('Form 1B', $lines[1]);
    }

    public function test_pdf_and_print_carry_the_school_and_the_filters(): void
    {
        $this->get(route('admin.students.index', ['export' => 'pdf']))->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('admin.students.index', ['status' => 'active', 'export' => 'print']))->assertOk()
            ->assertSee('Saint Joseph College')
            ->assertSee(__('Status').': '.__('Active'))
            ->assertSee('Pupil29')
            ->assertSee('window.print()', false);
    }

    public function test_an_export_is_logged(): void
    {
        $this->get(route('admin.students.index', ['export' => 'csv']))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'exported', 'model_type' => 'list:students', 'user_id' => $this->admin->id]);
    }
}
