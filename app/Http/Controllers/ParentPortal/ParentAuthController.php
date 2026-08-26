<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Support\ParentContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class ParentAuthController extends Controller
{
    protected function guard()
    {
        return Auth::guard('guardians');
    }

    // ── Login ──────────────────────────────

    public function showLogin()
    {
        if ($this->guard()->check()) {
            return redirect()->route('parent.dashboard');
        }

        return view('parent.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        // The login credential is `login_email`; map the posted `email` field to it.
        $attempt = [
            'login_email' => $credentials['email'],
            'password'    => $credentials['password'],
        ];

        // Only allow guardians who have completed the claim flow (portal_access + password).
        if ($this->guard()->attempt($attempt, $remember)) {
            $guardian = $this->guard()->user();

            if (! $guardian->canAccessPortal()) {
                $this->guard()->logout();

                return back()->withErrors([
                    'email' => __('Your portal access has not been activated. Please contact the school.'),
                ])->onlyInput('email');
            }

            $guardian->forceFill(['last_login_at' => now()])->saveQuietly();

            $request->session()->regenerate();

            return redirect()->intended(route('parent.dashboard'));
        }

        return back()->withErrors([
            'email' => __('Invalid email or password.'),
        ])->onlyInput('email');
    }

    // ── Claim (one-time activation via invite token) ──────────────────────────────

    public function showClaim(Request $request)
    {
        $token = $request->query('token');

        $guardian = $token
            ? Guardian::withoutBranchScope()->where('invite_token', $token)->first()
            : null;

        if (! $guardian || ! $guardian->inviteIsValid()) {
            return view('parent.auth.claim', [
                'guardian' => null,
                'token'    => $token,
                'invalid'  => true,
            ]);
        }

        return view('parent.auth.claim', [
            'guardian' => $guardian,
            'token'    => $token,
            'invalid'  => false,
        ]);
    }

    public function claim(Request $request)
    {
        $validated = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $guardian = Guardian::withoutBranchScope()
            ->where('invite_token', $validated['token'])
            ->first();

        if (! $guardian || ! $guardian->inviteIsValid()) {
            return back()->withErrors([
                'token' => __('This invitation link is invalid or has expired. Please contact the school.'),
            ]);
        }

        // Ensure the chosen login email is unique across guardians (scope-free check).
        $emailTaken = Guardian::withoutBranchScope()
            ->where('login_email', $validated['email'])
            ->where('id', '!=', $guardian->id)
            ->exists();

        if ($emailTaken) {
            return back()->withErrors([
                'email' => __('That email is already in use. Please use a different one.'),
            ])->onlyInput('email');
        }

        $guardian->forceFill([
            'login_email'       => $validated['email'],
            'password'          => $validated['password'], // hashed via cast
            'portal_access'     => true,
            'email_verified_at' => now(),
            'invite_token'      => null,
            'invite_expires_at' => null,
            'last_login_at'     => now(),
        ])->save();

        $this->guard()->login($guardian);
        $request->session()->regenerate();

        return redirect()->route('parent.dashboard')
            ->with('success', __('Welcome! Your parent account is now active.'));
    }

    // ── Logout ─────────────────────────────

    public function logout(Request $request)
    {
        $this->guard()->logout();
        $request->session()->forget(ParentContext::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('parent.login');
    }
}
