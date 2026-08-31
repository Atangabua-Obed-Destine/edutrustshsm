<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Room;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saving a day's timetable checked exactly one thing: whether the teacher was
 * already booked in a DIFFERENT class section. Three clashes went through
 * untouched — two rows of the same grid overlapping (the class in two places at
 * once), the same teacher twice within one grid (excluded by the very filter
 * meant to find them), and rooms, which were not checked at all.
 *
 * The check also ran before the transaction rather than inside it.
 */
class TimetableConflictTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private AcademicSession $session;
    private ClassSection $sectionA;
    private ClassSection $sectionB;
    private Subject $maths;
    private Subject $english;
    private User $teacher;
    private User $otherTeacher;
    private Room $lab;
    private Room $hall;

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

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);

        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'has_streams' => false, 'display_order' => 1, 'is_active' => true,
        ]);

        $this->sectionA = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->sectionB = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'B', 'name' => 'Form 1B', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->maths = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'Maths', 'code' => 'MTH', 'is_active' => true,
        ]);
        $this->english = Subject::create([
            'branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true,
        ]);

        $this->teacher = User::create([
            'first_name' => 'Tom', 'last_name' => 'Teacher',
            'email' => 'tom@example.test', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);
        $this->otherTeacher = User::create([
            'first_name' => 'Rita', 'last_name' => 'Reader',
            'email' => 'rita@example.test', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);

        $this->lab = Room::create(['name' => 'Lab 1', 'type' => 'lab', 'capacity' => 40, 'is_active' => true]);
        $this->hall = Room::create(['name' => 'Hall', 'type' => 'hall', 'capacity' => 200, 'is_active' => true]);
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function save(ClassSection $section, array $entries)
    {
        return $this->post(route('admin.timetable.save-day-schedule'), [
            'class_section_id' => $section->id,
            'academic_session_id' => $this->session->id,
            'day_of_week' => 'monday',
            'entries' => $entries,
        ]);
    }

    /** @return array<string, mixed> */
    private function row(string $start, string $end, ?User $teacher = null, ?Room $room = null, ?Subject $subject = null): array
    {
        return [
            'subject_id' => ($subject ?? $this->maths)->id,
            'teacher_id' => ($teacher ?? $this->teacher)->id,
            'room_id' => $room?->id,
            'start_time' => $start,
            'end_time' => $end,
        ];
    }

    private function bookElsewhere(?User $teacher = null, ?Room $room = null): void
    {
        TimetableEntry::create([
            'branch_id' => $this->branch->id,
            'academic_session_id' => $this->session->id,
            'class_section_id' => $this->sectionB->id,
            'day_of_week' => 'monday',
            'subject_id' => $this->maths->id,
            'teacher_id' => ($teacher ?? $this->teacher)->id,
            'room_id' => $room?->id,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);
    }

    public function test_a_clean_day_saves(): void
    {
        $this->save($this->sectionA, [
            $this->row('08:00', '09:00', room: $this->lab),
            $this->row('09:00', '10:00', $this->otherTeacher, $this->hall, $this->english),
        ])->assertSessionHas('success');

        $this->assertSame(2, TimetableEntry::count());
    }

    public function test_back_to_back_periods_do_not_clash(): void
    {
        // Half-open intervals: 09:00 ends as 09:00 begins.
        $this->save($this->sectionA, [
            $this->row('08:00', '09:00', room: $this->lab),
            $this->row('09:00', '10:00', room: $this->lab),
        ])->assertSessionHas('success');

        $this->assertSame(2, TimetableEntry::count());
    }

    public function test_a_class_cannot_be_in_two_places_at_once(): void
    {
        // Regression: the grid was never checked against itself.
        $this->save($this->sectionA, [
            $this->row('08:00', '09:00'),
            $this->row('08:30', '09:30', $this->otherTeacher, null, $this->english),
        ])->assertSessionHas('error');

        $this->assertSame(0, TimetableEntry::count());
    }

    public function test_a_teacher_cannot_be_booked_twice_in_the_same_grid(): void
    {
        // Regression: the only teacher check excluded this very class section,
        // so the easiest double-booking to create was the one never looked for.
        $this->save($this->sectionA, [
            $this->row('08:00', '09:00'),
            $this->row('08:30', '09:30', $this->teacher, null, $this->english),
        ])->assertSessionHas('error');

        $this->assertSame(0, TimetableEntry::count());
    }

    public function test_a_teacher_already_teaching_another_class_is_refused(): void
    {
        $this->bookElsewhere();

        $this->save($this->sectionA, [$this->row('08:30', '09:30')])
            ->assertSessionHas('error');

        $this->assertSame(1, TimetableEntry::count());
    }

    public function test_a_room_taken_by_another_class_is_refused(): void
    {
        $this->bookElsewhere($this->otherTeacher, $this->lab);

        // Regression: rooms were not checked at all, so two classes could be put
        // in the same room at the same time.
        $this->save($this->sectionA, [$this->row('08:30', '09:30', room: $this->lab)])
            ->assertSessionHas('error');

        $this->assertSame(1, TimetableEntry::count());
    }

    public function test_a_room_cannot_be_used_twice_in_the_same_grid(): void
    {
        $this->save($this->sectionA, [
            $this->row('08:00', '09:00', room: $this->lab),
            $this->row('08:30', '09:30', $this->otherTeacher, $this->lab, $this->english),
        ])->assertSessionHas('error');

        $this->assertSame(0, TimetableEntry::count());
    }

    public function test_a_refused_save_leaves_the_existing_day_intact(): void
    {
        $this->save($this->sectionA, [$this->row('08:00', '09:00', room: $this->lab)])
            ->assertSessionHas('success');

        $this->bookElsewhere($this->otherTeacher, $this->hall);

        // The save deletes the day before rewriting it. The check now runs inside
        // that transaction, so a refusal must not leave the class with no Monday.
        $this->save($this->sectionA, [$this->row('08:30', '09:30', room: $this->hall)])
            ->assertSessionHas('error');

        $this->assertSame(
            1,
            TimetableEntry::where('class_section_id', $this->sectionA->id)->count()
        );
    }

    public function test_every_clash_is_reported_at_once(): void
    {
        $this->bookElsewhere($this->teacher, $this->lab);

        $this->save($this->sectionA, [$this->row('08:30', '09:30', room: $this->lab)]);

        $error = session('error');

        // One save used to surface one clash, so fixing a grid meant saving it
        // once per problem.
        $this->assertStringContainsString('Tom Teacher', $error);
        $this->assertStringContainsString('Lab 1', $error);
    }
}
