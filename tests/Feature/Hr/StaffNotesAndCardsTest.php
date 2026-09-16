<?php

namespace Tests\Feature\Hr;

use App\Models\Branch;
use App\Models\StaffNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staff notes and staff ID cards, which the reference system has and this one
 * did not. Students had printable ID cards; staff did not.
 */
class StaffNotesAndCardsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['code' => 'MAIN'], ['name' => 'Main', 'is_active' => true]);

        $this->admin = User::create([
            'first_name' => 'Ada', 'last_name' => 'Admin',
            'email' => 'admin@example.test', 'password' => 'password',
            'role' => 'super_admin', 'is_active' => true,
        ]);
        $this->admin->branches()->attach($this->branch->id, ['is_default' => true]);
        $this->actingAs($this->admin);

        $this->staff = User::create([
            'first_name' => 'Sam', 'last_name' => 'Staff', 'staff_id' => 'STF001',
            'email' => 'staff@example.test', 'password' => 'password',
            'role' => 'staff', 'is_active' => true, 'basic_salary' => 150000,
        ]);
        $this->staff->branches()->attach($this->branch->id, ['is_default' => true]);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('staff-notes');

        parent::tearDown();
    }

    // ------------------------------------------------------------ notes

    public function test_a_note_is_added_and_shown_on_the_staff_page(): void
    {
        $this->post(route('admin.staff.notes.store', $this->staff), [
            'title' => 'Commendation',
            'note' => 'Ran the science fair.',
        ])->assertRedirect(route('admin.staff.show', $this->staff));

        $note = StaffNote::firstOrFail();
        $this->assertSame($this->admin->id, $note->created_by);

        $this->get(route('admin.staff.show', $this->staff))
            ->assertSuccessful()
            ->assertSee('Commendation')
            ->assertSee('Ran the science fair.');
    }

    public function test_an_attachment_is_kept_private(): void
    {
        $this->post(route('admin.staff.notes.store', $this->staff), [
            'title' => 'Warning letter',
            'note' => 'Signed copy attached.',
            'attachment' => UploadedFile::fake()->create('letter.pdf', 20, 'application/pdf'),
        ]);

        $note = StaffNote::firstOrFail();

        // HR records go to the private disk, never the public web root.
        $this->assertTrue(Storage::disk('local')->exists($note->attachment));
        $this->assertFalse(Storage::disk('public')->exists($note->attachment));

        $this->get(private_file_url('staff-note', $note, 'attachment'))->assertSuccessful();
    }

    public function test_deleting_a_note_removes_its_attachment(): void
    {
        $this->post(route('admin.staff.notes.store', $this->staff), [
            'title' => 'Temp', 'note' => 'x',
            'attachment' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
        ]);
        $note = StaffNote::firstOrFail();

        $this->delete(route('admin.staff.notes.destroy', $note))->assertRedirect();

        $this->assertSame(0, StaffNote::count());
        $this->assertFalse(Storage::disk('local')->exists($note->attachment));
    }

    public function test_adding_a_note_needs_the_permission(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.staff.notes.store', $this->staff), ['title' => 'x', 'note' => 'y'])
            ->assertForbidden();

        $this->assertSame(0, StaffNote::count());
    }

    // ------------------------------------------------------------ ID cards

    public function test_cards_print_and_each_gets_a_verification_token(): void
    {
        $this->post(route('admin.staff.id-cards.print'), ['staff_ids' => [$this->staff->id]])
            ->assertSuccessful()
            ->assertSee('STF001');

        $token = $this->staff->fresh()->id_card_token;
        $this->assertSame(32, strlen($token));

        // Printing again keeps the same token, so earlier cards still verify.
        $this->post(route('admin.staff.id-cards.print'), ['staff_ids' => [$this->staff->id]]);
        $this->assertSame($token, $this->staff->fresh()->id_card_token);
    }

    public function test_a_card_verifies_publicly_without_exposing_contact_details(): void
    {
        $this->post(route('admin.staff.id-cards.print'), ['staff_ids' => [$this->staff->id]]);
        $token = $this->staff->fresh()->id_card_token;

        auth()->logout();

        $this->get(route('staff-card.verify', $token))
            ->assertSuccessful()
            ->assertSee('Sam Staff')
            ->assertSee(__('This is a current member of staff.'))
            ->assertDontSee('staff@example.test');
    }

    public function test_a_departed_staff_members_card_is_reported_invalid(): void
    {
        $this->post(route('admin.staff.id-cards.print'), ['staff_ids' => [$this->staff->id]]);
        $this->staff->update(['is_active' => false]);

        auth()->logout();

        $this->get(route('staff-card.verify', $this->staff->fresh()->id_card_token))
            ->assertSuccessful()
            ->assertSee(__('This card is no longer valid.'));
    }

    public function test_an_unknown_token_is_not_found_and_ids_cannot_be_walked(): void
    {
        auth()->logout();

        $this->get(route('staff-card.verify', str_repeat('a', 32)))->assertNotFound();
        // The staff ID itself is not a way in.
        $this->get(route('staff-card.verify', 'STF001'))->assertNotFound();
    }

    public function test_the_staff_list_offers_bulk_printing(): void
    {
        $this->get(route('admin.staff.index'))
            ->assertSuccessful()
            ->assertSee(route('admin.staff.id-cards.print'), false);
    }
}
