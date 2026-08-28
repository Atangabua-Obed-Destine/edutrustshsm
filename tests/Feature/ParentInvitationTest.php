<?php

namespace Tests\Feature;

use App\Mail\ParentInvitation;
use App\Models\Branch;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The invite flow generated a link that an admin had to read off the screen and
 * relay by hand — ParentPortalController carried a TODO where the email should
 * have been.
 */
class ParentInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;

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
    }

    private function guardian(?string $email = 'parent@example.test'): Guardian
    {
        // branch_id must be explicit: the row is created outside a request, where
        // BranchContext is inactive and the trait cannot stamp it — a NULL branch
        // would make the guardian invisible to route-model binding.
        return Guardian::create([
            'branch_id' => $this->branch->id,
            'guardian_name' => 'Pat Parent',
            'guardian_email' => $email,
            'guardian_phone' => '677000000',
            'emergency_contact_name' => 'Pat Parent',
            'emergency_contact_phone' => '677000000',
        ]);
    }

    public function test_inviting_a_guardian_sends_them_the_link(): void
    {
        Mail::fake();
        $guardian = $this->guardian();

        $this->actingAs($this->admin)
            ->post(route('admin.parent-portal.invite', $guardian))
            ->assertRedirect();

        Mail::assertQueued(ParentInvitation::class, function ($mail) use ($guardian) {
            return $mail->hasTo($guardian->primary_email)
                && str_contains($mail->link, $guardian->fresh()->invite_token);
        });
    }

    public function test_the_invitation_sets_a_token_and_expiry(): void
    {
        Mail::fake();
        $guardian = $this->guardian();

        $this->actingAs($this->admin)->post(route('admin.parent-portal.invite', $guardian));

        $guardian->refresh();

        $this->assertTrue($guardian->portal_access);
        $this->assertNotNull($guardian->invite_token);
        $this->assertTrue($guardian->invite_expires_at->isFuture());
    }

    public function test_a_guardian_with_no_email_is_refused(): void
    {
        Mail::fake();
        $guardian = $this->guardian(null);

        $this->actingAs($this->admin)
            ->post(route('admin.parent-portal.invite', $guardian))
            ->assertSessionHas('error');

        Mail::assertNothingQueued();
        $this->assertNull($guardian->fresh()->invite_token);
    }
}
