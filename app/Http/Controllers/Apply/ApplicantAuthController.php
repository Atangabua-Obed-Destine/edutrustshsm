<?php

namespace App\Http\Controllers\Apply;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ApplicantAuthController extends Controller
{
    protected function guard()
    {
        return Auth::guard('applicant');
    }

    // ── Registration ───────────────────────

    public function showRegister()
    {
        if ($this->guard()->check()) {
            return redirect()->route('apply.dashboard');
        }
        return view('apply.auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:150', 'unique:applicants,email'],
            'phone'      => ['nullable', 'string', 'max:20'],
            'password'   => ['required', 'confirmed', Password::min(8)],
        ]);

        $applicant = Applicant::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'] ?? null,
            'password'   => $validated['password'],
        ]);

        $this->guard()->login($applicant);

        return redirect()->route('apply.dashboard')
            ->with('success', __('Account created successfully! You can now submit an application.'));
    }

    // ── Login ──────────────────────────────

    public function showLogin()
    {
        if ($this->guard()->check()) {
            return redirect()->route('apply.dashboard');
        }
        return view('apply.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if ($this->guard()->attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('apply.dashboard'));
        }

        return back()->withErrors([
            'email' => __('Invalid email or password.'),
        ])->onlyInput('email');
    }

    // ── Logout ─────────────────────────────

    public function logout(Request $request)
    {
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('apply.login');
    }
}
