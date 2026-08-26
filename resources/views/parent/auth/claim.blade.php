@php
    use App\Models\SchoolSetting;
    $school = SchoolSetting::current();
    $schoolName = $school->school_name ?? 'EduTrust';
    $logo = ($school && $school->logo) ? asset('storage/' . $school->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Activate Account') }} — {{ $schoolName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
               background: linear-gradient(135deg, #0f172a 0%, #134e4a 100%); color: #1e293b;
               min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-input:focus { border-color: #14b8a6 !important; box-shadow: 0 0 0 3px rgba(20,184,166,0.12) !important; }
    </style>
</head>
<body>
    <div style="width: 100%; max-width: 440px;">
        <div style="text-align: center; margin-bottom: 24px;">
            @if($logo)
                <img src="{{ $logo }}" alt="logo" style="width: 60px; height: 60px; border-radius: 13px; object-fit: cover; background:#fff; margin-bottom: 12px;">
            @else
                <div style="width: 60px; height: 60px; border-radius: 13px; background: #14b8a6; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 800; color: #fff; margin-bottom: 12px;">{{ mb_substr($schoolName, 0, 1) }}</div>
            @endif
            <h1 style="font-size: 1.2rem; font-weight: 700; color: #fff;">{{ $schoolName }}</h1>
        </div>

        <div style="background: #fff; border-radius: 14px; box-shadow: 0 10px 40px rgba(0,0,0,0.25); padding: 30px;">
            @if($invalid)
                <div style="text-align: center;">
                    <div style="width: 54px; height: 54px; border-radius: 50%; background: #fef2f2; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                        <svg width="28" height="28" style="color:#ef4444;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <h2 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 8px;">{{ __('Invitation Invalid or Expired') }}</h2>
                    <p style="font-size: 0.84rem; color: #64748b; line-height: 1.5;">{{ __('This activation link is no longer valid. Please contact the school office to request a new invitation.') }}</p>
                    <a href="{{ route('parent.login') }}" style="display: inline-block; margin-top: 18px; color: #14b8a6; font-weight: 600; font-size: 0.85rem;">{{ __('Go to Login') }}</a>
                </div>
            @else
                <h2 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;">{{ __('Activate Your Account') }}</h2>
                <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 20px;">
                    {{ __('Hello :name, set a password to access your children’s records.', ['name' => $guardian->display_name]) }}
                </p>

                @if($errors->any())
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 11px 13px; margin-bottom: 18px;">
                    @foreach($errors->all() as $error)
                    <div style="font-size: 0.8rem; color: #dc2626;">• {{ $error }}</div>
                    @endforeach
                </div>
                @endif

                <form method="POST" action="{{ route('parent.claim.submit') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Your Email Address') }}</label>
                        <input type="email" name="email" value="{{ old('email', $guardian->primary_email) }}" required class="login-input"
                               style="width: 100%; padding: 11px 13px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.88rem; outline: none; transition: all 0.15s;">
                        <p style="font-size: 0.7rem; color: #94a3b8; margin-top: 4px;">{{ __('You will use this to sign in.') }}</p>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Password') }}</label>
                        <input type="password" name="password" required class="login-input"
                               style="width: 100%; padding: 11px 13px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.88rem; outline: none; transition: all 0.15s;"
                               placeholder="{{ __('At least 8 characters') }}">
                    </div>
                    <div style="margin-bottom: 22px;">
                        <label style="display: block; font-size: 0.78rem; font-weight: 600; color: #374151; margin-bottom: 6px;">{{ __('Confirm Password') }}</label>
                        <input type="password" name="password_confirmation" required class="login-input"
                               style="width: 100%; padding: 11px 13px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.88rem; outline: none; transition: all 0.15s;">
                    </div>
                    <button type="submit"
                            style="width: 100%; padding: 12px; background: #14b8a6; color: #fff; border: none; border-radius: 9px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background 0.2s;"
                            onmouseover="this.style.background='#0d9488'" onmouseout="this.style.background='#14b8a6'">
                        {{ __('Activate & Continue') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</body>
</html>
