<?php

namespace Tests\Feature\Academic;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Mark;
use App\Models\MarksSubmission;
use App\Models\SchoolSetting;
use App\Models\Sequence;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentSubject;
use App\Models\Subject;
use App\Models\TeacherSubjectAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A teacher could not log in at all — "no portal is available for your
 * role" — so every mark and every register was typed by an admin. Now a
 * teacher logs in to their own classes, marks only what they teach, keeps
 * only their own register, and cannot approve their own marks.
 */
class TeacherAccessTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $teacher;
    private AcademicSession $session;
    private Term $term;
    private Sequence $seq;
    private ClassSection $p3;
    private ClassSection $p4;
    private Subject $maths;
    private Subject $english;
    private StudentEnrollment $p3Pupil;
    private StudentEnrollment $p4Pupil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);
        SchoolSetting::create(['branch_id' => $this->branch->id, 'school_name' => 'Test School', 'school_code' => 'TS', 'school_level_mode' => 'both', 'pass_mark' => 10, 'max_mark' => 20]);

        $this->admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'super_admin', 'is_active' => true]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);

        // Created the way the HR screen creates staff: role name only, no role record attached.
        $this->teacher = User::create(['first_name' => 'Tom', 'last_name' => 'Teacher', 'email' => 'teacher@example.test', 'password' => 'password', 'role' => 'teacher', 'is_active' => true]);
        $this->teacher->branches()->attach($this->branch->id, ['is_default' => true]);

        $this->session = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2025/2026', 'start_date' => '2025-09-01', 'end_date' => '2026-07-31', 'status' => 'active', 'is_current' => true]);
        $this->term = Term::create(['branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term', 'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true]);
        $this->seq = Sequence::create(['branch_id' => $this->branch->id, 'name' => 'Seq 1', 'sequence_number' => 1, 'weight' => 1]);
        $batch = Batch::create(['branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true]);

        $this->maths = Subject::create(['branch_id' => $this->branch->id, 'name' => 'Mathematics', 'code' => 'MTH', 'is_active' => true]);
        $this->english = Subject::create(['branch_id' => $this->branch->id, 'name' => 'English', 'code' => 'ENG', 'is_active' => true]);

        foreach (['Primary 3' => 3, 'Primary 4' => 4] as $name => $order) {
            $form = Form::create(['branch_id' => $this->branch->id, 'name' => $name, 'short_name' => 'P'.$order, 'level' => 'primary', 'school_level' => 'nursery_primary', 'education_system' => 'english', 'has_streams' => false, 'display_order' => $order, 'is_active' => true]);
            $section = ClassSection::create(['branch_id' => $this->branch->id, 'form_id' => $form->id, 'section' => 'A', 'name' => 'P'.$order.'A', 'max_students' => 40, 'is_active' => true]);
            foreach ([$this->maths, $this->english] as $s) {
                DB::table('form_subject')->insert(['form_id' => $form->id, 'subject_id' => $s->id, 'coefficient' => 1, 'type' => 'core']);
            }
            DB::table('form_sequence')->insert(['form_id' => $form->id, 'term_id' => $this->term->id, 'sequence_id' => $this->seq->id]);

            $student = Student::create(['branch_id' => $this->branch->id, 'student_id' => 'TS'.$order, 'batch_id' => $batch->id, 'first_name' => 'Pupil', 'last_name' => $name, 'date_of_birth' => '2017-01-01', 'gender' => 'female', 'status' => 'active', 'admission_date' => '2025-09-05']);
            $enrollment = StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $student->id, 'academic_session_id' => $this->session->id, 'term_id' => $this->term->id, 'class_section_id' => $section->id, 'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active']);
            foreach ([$this->maths, $this->english] as $s) {
                StudentSubject::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $enrollment->id, 'subject_id' => $s->id, 'coefficient' => 1]);
            }

            if ($order === 3) {
                [$this->p3, $this->p3Pupil] = [$section, $enrollment];
            } else {
                [$this->p4, $this->p4Pupil] = [$section, $enrollment];
            }
        }

        // Class teacher of P3; teaches nothing in P4.
        $this->p3->update(['class_teacher_id' => $this->teacher->id]);
    }

    private function marks(ClassSection $section, Subject $subject, StudentEnrollment $pupil, float $score)
    {
        return $this->post(route('admin.marks.save'), [
            'class_section_id' => $section->id, 'subject_id' => $subject->id, 'sequence_id' => $this->seq->id,
            'marks' => [['enrollment_id' => $pupil->id, 'score' => $score]],
        ]);
    }

    public function test_a_teacher_logs_in_to_their_own_classes(): void
    {
        $this->post('/login', ['email' => 'teacher@example.test', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertSuccessful()
            ->assertSee('P3A')->assertDontSee('P4A')
            ->assertSee(__("Today's register"))
            ->assertSee('Mathematics');
    }

    public function test_the_dashboard_shows_each_subject_as_a_card_with_its_marks_and_todays_periods(): void
    {
        $this->actingAs($this->teacher);
        $this->marks($this->p3, $this->maths, $this->p3Pupil, 14)->assertSessionHas('success');
        \App\Models\TimetableEntry::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'class_section_id' => $this->p3->id, 'subject_id' => $this->maths->id, 'teacher_id' => $this->teacher->id, 'day_of_week' => 'monday', 'start_time' => '08:00', 'end_time' => '09:00']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('next monday 07:30'));

        $this->get(route('admin.dashboard'))->assertSuccessful()
            ->assertViewHas('cards', fn ($cards) => $cards->count() === 2
                && $cards->firstWhere('subject.id', $this->maths->id)['sequences'][0]['status'] === 'draft')
            ->assertViewHas('today', fn ($today) => $today->count() === 1)
            ->assertSee(__('My subjects'))
            ->assertSee(__(':n/:t entered', ['n' => 1, 't' => 1]))
            ->assertSee(__('Enter marks'));
    }

    public function test_marks_entry_opens_on_the_teachers_subject_cards(): void
    {
        $this->actingAs($this->teacher);
        $response = $this->get(route('admin.marks.index'))->assertSuccessful()
            ->assertSee(__('My assigned subjects'))
            ->assertViewHas('cards', fn ($cards) => $cards->pluck('section.name')->unique()->values()->all() === ['P3A']);

        // A sequence on a card opens that grid, and the grid is the teacher's.
        $url = $response->viewData('cards')->first()['sequences'][0]['url'];
        $this->get($url)->assertSuccessful()->assertViewHas('filtered', true);

        // Someone who teaches nothing keeps the filters, with no cards.
        $this->actingAs($this->admin)->get(route('admin.marks.index'))->assertSuccessful()
            ->assertViewHas('cards', fn ($cards) => $cards->isEmpty())
            ->assertDontSee(__('My assigned subjects'));
    }

    public function test_a_class_teacher_prints_their_own_class_list_and_no_other(): void
    {
        // Last year this pupil sat Primary 3 too: a repeater.
        $old = AcademicSession::create(['branch_id' => $this->branch->id, 'name' => '2024/2025', 'start_date' => '2024-09-01', 'end_date' => '2025-07-31', 'status' => 'closed', 'is_current' => false]);
        StudentEnrollment::create(['branch_id' => $this->branch->id, 'student_id' => $this->p3Pupil->student_id, 'academic_session_id' => $old->id, 'term_id' => $this->term->id, 'class_section_id' => $this->p3->id, 'residence_type' => 'day', 'enrollment_date' => '2024-09-05', 'status' => 'promoted']);

        $this->actingAs($this->teacher);

        // One class: it opens straight on it.
        $this->get(route('admin.class-list.index'))->assertSuccessful()
            ->assertSee('TS3')->assertSee('01/01/2017')->assertDontSee('TS4')
            ->assertSeeInOrder([__('Girls'), '1', __('Total'), '1', __('Repeaters'), '1']);

        $this->get(route('admin.class-list.index', ['class_section_id' => $this->p4->id]))->assertDontSee('TS4');
        $this->get(route('admin.class-list.csv', ['class_section_id' => $this->p4->id]))->assertNotFound();

        $csv = $this->get(route('admin.class-list.csv', ['class_section_id' => $this->p3->id]))->assertSuccessful()->streamedContent();
        $this->assertStringContainsString('TS3', $csv);

        $this->actingAs($this->admin);
        $this->get(route('admin.class-list.index', ['class_section_id' => $this->p4->id]))->assertSuccessful()->assertSee('TS4');
    }

    public function test_a_teacher_created_on_the_hr_screen_without_a_branch_link_still_sees_their_class(): void
    {
        // No screen links new staff to a branch; in a one-branch school that
        // must not leave them looking at an empty school.
        $this->teacher->branches()->detach();

        $this->actingAs($this->teacher)->get(route('admin.dashboard'))
            ->assertSuccessful()->assertSee('P3A');
        $r = $this->post(route('admin.attendance.store'), ['class_section_id' => $this->p3->id, 'date' => now()->toDateString(), 'attendance' => [$this->p3Pupil->id => ['status' => 'present']]]);
        $r->assertSessionMissing('error')->assertRedirect();
        $this->assertDatabaseHas('attendances', ['student_enrollment_id' => $this->p3Pupil->id, 'status' => 'present']);
    }

    public function test_the_teacher_menu_holds_their_work_and_nothing_else(): void
    {
        $html = $this->actingAs($this->teacher)->get(route('admin.dashboard'))->assertSuccessful()->getContent();

        $this->assertStringContainsString(route('admin.marks.index'), $html);
        $this->assertStringContainsString(route('admin.attendance.index'), $html);
        $this->assertStringNotContainsString(route('admin.collect-fees.index'), $html);
        $this->assertStringNotContainsString(route('admin.students.create'), $html);
    }

    public function test_a_class_teacher_at_primary_marks_every_subject_of_their_class_only(): void
    {
        $this->actingAs($this->teacher);

        $this->marks($this->p3, $this->maths, $this->p3Pupil, 14)->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->marks($this->p3, $this->english, $this->p3Pupil, 12)->assertSessionMissing('error');
        $this->assertSame(2, Mark::count());

        $this->marks($this->p4, $this->maths, $this->p4Pupil, 9)->assertForbidden();
        $this->assertSame(2, Mark::count());

        // Nor smuggle a P4 pupil into a P3 batch.
        $this->marks($this->p3, $this->maths, $this->p4Pupil, 3)->assertSessionHas('error');
    }

    public function test_a_subject_teacher_marks_only_the_subject_assigned(): void
    {
        TeacherSubjectAssignment::create(['branch_id' => $this->branch->id, 'academic_session_id' => $this->session->id, 'user_id' => $this->teacher->id, 'subject_id' => $this->maths->id, 'class_section_id' => $this->p4->id]);
        $this->actingAs($this->teacher);

        $this->marks($this->p4, $this->maths, $this->p4Pupil, 11)->assertSessionMissing('error');
        $this->marks($this->p4, $this->english, $this->p4Pupil, 11)->assertForbidden();
    }

    public function test_a_teacher_submits_but_cannot_approve_their_own_marks(): void
    {
        $this->actingAs($this->teacher);
        $this->marks($this->p3, $this->maths, $this->p3Pupil, 14);
        $submission = MarksSubmission::firstOrFail();

        $this->post(route('admin.marks.submit', $submission))->assertSessionMissing('error');
        $this->assertSame('submitted', $submission->fresh()->status);

        $this->post(route('admin.marks.approve', $submission))->assertForbidden();
        $this->assertSame('submitted', $submission->fresh()->status);
    }

    public function test_a_teacher_keeps_only_their_own_register(): void
    {
        $this->actingAs($this->teacher);

        // One class: the register opens on it.
        $this->get(route('admin.attendance.index'))->assertSuccessful()->assertSee('P3A')->assertDontSee('P4A');

        $this->post(route('admin.attendance.store'), ['class_section_id' => $this->p3->id, 'date' => now()->toDateString(), 'attendance' => [$this->p3Pupil->id => ['status' => 'present']]])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('attendances', ['student_enrollment_id' => $this->p3Pupil->id]);
        $this->post(route('admin.attendance.store'), ['class_section_id' => $this->p4->id, 'date' => now()->toDateString(), 'attendance' => [$this->p4Pupil->id => ['status' => 'present']]])->assertForbidden();
    }

    public function test_admin_screens_stay_closed_to_a_teacher(): void
    {
        $this->actingAs($this->teacher);

        $this->get(route('admin.collect-fees.index'))->assertForbidden();
        $this->get(route('admin.students.create'))->assertForbidden();
        $this->post(route('admin.report-cards.publish'), ['class_section_id' => $this->p3->id, 'term_id' => $this->term->id])->assertForbidden();
    }

    public function test_the_class_teacher_writes_remarks_and_the_head_writes_theirs(): void
    {
        $this->actingAs($this->teacher);
        $this->get(route('admin.report-cards.remarks', ['class_section_id' => $this->p3->id, 'term_id' => $this->term->id]))
            ->assertSuccessful()->assertSee('Primary 3 Pupil');

        $this->post(route('admin.report-cards.remarks.save'), [
            'class_section_id' => $this->p3->id, 'term_id' => $this->term->id,
            'remarks' => [$this->p3Pupil->id => ['class_teacher_remark' => 'Good work, can do better.', 'conduct' => 'very_good', 'principal_remark' => 'Sneaked in']],
        ])->assertSessionHas('success');

        $result = \App\Models\TermResult::where('student_enrollment_id', $this->p3Pupil->id)->firstOrFail();
        $this->assertSame('Good work, can do better.', $result->class_teacher_remark);
        $this->assertSame('very_good', $result->conduct);
        $this->assertNull($result->principal_remark, 'a teacher does not write the head remark');

        // Not the class teacher of P4: refused.
        $this->post(route('admin.report-cards.remarks.save'), ['class_section_id' => $this->p4->id, 'term_id' => $this->term->id, 'remarks' => []])->assertForbidden();

        $this->actingAs($this->admin);
        $this->post(route('admin.report-cards.remarks.save'), [
            'class_section_id' => $this->p3->id, 'term_id' => $this->term->id,
            'remarks' => [$this->p3Pupil->id => ['class_teacher_remark' => 'Good work, can do better.', 'conduct' => 'very_good', 'principal_remark' => 'Promoted with encouragement.']],
        ])->assertSessionHas('success');

        // Generating the cards afterwards keeps every remark.
        Mark::create(['branch_id' => $this->branch->id, 'student_enrollment_id' => $this->p3Pupil->id, 'subject_id' => $this->maths->id, 'sequence_id' => $this->seq->id, 'score' => 14, 'status' => 'approved']);
        app(\App\Services\TermResultCalculator::class)->persist($this->p3, $this->term, $this->session->id);

        $this->get(route('admin.report-cards.show', $result->fresh()))
            ->assertSuccessful()
            ->assertSee('Good work, can do better.')
            ->assertSee(__('Very good'))
            ->assertSee('Promoted with encouragement.')
            ->assertSee(__("Head Teacher's Remark"))
            ->assertSee('Tom Teacher');
    }

    public function test_the_head_assigns_who_teaches_what(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.teaching-assignments.index'))->assertSuccessful()->assertSee('P4A')->assertSee(__('Who Teaches What'));

        $this->put(route('admin.teaching-assignments.update', $this->p4), [
            'class_teacher_id' => $this->teacher->id,
            'subjects' => [$this->maths->id => $this->teacher->id, $this->english->id => ''],
        ])->assertSessionHas('success');

        $this->assertSame($this->teacher->id, $this->p4->fresh()->class_teacher_id);
        $this->assertSame(1, TeacherSubjectAssignment::where('class_section_id', $this->p4->id)->count());
    }
}
