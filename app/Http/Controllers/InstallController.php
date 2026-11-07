<?php

namespace App\Http\Controllers;

use App\Services\SchoolInstaller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstallController extends Controller
{
    public function show(SchoolInstaller $installer, ?string $token = null)
    {
        if ($installer->isInstalled()) {
            return redirect()->route('login');
        }

        return $installer->tokenIsValid($token)
            ? view('install.setup', ['token' => $token])
            : view('install.locked');
    }

    public function store(Request $request, SchoolInstaller $installer, string $token)
    {
        if ($installer->isInstalled()) {
            return redirect()->route('login');
        }

        abort_unless($installer->tokenIsValid($token), 403);

        $validated = $request->validate([
            'school_name' => ['required', 'string', 'max:255'],
            'school_short_name' => ['nullable', 'string', 'max:255'],
            'school_code' => ['required', 'alpha_num:ascii', 'max:10'],
            'school_level_mode' => ['required', 'in:nursery_primary,secondary,both'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $installer->finish($validated, $validated);

        Auth::login($user);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        return redirect()->route($user->homeRoute())
            ->with('success', __('EduTrust is ready. The list below shows what to set up next.'));
    }
}
