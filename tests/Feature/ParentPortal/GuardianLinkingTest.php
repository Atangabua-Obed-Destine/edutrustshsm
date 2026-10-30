<?php

namespace Tests\Feature\ParentPortal;

use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassSection;
use App\Models\Form;
use App\Models\Guardian;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Term;
use App\Models\User;
use App\Services\GuardianLinkService;
use App\Services\GuardianMergeService;
use App\Services\GuardianResolver;
use App\Support\ParentContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is a parent to whom.
 *
 * A child could have exactly one guardian, and guardians were matched only by
 * email — which the registration form never asked for. The result was six real
 * parents spread across fourteen records, each showing one child, and no way
 * to join them or to give a mother her own login.
 */
class GuardianLinkingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private Batch $batch;
    private ClassSection $class;
    private AcademicSession $session;
    private Term $term;

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
        $this->actingAs($this->admin);

        $this->session = AcademicSession::create([
            'branch_id' => $this->branch->id, 'name' => '2025/2026',
            'start_date' => '2025-09-01', 'end_date' => '2026-07-31',
            'status' => 'active', 'is_current' => true,
        ]);
        $this->term = Term::create([
            'branch_id' => $this->branch->id, 'term_number' => 1, 'name' => 'First Term',
            'start_date' => '2025-09-01', 'end_date' => '2025-12-15', 'is_current' => true,
        ]);
        $this->batch = Batch::create([
            'branch_id' => $this->branch->id, 'name' => '2025', 'shortcode' => '25', 'is_active' => true,
        ]);

        $form = Form::create([
            'branch_id' => $this->branch->id, 'name' => 'Form 1', 'short_name' => 'F1',
            'level' => 'first_cycle', 'school_level' => 'secondary',
            'education_system' => 'english', 'has_streams' => false,
            'display_order' => 1, 'is_active' => true,
        ]);
        $this->class = ClassSection::create([
            'branch_id' => $this->branch->id, 'form_id' => $form->id,
            'section' => 'A', 'name' => 'Form 1A', 'max_students' => 40, 'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function guardian(array $attributes = []): Guardian
    {
        return Guardian::create(array_merge([
            'branch_id' => $this->branch->id,
            'emergency_contact_name' => 'Mami Ngwa',
            'emergency_contact_phone' => '677000111',
        ], $attributes));
    }

    private function pupil(string $name, ?Guardian $guardian = null): Student
    {
        $student = Student::create([
            'branch_id' => $this->branch->id,
            'student_id' => 'TS25'.str_pad((string) (Student::count() + 1), 4, '0', STR_PAD_LEFT),
            'batch_id' => $this->batch->id,
            'guardian_id' => $guardian?->id,
            'first_name' => $name, 'last_name' => 'Pupil',
            'date_of_birth' => '2012-04-11', 'gender' => 'female',
            'status' => 'active', 'admission_date' => '2025-09-05',
        ]);

        StudentEnrollment::create([
            'branch_id' => $this->branch->id, 'student_id' => $student->id,
            'academic_session_id' => $this->session->id, 'term_id' => $this->term->id,
            'class_section_id' => $this->class->id,
            'residence_type' => 'day', 'enrollment_date' => '2025-09-05', 'status' => 'active',
        ]);

        return $student->fresh();
    }

    private function links(): GuardianLinkService
    {
        return app(GuardianLinkService::class);
    }

    // ------------------------------------------------------------ the display

    public function test_a_guardian_is_named_by_the_one_name_intake_insists_on(): void
    {
        // The registration form requires an emergency contact and nothing
        // else, so this is the only name most guardians have.
        $guardian = $this->guardian(['emergency_contact_name' => 'Vabi Michael']);

        $this->assertSame('Vabi Michael', $guardian->display_name);
        $this->assertSame('677000111', $guardian->primary_phone);
    }

    public function test_an_explicit_guardian_name_still_wins(): void
    {
        $guardian = $this->guardian([
            'guardian_name' => 'Ngwa Peter',
            'emergency_contact_name' => 'Vabi Michael',
        ]);

        $this->assertSame('Ngwa Peter', $guardian->display_name);
    }

    // ------------------------------------------------------------ matching

    public function test_a_parent_registering_a_second_child_is_recognised_by_phone(): void
    {
        $first = app(GuardianResolver::class)->resolve([
            'emergency_contact_name' => 'Mami Ngwa',
            'emergency_contact_phone' => '677 123 456',
        ]);

        // Same number, typed with a country code the second time.
        $second = app(GuardianResolver::class)->resolve([
            'emergency_contact_name' => 'Mami Ngwa',
            'emergency_contact_phone' => '+237677123456',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Guardian::count());
    }

    public function test_phones_are_compared_on_their_last_nine_digits(): void
    {
        $this->assertSame('677123456', GuardianResolver::normalisePhone('+237 677-123-456'));
        $this->assertSame('677123456', GuardianResolver::normalisePhone('00237677123456'));
        $this->assertSame('677123456', GuardianResolver::normalisePhone('677123456'));
        // Too short to be a subscriber number: only ever matches itself.
        $this->assertSame('1234', GuardianResolver::normalisePhone('1234'));
        $this->assertNull(GuardianResolver::normalisePhone(''));
    }

    public function test_different_parents_are_not_joined(): void
    {
        app(GuardianResolver::class)->resolve([
            'emergency_contact_name' => 'Mami Ngwa', 'emergency_contact_phone' => '677123456',
        ]);
        app(GuardianResolver::class)->resolve([
            'emergency_contact_name' => 'Papa Tabi', 'emergency_contact_phone' => '699888777',
        ]);

        $this->assertSame(2, Guardian::count());
    }

    // ------------------------------------------------------------ linking

    public function test_registering_a_pupil_links_them_to_their_guardian(): void
    {
        $guardian = $this->guardian();
        $student = $this->pupil('Bih', $guardian);

        $this->assertTrue($guardian->students()->where('students.id', $student->id)->exists());
        $this->assertTrue($this->links()->isPrimary($guardian, $student));
    }

    public function test_a_second_parent_can_be_linked_to_the_same_child(): void
    {
        $father = $this->guardian(['guardian_name' => 'Ngwa Peter']);
        $mother = $this->guardian(['guardian_name' => 'Mami Ngwa', 'emergency_contact_phone' => '699000222']);
        $student = $this->pupil('Bih', $father);

        $this->links()->link($mother, $student, 'Mother');

        $this->assertSame(2, $student->guardians()->count());
        // The first link stays the main contact unless someone says otherwise.
        $this->assertTrue($this->links()->isPrimary($father, $student));
        $this->assertSame($father->id, $student->fresh()->guardian_id);
    }

    public function test_making_a_parent_the_main_contact_demotes_the_other_and_moves_the_column(): void
    {
        $father = $this->guardian(['guardian_name' => 'Ngwa Peter']);
        $mother = $this->guardian(['guardian_name' => 'Mami Ngwa', 'emergency_contact_phone' => '699000222']);
        $student = $this->pupil('Bih', $father);

        $this->links()->link($mother, $student, 'Mother');
        $this->links()->makePrimary($mother, $student);

        $this->assertTrue($this->links()->isPrimary($mother, $student));
        $this->assertFalse($this->links()->isPrimary($father, $student));
        // students.guardian_id follows, so everything reading it stays right.
        $this->assertSame($mother->id, $student->fresh()->guardian_id);
    }

    public function test_removing_the_main_contact_hands_it_to_whoever_is_left(): void
    {
        $father = $this->guardian(['guardian_name' => 'Ngwa Peter']);
        $mother = $this->guardian(['guardian_name' => 'Mami Ngwa', 'emergency_contact_phone' => '699000222']);
        $student = $this->pupil('Bih', $father);
        $this->links()->link($mother, $student, 'Mother');

        $this->assertTrue($this->links()->unlink($father, $student));

        $this->assertTrue($this->links()->isPrimary($mother, $student));
        $this->assertSame($mother->id, $student->fresh()->guardian_id);
    }

    public function test_the_last_parent_cannot_be_removed(): void
    {
        $guardian = $this->guardian();
        $student = $this->pupil('Bih', $guardian);

        // A pupil with nobody attached has nobody to contact about them.
        $this->assertFalse($this->links()->unlink($guardian, $student));
        $this->assertSame(1, $student->guardians()->count());
    }

    public function test_the_main_contact_always_keeps_portal_access(): void
    {
        $guardian = $this->guardian();
        $student = $this->pupil('Bih', $guardian);

        $this->assertFalse($this->links()->setPortalAccess($guardian, $student, false));
        $this->assertTrue((bool) $student->guardians()->first()->pivot->can_access_portal);
    }

    public function test_a_sponsor_can_be_linked_without_seeing_the_child(): void
    {
        $parent = $this->guardian(['guardian_name' => 'Mami Ngwa']);
        $sponsor = $this->guardian(['guardian_name' => 'Uncle Tabi', 'emergency_contact_phone' => '699000333']);
        $student = $this->pupil('Bih', $parent);

        $this->links()->link($sponsor, $student, 'Sponsor', canAccessPortal: false);

        $this->assertSame(2, $student->guardians()->count());
        $this->assertSame(0, $sponsor->portalStudents()->count());
        $this->assertSame(1, $parent->portalStudents()->count());
    }

    // ------------------------------------------------------------ the portal

    public function test_a_parent_sees_every_child_linked_to_them(): void
    {
        $father = $this->guardian(['guardian_name' => 'Ngwa Peter', 'login_email' => 'dad@example.test']);
        $mother = $this->guardian(['guardian_name' => 'Mami Ngwa', 'emergency_contact_phone' => '699000222']);

        $ada = $this->pupil('Ada', $father);
        $bih = $this->pupil('Bih', $mother);

        // The father is not the main contact for Bih, but is still her father.
        $this->links()->link($father, $bih, 'Father');

        $this->actingAs($father, 'guardians');

        $this->assertSame(['Ada', 'Bih'], ParentContext::children()->pluck('first_name')->all());
    }

    public function test_a_parent_cannot_reach_a_child_they_are_not_linked_to(): void
    {
        $guardian = $this->guardian(['guardian_name' => 'Mami Ngwa']);
        $other = $this->guardian(['guardian_name' => 'Someone Else', 'emergency_contact_phone' => '699000444']);

        $this->pupil('Ada', $guardian);
        $notTheirs = $this->pupil('Bih', $other);

        $this->actingAs($guardian, 'guardians');

        $this->get(route('parent.student.show', $notTheirs))->assertForbidden();
    }

    // ------------------------------------------------------------ the screens

    public function test_an_admin_links_and_unlinks_a_child(): void
    {
        $guardian = $this->guardian(['guardian_name' => 'Mami Ngwa']);
        $other = $this->guardian(['guardian_name' => 'Other', 'emergency_contact_phone' => '699000555']);
        $ada = $this->pupil('Ada', $guardian);
        $bih = $this->pupil('Bih', $other);

        $this->post(route('admin.parent-portal.students.attach', $guardian), [
            'student_id' => $bih->id,
            'relationship' => 'Aunt',
        ])->assertRedirect();

        $this->assertSame(2, $guardian->students()->count());
        $this->assertSame('Aunt', $guardian->students()->where('students.id', $bih->id)->first()->pivot->relationship);

        $this->delete(route('admin.parent-portal.students.detach', [$guardian, $bih]))->assertRedirect();

        $this->assertSame(1, $guardian->students()->count());
    }

    public function test_linking_the_same_child_twice_is_refused_politely(): void
    {
        $guardian = $this->guardian();
        $ada = $this->pupil('Ada', $guardian);

        $this->post(route('admin.parent-portal.students.attach', $guardian), ['student_id' => $ada->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, $guardian->students()->count());
    }

    public function test_the_list_can_be_searched_by_the_name_the_school_typed(): void
    {
        $this->guardian(['emergency_contact_name' => 'Vabi Michael']);
        $this->guardian(['emergency_contact_name' => 'Someone Else', 'emergency_contact_phone' => '699000666']);

        $response = $this->get(route('admin.parent-portal.index', ['q' => 'Vabi']))->assertSuccessful();

        $this->assertCount(1, $response->viewData('guardians'));
    }

    public function test_the_list_can_be_searched_by_a_child(): void
    {
        $guardian = $this->guardian(['emergency_contact_name' => 'Vabi Michael']);
        $this->pupil('Ada', $guardian);
        $this->guardian(['emergency_contact_name' => 'Someone Else', 'emergency_contact_phone' => '699000777']);

        $response = $this->get(route('admin.parent-portal.index', ['q' => 'Ada']))->assertSuccessful();

        $this->assertCount(1, $response->viewData('guardians'));
    }

    public function test_the_emergency_contact_can_finally_be_corrected(): void
    {
        $guardian = $this->guardian(['emergency_contact_name' => 'Typo']);

        $this->put(route('admin.parent-portal.update', $guardian), [
            'emergency_contact_name' => 'Vabi Michael',
            'emergency_contact_phone' => '677000111',
        ])->assertRedirect();

        $this->assertSame('Vabi Michael', $guardian->fresh()->emergency_contact_name);
    }

    // ------------------------------------------------------------ merging

    public function test_duplicates_are_grouped_by_phone(): void
    {
        $a = $this->guardian(['emergency_contact_name' => 'Vabi Michael', 'emergency_contact_phone' => '677 713 593']);
        $b = $this->guardian(['emergency_contact_name' => 'Vabi M.', 'emergency_contact_phone' => '+237677713593']);
        $this->guardian(['emergency_contact_name' => 'Unrelated', 'emergency_contact_phone' => '699111222']);

        $groups = app(GuardianMergeService::class)->candidates();

        $this->assertCount(1, $groups);
        $this->assertSame([$a->id, $b->id], $groups->first()['guardians']->pluck('id')->all());
    }

    public function test_merging_moves_the_children_and_keeps_the_fuller_record(): void
    {
        $keeper = $this->guardian(['guardian_name' => 'Vabi Michael', 'login_email' => 'vabi@example.test']);
        $duplicate = $this->guardian(['father_name' => 'Vabi Michael Snr', 'father_phone' => '699222333']);

        $ada = $this->pupil('Ada', $keeper);
        $bih = $this->pupil('Bih', $duplicate);

        $this->post(route('admin.parent-portal.merge'), [
            'keeper_id' => $keeper->id,
            'merge_ids' => [$keeper->id, $duplicate->id],
        ])->assertRedirect();

        $keeper->refresh();

        $this->assertNull(Guardian::find($duplicate->id));
        $this->assertSame(2, $keeper->students()->count());
        // Both children point at the surviving record.
        $this->assertSame($keeper->id, $bih->fresh()->guardian_id);
        // A detail the keeper was missing is taken over.
        $this->assertSame('Vabi Michael Snr', $keeper->father_name);
    }

    public function test_merging_never_overwrites_the_keepers_own_details(): void
    {
        $keeper = $this->guardian(['guardian_name' => 'The Right Name', 'guardian_phone' => '677000001']);
        $duplicate = $this->guardian(['guardian_name' => 'The Wrong Name', 'guardian_phone' => '677000001']);

        app(GuardianMergeService::class)->merge($keeper, [$duplicate->id]);

        $this->assertSame('The Right Name', $keeper->fresh()->guardian_name);
    }

    public function test_a_merge_keeps_siblings_from_being_linked_twice(): void
    {
        $keeper = $this->guardian(['guardian_name' => 'Vabi Michael']);
        $duplicate = $this->guardian(['guardian_name' => 'Vabi M.', 'emergency_contact_phone' => '699333444']);

        $shared = $this->pupil('Ada', $keeper);
        // The same child somehow linked to both records.
        $this->links()->link($duplicate, $shared, 'Father');

        app(GuardianMergeService::class)->merge($keeper, [$duplicate->id]);

        $this->assertSame(1, $keeper->fresh()->students()->count());
    }
}
