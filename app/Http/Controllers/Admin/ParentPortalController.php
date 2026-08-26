<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\ParentPaymentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class ParentPortalController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $guardians = Guardian::query()
            ->withCount('students')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('guardian_name', 'like', "%{$search}%")
                        ->orWhere('father_name', 'like', "%{$search}%")
                        ->orWhere('mother_name', 'like', "%{$search}%")
                        ->orWhere('guardian_email', 'like', "%{$search}%")
                        ->orWhere('father_email', 'like', "%{$search}%")
                        ->orWhere('mother_email', 'like', "%{$search}%")
                        ->orWhere('login_email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.parent-portal.index', compact('guardians', 'search'));
    }

    /**
     * Generate (or regenerate) a one-time invitation link for a guardian.
     * The link is shown to the admin to copy/share (email/SMS integration later).
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

        // TODO (later): dispatch email/SMS notification with $link.
        return back()->with('success', __('Invitation link generated. Share it with the parent:') . ' ' . $link);
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

        return view('admin.parent-portal.show', compact('guardian', 'submissions'));
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
}
