<?php

namespace Tests\Feature\Communication;

use App\Models\AcademicSession;
use App\Models\Announcement;
use App\Models\AnnouncementCategory;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Event;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The school notice board and calendar.
 *
 * The PTA used to be the only part of the school that could address parents.
 * What matters here is who a posting reaches: a notice aimed at one class must
 * not appear to the parents of another, a staff notice must not reach parents
 * at all, and a draft must reach nobody.
 */
class NoticeBoardTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Guardian $guardian;
    private Student $child;
    private ClassSection $childClass;
    private ClassSection $otherClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        SchoolSetting::create([
            'branch_id' => $this->branch->id, 'school_name' => 'Test School',
            'school_code' => 'TS', 'school_level_mode' => 'both',
        ]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);

        $session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true,
        ]);

        $form = $this->form('Form 1', 'F1', 1);
        $otherForm = $this->form('Form 2', 'F2', 2);

        $this->childClass = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
        $this->otherClass = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $otherForm->id,
            'section' => 'A', 'name' => 'Form 2A', 'max_students' => 40, 'is_active' => true,
        ]);

        $this->guardian = Guardian::create([
            'branch_id' => $this->branch->id,
            'guardian_name' => 'Mami Ngwa', 'guardian_phone' => '677000111',
            'guardian_email' => 'mami@example.test',
            'emergency_contact_name' => 'Mami Ngwa', 'emergency_contact_phone' => '677000111',
            'password' => bcrypt('password'), 'portal_enabled' => true,
        ]);

        $this->child = Student::create([
            'branch_id' => $this->branch->id, 'student_id' => 'TS250001',
            'batch_id' => $batch->id, 'guardian_id' => $this->guardian->id,
            'first_name' => 'Bih', 'last_name' => 'Ngwa',
            'date_of_birth' => '2012-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $this->child->id,
            'academic_session_id' => $session->id, 'term_id' => $term->id,
            'class_section_id' => $this->childClass->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);
    }

    private function form(string $name, string $short, int $order): Form
    {
        return Form::create([
            'branch_id' => $this->branch->id, 'name' => $name, 'short_name' => $short,
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => $order, 'is_active' => true,
        ]);
    }

    private function category(string $slug = 'general'): AnnouncementCategory
    {
        return AnnouncementCategory::where('slug', $slug)->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function notice(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category()->id,
            'reference' => Announcement::nextReference($this->branch->id),
            'title' => 'Term opens Monday',
            'body' => 'Please be on time.',
            'scope' => Announcement::SCOPE_ALL,
            'audience' => Announcement::AUDIENCE_GUARDIANS,
            'published_at' => now()->subHour(),
        ], $overrides));
    }

    // ------------------------------------------------------------ admin

    public function test_an_admin_posts_a_notice_and_it_gets_a_reference(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'category_id' => $this->category()->id,
                'title' => 'Fees due Friday',
                'body' => 'Second instalment.',
                'scope' => 'all',
                'audience' => 'guardians',
                'publish' => 1,
            ])->assertRedirect(route('admin.announcements.index'));

        $notice = Announcement::firstOrFail();
        $this->assertSame('NOT-'.now()->year.'-0001', $notice->reference);
        $this->assertTrue($notice->isPublished());
        $this->assertSame($this->admin->id, $notice->created_by);
    }

    public function test_a_targeted_notice_must_say_which_form(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.announcements.store'), [
                'category_id' => $this->category()->id,
                'title' => 'Form 1 trip', 'body' => 'Bring a packed lunch.',
                'scope' => 'form_specific', 'audience' => 'guardians',
            ])->assertSessionHasErrors('target_form_id');

        $this->assertSame(0, Announcement::count());
    }

    public function test_narrowing_a_notice_clears_the_target_it_no_longer_uses(): void
    {
        $notice = $this->notice([
            'scope' => Announcement::SCOPE_CLASS,
            'target_class_section_id' => $this->childClass->id,
        ]);

        // Widened to the whole school, the class must not linger: it would
        // silently narrow the notice again if the scope were ever changed back.
        $this->actingAs($this->admin)->put(route('admin.announcements.update', $notice), [
            'category_id' => $notice->category_id,
            'title' => $notice->title, 'body' => $notice->body,
            'scope' => 'all', 'audience' => 'guardians',
        ])->assertRedirect();

        $this->assertNull($notice->fresh()->target_class_section_id);
    }

    public function test_publishing_is_a_separate_act_from_writing(): void
    {
        $notice = $this->notice(['published_at' => null]);

        $this->assertFalse($notice->isPublished());

        $this->actingAs($this->admin)
            ->post(route('admin.announcements.toggle', $notice))
            ->assertRedirect();

        $this->assertTrue($notice->fresh()->isPublished());
    }

    // ------------------------------------------------------------ who sees what

    public function test_a_guardian_sees_a_school_wide_notice(): void
    {
        $this->notice(['title' => 'Term opens Monday']);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.index'))
            ->assertSuccessful()
            ->assertSee('Term opens Monday');
    }

    public function test_a_guardian_does_not_see_another_classs_notice(): void
    {
        $this->notice([
            'title' => 'Form 2A parents meeting',
            'scope' => Announcement::SCOPE_CLASS,
            'target_class_section_id' => $this->otherClass->id,
        ]);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.index'))
            ->assertSuccessful()
            ->assertDontSee('Form 2A parents meeting');
    }

    public function test_a_staff_notice_never_reaches_parents(): void
    {
        $notice = $this->notice([
            'title' => 'Staff briefing Thursday',
            'audience' => Announcement::AUDIENCE_STAFF,
        ]);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.index'))
            ->assertSuccessful()
            ->assertDontSee('Staff briefing Thursday');

        // Not merely hidden from the list — unreachable by its own link.
        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.show', $notice))
            ->assertNotFound();
    }

    public function test_a_draft_and_an_expired_notice_reach_nobody(): void
    {
        $this->notice(['title' => 'Draft notice', 'published_at' => null]);
        $this->notice(['title' => 'Old notice', 'published_at' => now()->subMonth(), 'expires_at' => now()->subDay()]);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.index'))
            ->assertSuccessful()
            ->assertDontSee('Draft notice')
            ->assertDontSee('Old notice');
    }

    public function test_a_notice_scheduled_for_later_is_not_out_yet(): void
    {
        $this->notice(['title' => 'Results day', 'published_at' => now()->addWeek()]);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.index'))
            ->assertSuccessful()
            ->assertDontSee('Results day');
    }

    // ------------------------------------------------------------ attachments

    public function test_an_attachment_is_private_but_a_guardian_can_still_read_it(): void
    {
        $this->actingAs($this->admin)->post(route('admin.announcements.store'), [
            'category_id' => $this->category()->id,
            'title' => 'Circular', 'body' => 'See attached.',
            'scope' => 'all', 'audience' => 'guardians', 'publish' => 1,
            'attachment' => UploadedFile::fake()->create('circular.pdf', 20, 'application/pdf'),
        ]);

        $notice = Announcement::firstOrFail();

        $this->assertTrue(Storage::disk('local')->exists($notice->attachment));
        $this->assertFalse(Storage::disk('public')->exists($notice->attachment));

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.files.notice', $notice))
            ->assertSuccessful();

        Storage::disk('local')->delete($notice->attachment);
    }

    public function test_a_guardian_cannot_read_an_attachment_on_a_notice_not_addressed_to_them(): void
    {
        $notice = $this->notice([
            'scope' => Announcement::SCOPE_CLASS,
            'target_class_section_id' => $this->otherClass->id,
            'attachment' => 'announcements/secret.pdf',
        ]);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.files.notice', $notice))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ the calendar

    public function test_the_calendar_shows_what_is_still_to_come(): void
    {
        Event::create([
            'branch_id' => $this->branch->id, 'title' => 'Sports Day',
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
            'scope' => Event::SCOPE_ALL, 'audience' => Event::AUDIENCE_GUARDIANS,
            'published_at' => now(),
        ]);

        Event::create([
            'branch_id' => $this->branch->id, 'title' => 'Last term prize giving',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->subMonth()->toDateString(),
            'scope' => Event::SCOPE_ALL, 'audience' => Event::AUDIENCE_GUARDIANS,
            'published_at' => now()->subMonths(2),
        ]);

        $response = $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.events'))
            ->assertSuccessful();

        $this->assertSame(['Sports Day'], $response->viewData('upcoming')->pluck('title')->all());
        $this->assertSame(['Last term prize giving'], $response->viewData('past')->pluck('title')->all());
    }

    public function test_an_event_running_today_still_counts_as_upcoming(): void
    {
        // Judged on the end date: a week-long event is worth showing on day three.
        Event::create([
            'branch_id' => $this->branch->id, 'title' => 'Mock exams',
            'starts_on' => now()->subDays(3)->toDateString(),
            'ends_on' => now()->addDays(3)->toDateString(),
            'scope' => Event::SCOPE_ALL, 'audience' => Event::AUDIENCE_GUARDIANS,
            'published_at' => now()->subWeek(),
        ]);

        $response = $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.events'))->assertSuccessful();

        $this->assertSame(['Mock exams'], $response->viewData('upcoming')->pluck('title')->all());
    }

    public function test_an_event_cannot_end_before_it_starts(): void
    {
        $this->actingAs($this->admin)->post(route('admin.events.store'), [
            'title' => 'Backwards',
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->toDateString(),
            'scope' => 'all', 'audience' => 'guardians',
        ])->assertSessionHasErrors('ends_on');

        $this->assertSame(0, Event::count());
    }

    // ------------------------------------------------------------ the PTA still works

    public function test_pta_announcements_still_show_on_the_pta_page_only(): void
    {
        $this->notice(['title' => 'General school notice']);
        $this->notice(['title' => 'PTA levy reminder', 'category_id' => $this->category('pta')->id]);

        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.pta.announcements'))
            ->assertSuccessful()
            ->assertSee('PTA levy reminder')
            ->assertDontSee('General school notice');

        // The notice board shows both: the PTA is part of the school.
        $this->actingAs($this->guardian, 'guardians')
            ->get(route('parent.notices.index'))
            ->assertSuccessful()
            ->assertSee('PTA levy reminder')
            ->assertSee('General school notice');
    }

    public function test_a_pta_announcement_posted_from_the_pta_screen_lands_in_the_pta_category(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pta.announcements.store'), [
            'title' => 'Meeting Saturday',
            'body' => 'In the school hall.',
            'scope' => 'all',
            'publish' => 1,
        ])->assertRedirect();

        $notice = Announcement::firstOrFail();
        $this->assertSame('pta', $notice->category->slug);
        $this->assertSame(Announcement::AUDIENCE_GUARDIANS, $notice->audience);
    }
}
