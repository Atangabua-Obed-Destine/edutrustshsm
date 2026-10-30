<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Mail\ParentInvitation;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\GuardianLinkService;
use App\Services\GuardianMergeService;
use App\Models\ParentPaymentSubmission;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class ParentPortalController extends Controller implements HasMiddleware
{
    use AuthorizesModule;

    protected static string $access = 'parent-portal';

    /** @return array<int, Middleware> */
    protected static function extraMiddleware(): array
    {
        return [
            static::can('parent-portal.view', ['index', 'show']),
            static::can('parent-portal.invite', ['invite']),
            static::can('parent-portal.password', ['resetPassword', 'setPassword']),
            static::can('parent-portal.revoke', ['disable']),
            static::can('parent-portal.edit', ['update']),
            // Moving a child between parents, and folding two parent records
            // into one, are not ordinary edits.
            static::can('parent-portal.link', ['attachStudent', 'detachStudent', 'makePrimary', 'updateLink']),
            static::can('parent-portal.merge', ['duplicates', 'merge']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $guardians = Guardian::query()
            ->withCount('students')
            // The children themselves, so the list can name them rather than
            // showing a number nobody can act on.
            ->with(['students' => fn ($q) => $q->orderBy('first_name')])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    // Every name and phone column, not only the ones the
                    // intake form happens to leave blank. A parent searched
                    // for by the name the school typed used to find nothing.
                    foreach ([
                        'guardian_name', 'father_name', 'mother_name', 'emergency_contact_name',
                        'guardian_email', 'father_email', 'mother_email', 'login_email',
                        'guardian_phone', 'father_phone', 'mother_phone', 'emergency_contact_phone',
                    ] as $column) {
                        $sub->orWhere($column, 'like', "%{$search}%");
                    }

                    // And by the name of any child they are linked to.
                    // Qualified: student_id is the matricule on students and
                    // the foreign key on the pivot the relation joins through.
                    $sub->orWhereHas('students', fn ($child) => $child
                        ->where('students.first_name', 'like', "%{$search}%")
                        ->orWhere('students.last_name', 'like', "%{$search}%")
                        ->orWhere('students.student_id', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.parent-portal.index', compact('guardians', 'search') + [
            'duplicateGroups' => app(\App\Services\GuardianMergeService::class)->candidates()->count(),
        ]);
    }

    /**
     * Generate (or regenerate) a one-time invitation link for a guardian and
     * email it to them. The link is still surfaced to the admin so it can be
     * relayed by hand if the parent has no working mailbox.
     */
    public function invite(Request $request, Guardian $guardian)
    {
        if (! $guardian->primary_email) {
            return back()->with('error', __('This guardian has no email on file. Add a contact email first.'));
        }

        $token = Str::random(48);

        $guardian->forceFill([
            'portal_access'     => true,
            'invite_token'      => $token,
            'invite_expires_at' => now()->addHours(48),
        ])->save();

        $link = route('parent.claim', ['token' => $token]);

        try {
            Mail::to($guardian->primary_email)->send(ParentInvitation::for($guardian, $link));
        } catch (\Throwable $e) {
            // Never lose the invitation because mail is misconfigured — the link
            // is valid either way, so hand it back for manual delivery.
            Log::warning('Parent invitation email failed', [
                'guardian_id' => $guardian->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('The invitation was created but the email could not be sent. Share this link with the parent:') . ' ' . $link);
        }

        return back()->with('success', __('Invitation sent to :email.', ['email' => $guardian->primary_email]) . ' ' . __('Link:') . ' ' . $link);
    }

    /** Revoke portal access (parent can no longer log in). */
    public function disable(Guardian $guardian)
    {
        $guardian->forceFill([
            'portal_access'     => false,
            'invite_token'      => null,
            'invite_expires_at' => null,
        ])->save();

        return back()->with('success', __('Portal access revoked for this guardian.'));
    }

    /** Clear the password so the parent must re-activate via a fresh invite. */
    public function resetPassword(Guardian $guardian)
    {
        $guardian->forceFill([
            'password'      => null,
            'portal_access' => false,
        ])->save();

        return back()->with('success', __('Password reset. Send a new invitation for the parent to set a new one.'));
    }

    /** Full parent detail: contacts, all children, portal status, activity. */
    public function show(Guardian $guardian)
    {
        $guardian->load(['students.currentEnrollment.classSection.form']);

        $submissions = ParentPaymentSubmission::where('guardian_id', $guardian->id)
            ->with(['enrollment.student', 'studentFee.feeCategory'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Pupils this parent is not already linked to, for the attach picker.
        $linkable = Student::whereNotIn('id', $guardian->students->pluck('id'))
            ->where('status', 'active')
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'student_id']);

        return view('admin.parent-portal.show', compact('guardian', 'submissions', 'linkable'));
    }

    /** Edit the guardian's contact details. */
    public function update(Request $request, Guardian $guardian)
    {
        $validated = $request->validate([
            'guardian_name'         => ['nullable', 'string', 'max:150'],
            'guardian_relationship' => ['nullable', 'string', 'max:60'],
            'guardian_phone'        => ['nullable', 'string', 'max:30'],
            'guardian_email'        => ['nullable', 'email', 'max:150'],
            'father_name'           => ['nullable', 'string', 'max:150'],
            'father_phone'          => ['nullable', 'string', 'max:30'],
            'father_email'          => ['nullable', 'email', 'max:150'],
            'mother_name'           => ['nullable', 'string', 'max:150'],
            'mother_phone'          => ['nullable', 'string', 'max:30'],
            'mother_email'          => ['nullable', 'email', 'max:150'],
            // The intake form insists on these, so for most guardians they
            // are the only name and number on file. Not being able to correct
            // them meant not being able to correct anything.
            'emergency_contact_name'         => ['nullable', 'string', 'max:150'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone'        => ['nullable', 'string', 'max:30'],
        ]);

        $guardian->update($validated);

        return back()->with('success', __('Parent details updated.'));
    }

    /**
     * Directly set (or change) the parent's portal password — useful for parents
     * without email who cannot use the invite link. Activates portal access.
     */
    public function setPassword(Request $request, Guardian $guardian)
    {
        $validated = $request->validate([
            'login_email' => [
                'required', 'email', 'max:150',
                Rule::unique('guardians', 'login_email')->ignore($guardian->id),
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $guardian->forceFill([
            'login_email'       => $validated['login_email'],
            'password'          => Hash::make($validated['password']),
            'portal_access'     => true,
            'email_verified_at' => now(),
            'invite_token'      => null,
            'invite_expires_at' => null,
        ])->save();

        return back()->with('success', __('Portal password set. The parent can now log in with the email and password you provided.'));
    }

    // ── Who belongs to whom ─────────────────────────────────────────────────

    /** Link a child to this parent. */
    public function attachStudent(Request $request, Guardian $guardian, GuardianLinkService $links)
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'relationship' => ['nullable', 'string', 'max:60'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $student = Student::findOrFail($validated['student_id']);

        if ($guardian->students()->where('students.id', $student->id)->exists()) {
            return back()->with('error', __(':name is already linked to this parent.', [
                'name' => $student->first_name.' '.$student->last_name,
            ]));
        }

        $links->link(
            $guardian,
            $student,
            relationship: $validated['relationship'] ?? null,
            primary: $request->boolean('is_primary') ?: null
        );

        return back()->with('success', __(':name is now linked to this parent.', [
            'name' => $student->first_name.' '.$student->last_name,
        ]));
    }

    /** Remove a link, which is not the same as deleting either record. */
    public function detachStudent(Guardian $guardian, Student $student, GuardianLinkService $links)
    {
        if (! $links->unlink($guardian, $student)) {
            return back()->with('error', __('This is the only adult linked to :name. Link someone else before removing this one.', [
                'name' => $student->first_name.' '.$student->last_name,
            ]));
        }

        return back()->with('success', __(':name is no longer linked to this parent.', [
            'name' => $student->first_name.' '.$student->last_name,
        ]));
    }

    /** Make this parent the school's first point of contact for a child. */
    public function makePrimary(Guardian $guardian, Student $student, GuardianLinkService $links)
    {
        $links->makePrimary($guardian, $student);

        return back()->with('success', __('This parent is now the main contact for :name.', [
            'name' => $student->first_name.' '.$student->last_name,
        ]));
    }

    /** Change what this adult is to a child, or whether they may see them. */
    public function updateLink(Request $request, Guardian $guardian, Student $student, GuardianLinkService $links)
    {
        $validated = $request->validate([
            'relationship' => ['nullable', 'string', 'max:60'],
            'can_access_portal' => ['nullable', 'boolean'],
        ]);

        $links->setRelationship($guardian, $student, $validated['relationship'] ?? null);

        if (! $links->setPortalAccess($guardian, $student, $request->boolean('can_access_portal'))) {
            return back()->with('error', __('The main contact for a child always keeps portal access.'));
        }

        return back()->with('success', __('Link updated.'));
    }

    // ── Duplicates ──────────────────────────────────────────────────────────

    /** Parents the system recorded more than once. */
    public function duplicates(GuardianMergeService $merges)
    {
        $groups = $merges->candidates();

        return view('admin.parent-portal.duplicates', [
            'groups' => $groups->map(fn (array $group) => $group + [
                'suggested' => $merges->suggestKeeper($group['guardians']),
            ]),
        ]);
    }

    /** Join a group of duplicates into one record, on confirmation. */
    public function merge(Request $request, GuardianMergeService $merges)
    {
        $validated = $request->validate([
            'keeper_id' => ['required', 'exists:guardians,id'],
            'merge_ids' => ['required', 'array', 'min:1'],
            'merge_ids.*' => ['integer', 'exists:guardians,id'],
        ]);

        $keeper = Guardian::findOrFail($validated['keeper_id']);
        $result = $merges->merge($keeper, $validated['merge_ids']);

        return redirect()->route('admin.parent-portal.duplicates')
            ->with('success', __('Merged into :name — :children child(ren) and :submissions payment(s) moved across.', [
                'name' => $keeper->display_name,
                'children' => $result['children'],
                'submissions' => $result['submissions'],
            ]));
    }
}
