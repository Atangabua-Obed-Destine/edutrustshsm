<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\SchoolSetting;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The desktop shell around every admin screen: its own window, a session kept
 * alive while someone works, the status bar, toasts, and in-app dialogs in
 * place of the browser's confirm().
 */
class AppShellTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $branch->id, 'school_name' => 'Saint Joseph College', 'school_code' => 'SJC', 'pass_mark' => 10, 'max_mark' => 20]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($branch->id, ['is_default' => true]);

        AcademicSession::create(['branch_id' => $branch->id, 'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active', 'is_current' => true]);
        Term::create(['branch_id' => $branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2026-09-07', 'end_date' => '2026-12-18', 'is_current' => true]);
    }

    public function test_the_manifest_opens_the_dashboard_in_its_own_window(): void
    {
        $this->get(route('app.manifest'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'Saint Joseph College')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', route('admin.dashboard'))
            ->assertJsonPath('icons.0.src', route('app.icon', 192));
    }

    public function test_the_window_icon_is_a_png_of_the_size_asked(): void
    {
        $png = $this->get(route('app.icon', 192))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();

        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame([192, 192], [$width, $height]);

        $this->get(route('app.icon', 300))->assertNotFound();
    }

    public function test_keep_alive_answers_a_signed_in_person_with_the_form_token(): void
    {
        $this->getJson(route('admin.keep-alive'))->assertUnauthorized();

        $this->actingAs($this->admin)
            ->getJson(route('admin.keep-alive'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('token', session()->token());
    }

    public function test_every_screen_shows_the_year_and_term_in_the_status_bar(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.terms.index'))
            ->assertOk()
            ->assertSee('id="status-bar"', false)
            ->assertSee('2026/2027')
            ->assertSee('First Term')
            ->assertSee(route('app.manifest'), false)
            ->assertSee('"keepAlive":', false);
    }

    public function test_a_missing_current_term_is_shown_plainly(): void
    {
        Term::query()->update(['is_current' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.terms.index'))
            ->assertSee(__('No current term'));
    }

    public function test_a_message_after_saving_arrives_as_a_toast(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['error' => 'That term has marks and cannot be deleted.'])
            ->get(route('admin.terms.index'))
            ->assertSee('data-toast="error"', false)
            ->assertSee('That term has marks and cannot be deleted.');
    }

    public function test_no_screen_uses_the_browsers_own_confirm_box(): void
    {
        // data-ask="..." opens the app's own dialog instead (resources/js/shell.js).
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), 'return confirm('))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders);
    }

    public function test_the_palette_finds_pupils_for_someone_who_may_see_them(): void
    {
        $branch = Branch::first();
        \App\Models\SchoolSetting::query()->update(['school_level_mode' => 'secondary']);
        $form = \App\Models\Form::create(['branch_id' => $branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $class = \App\Models\ClassSection::create(['branch_id' => $branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);
        $pupil = \App\Models\Student::create(['branch_id' => $branch->id, 'student_id' => 'SJC260001', 'first_name' => 'Samuel', 'last_name' => 'ACHO', 'date_of_birth' => '2014-01-01', 'gender' => 'male', 'status' => 'active', 'admission_date' => '2026-09-07']);
        \App\Models\StudentEnrollment::create(['branch_id' => $branch->id, 'student_id' => $pupil->id, 'academic_session_id' => AcademicSession::first()->id, 'term_id' => Term::first()->id, 'class_section_id' => $class->id, 'residence_type' => 'day', 'enrollment_date' => '2026-09-07', 'status' => 'active']);

        $this->getJson(route('admin.palette.pupils', ['q' => 'acho']))->assertUnauthorized();

        $this->actingAs($this->admin);
        $this->getJson(route('admin.palette.pupils', ['q' => 'a']))->assertOk()->assertExactJson([]);
        $this->getJson(route('admin.palette.pupils', ['q' => 'samuel ach']))->assertOk()
            ->assertJsonPath('0.name', $pupil->full_name)
            ->assertJsonPath('0.detail', 'SJC260001 · Form 1A')
            ->assertJsonPath('0.url', route('admin.students.show', $pupil));

        // Someone without the student list gets no pupils, and no lookup address.
        $clerk = User::create(['first_name' => 'No', 'last_name' => 'Access', 'email' => 'clerk@example.test', 'password' => 'password', 'role' => 'teacher', 'is_active' => true]);
        $clerk->branches()->attach($branch->id, ['is_default' => true]);
        if (! $clerk->can('student.view')) {
            $this->actingAs($clerk)->getJson(route('admin.palette.pupils', ['q' => 'acho']))->assertForbidden();
        }
    }

    public function test_the_marks_and_attendance_grids_take_the_keyboard(): void
    {
        $marks = file_get_contents(resource_path('views/admin/marks/index.blade.php'));
        $register = file_get_contents(resource_path('views/admin/attendance/index.blade.php'));

        $this->assertStringContainsString('data-grid', $marks);
        $this->assertStringContainsString('data-grid', $register);
        $this->assertStringContainsString('data-key="{{ $label }}"', $register);
    }

    public function test_a_delete_asks_in_the_apps_own_dialog(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.terms.index'))
            ->assertSee('data-ask="'.e(__('Delete this term?')).'"', false);
    }
}
