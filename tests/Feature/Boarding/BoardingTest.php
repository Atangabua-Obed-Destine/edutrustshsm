<?php

namespace Tests\Feature\Boarding;

use App\Models\AcademicSession;
use App\Models\BoardingAllocation;
use App\Models\BoardingHouse;
use App\Models\BoardingRoom;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\BoardingService;
use App\Services\StudentExitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Boarding houses, rooms and beds. The rules a house master keeps in their
 * head become the system's: no more boarders than beds, no girl in the boys'
 * house, a bed only for a pupil the school calls a boarder this year, and a
 * pupil who leaves the school leaves their bed.
 */
class BoardingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private AcademicSession $session;
    private Term $term;
    private ClassSection $section;
    private BoardingHouse $boys;
    private BoardingRoom $dorm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $form = Form::create(['branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1', 'level' => 'first_cycle', 'school_level' => 'secondary', 'has_streams' => false, 'display_order' => 1, 'is_active' => true]);
        $this->section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true]);

        $this->boys = BoardingHouse::create(['branch_id' => $this->branch->id, 'name' => 'St Joseph', 'gender' => 'boys', 'is_active' => true]);
        $this->dorm = $this->boys->rooms()->create(['branch_id' => $this->branch->id, 'name' => 'Dorm 1', 'beds' => 2, 'is_active' => true]);
    }

    private function pupil(string $name, string $gender = 'male', string $residence = 'boarding'): Student
    {
        $student = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'F1'.str_pad((string) (Student::count() + 1), 3, '0', STR_PAD_LEFT),
            'first_name' => $name, 'last_name' => 'Test', 'date_of_birth' => '2013-01-01', 'gender' => $gender,
            'status' => 'active', 'admission_date' => '2025-09-01',
        ]);
        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id,
            'term_id' => $this->term->id, 'class_section_id' => $this->section->id, 'residence_type' => $residence,
            'enrollment_date' => '2025-09-01', 'status' => 'active',
        ]);

        return $student;
    }

    private function boarding(): BoardingService
    {
        return app(BoardingService::class);
    }

    private function refused(callable $act, string $expect): void
    {
        try {
            $act();
            $this->fail('expected a refusal: '.$expect);
        } catch (ValidationException $e) {
            $this->assertStringContainsString($expect, collect($e->errors())->flatten()->join(' '));
        }
    }

    public function test_a_boarder_is_given_a_bed(): void
    {
        $ewane = $this->pupil('Ewane');

        $this->post(route('admin.boarding.allocate'), ['student_id' => $ewane->id, 'boarding_room_id' => $this->dorm->id, 'bed' => '1'])
            ->assertSessionHasNoErrors();

        $bed = BoardingAllocation::current()->sole();
        $this->assertSame($ewane->id, $bed->student_id);
        $this->assertSame('1', $bed->bed);
    }

    public function test_only_a_boarder_this_year_gets_a_bed(): void
    {
        $day = $this->pupil('Kum', residence: 'day');
        $half = $this->pupil('Tabe', residence: 'half_boarding');

        $this->refused(fn () => $this->boarding()->allocate($day, $this->dorm), 'not a boarder');
        $this->refused(fn () => $this->boarding()->allocate($half, $this->dorm), 'not a boarder');
    }

    public function test_the_boys_house_takes_no_girls(): void
    {
        $this->refused(fn () => $this->boarding()->allocate($this->pupil('Mary', 'female'), $this->dorm), 'does not take');
    }

    public function test_a_room_holds_no_more_boarders_than_beds(): void
    {
        $this->boarding()->allocate($this->pupil('One'), $this->dorm);
        $this->boarding()->allocate($this->pupil('Two'), $this->dorm);

        $this->refused(fn () => $this->boarding()->allocate($this->pupil('Three'), $this->dorm), 'full');
        $this->assertSame(2, BoardingAllocation::current()->count());
    }

    public function test_two_boarders_cannot_share_a_named_bed(): void
    {
        $this->boarding()->allocate($this->pupil('One'), $this->dorm, '1');

        $this->refused(fn () => $this->boarding()->allocate($this->pupil('Two'), $this->dorm, '1'), 'already taken');
    }

    public function test_a_move_closes_the_old_bed_and_keeps_the_history(): void
    {
        $ewane = $this->pupil('Ewane');
        $dorm2 = $this->boys->rooms()->create(['branch_id' => $this->branch->id, 'name' => 'Dorm 2', 'beds' => 4, 'is_active' => true]);

        $this->boarding()->allocate($ewane, $this->dorm);
        $this->boarding()->allocate($ewane, $dorm2);

        $this->assertSame(1, BoardingAllocation::current()->where('student_id', $ewane->id)->count());
        $this->assertSame($dorm2->id, BoardingAllocation::current()->where('student_id', $ewane->id)->value('boarding_room_id'));
        $old = BoardingAllocation::whereNotNull('end_date')->sole();
        $this->assertStringContainsString('Dorm 2', $old->end_reason);
    }

    public function test_a_pupil_who_leaves_the_school_leaves_their_bed(): void
    {
        $ewane = $this->pupil('Ewane');
        $this->boarding()->allocate($ewane, $this->dorm);

        app(StudentExitService::class)->record($ewane, StudentExitService::TRANSFERRED, '2026-02-01', 'Family moved', 'GBHS Buea');

        $this->assertSame(0, BoardingAllocation::current()->count());
        $this->assertSame('2026-02-01', BoardingAllocation::sole()->end_date->toDateString());
    }

    public function test_the_overview_says_who_needs_a_bed_and_who_should_not_hold_one(): void
    {
        $waiting = $this->pupil('Waiting');
        $placed = $this->pupil('Placed');
        $this->boarding()->allocate($placed, $this->dorm);
        // Placed has since become a day pupil.
        StudentEnrollment::where('student_id', $placed->id)->update(['residence_type' => 'day']);

        $overview = $this->boarding()->overview();

        $this->assertSame([$waiting->id], $overview['needBed']->pluck('id')->all());
        $this->assertSame([$placed->id], $overview['shouldLeave']->pluck('student_id')->all());
        $this->assertSame(2, $overview['totals']['beds']);
        $this->assertSame(1, $overview['totals']['occupied']);

        $this->get(route('admin.boarding.index'))->assertOk()->assertSee('Waiting Test')->assertSee('Placed Test')->assertSee('St Joseph');
    }

    public function test_a_room_cannot_shrink_below_its_boarders_or_be_deleted_with_history(): void
    {
        $this->boarding()->allocate($this->pupil('One'), $this->dorm);
        $this->boarding()->allocate($this->pupil('Two'), $this->dorm);

        $this->put(route('admin.boarding.rooms.update', $this->dorm), ['name' => 'Dorm 1', 'beds' => 1, 'is_active' => 1])->assertSessionHasErrors('beds');
        $this->delete(route('admin.boarding.rooms.destroy', $this->dorm))->assertSessionHasErrors('room');
        $this->assertDatabaseHas('boarding_rooms', ['id' => $this->dorm->id, 'beds' => 2]);
    }

    public function test_a_house_cannot_turn_away_the_boarders_already_in_it(): void
    {
        $this->boarding()->allocate($this->pupil('Ewane'), $this->dorm);

        $this->put(route('admin.boarding.houses.update', $this->boys), ['name' => 'St Joseph', 'gender' => 'girls', 'is_active' => 1])
            ->assertSessionHasErrors('gender');
        $this->assertSame('boys', $this->boys->fresh()->gender);
    }

    public function test_the_house_page_and_its_list_render(): void
    {
        $this->boarding()->allocate($this->pupil('Ewane'), $this->dorm, '1');

        $this->get(route('admin.boarding.show', $this->boys))->assertOk()->assertSee('Ewane Test')->assertSee('Dorm 1');
        $this->get(route('admin.boarding.print', $this->boys))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('admin.students.show', Student::first()))->assertOk()->assertSee('St Joseph');
    }

    public function test_someone_who_may_only_view_cannot_place_or_rebuild(): void
    {
        $role = Role::create(['name' => 'boarding-viewer', 'display_name' => 'Viewer', 'is_system' => false]);
        $role->permissions()->sync([Permission::firstOrCreate(['name' => 'boarding.view'], ['display_name' => 'View', 'group_name' => 'Boarding'])->id]);
        $viewer = User::create(['first_name' => 'Vi', 'last_name' => 'Ewer', 'email' => 'viewer@example.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $viewer->roles()->sync([$role->id]);
        $viewer->branches()->attach($this->branch->id, ['is_default' => true]);
        $ewane = $this->pupil('Ewane');

        $this->actingAs($viewer)->get(route('admin.boarding.index'))->assertOk()->assertDontSee(__('Give a bed'))->assertDontSee(__('Add a house'));
        $this->post(route('admin.boarding.allocate'), ['student_id' => $ewane->id, 'boarding_room_id' => $this->dorm->id])->assertForbidden();
        $this->post(route('admin.boarding.houses.store'), ['name' => 'New', 'gender' => 'mixed'])->assertForbidden();
        $this->assertSame(0, BoardingAllocation::count());
    }
}
