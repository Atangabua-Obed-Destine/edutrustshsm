@php $__school = \App\Models\SchoolSetting::current(); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admissions')) - {{ $__school->school_name ?? 'EduTrustSchool' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Top Navigation --}}
    <nav style="background: #1e293b; color: #fff; padding: 0 24px; position: sticky; top: 0; z-index: 50; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">
        <div style="max-width: 1100px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 60px;">
            <a href="{{ route('apply.dashboard') }}" style="display: flex; align-items: center; gap: 10px;">
                <svg style="width: 28px; height: 28px; color: #38bdf8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                <div>
                    <div style="font-weight: 700; font-size: 0.95rem; line-height: 1.2;">{{ $__school->school_name ?? 'EduTrustSchool' }}</div>
                    <div style="font-size: 0.65rem; color: #94a3b8; letter-spacing: 0.05em;">{{ __('ONLINE ADMISSIONS PORTAL') }}</div>
                </div>
            </a>
            <div style="display: flex; align-items: center; gap: 16px;">
                @if(Auth::guard('applicant')->check())
                    <span style="font-size: 0.82rem; color: #cbd5e1;">{{ Auth::guard('applicant')->user()->full_name }}</span>
                    <form method="POST" action="{{ route('apply.logout') }}" style="display:inline;">
                        @csrf
                        <button type="submit" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 6px 14px; border-radius: 6px; font-size: 0.78rem; cursor: pointer;"
                                onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                            {{ __('Logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('apply.login') }}" style="font-size: 0.82rem; color: #cbd5e1; padding: 6px 14px;"
                       onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#cbd5e1'">{{ __('Login') }}</a>
                    <a href="{{ route('apply.register') }}" style="background: #0ea5e9; color: #fff; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 600;"
                       onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">{{ __('Register') }}</a>
                @endif
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main style="max-width: 1100px; margin: 0 auto; padding: 28px 24px;">
        @if(session('success'))
        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" style="color: #22c55e; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size: 0.85rem; color: #166534; font-weight: 500;">{{ session('success') }}</span>
            </div>
        </div>
        @endif

        @if(session('error'))
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" style="color: #ef4444; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size: 0.85rem; color: #b91c1c; font-weight: 500;">{{ session('error') }}</span>
            </div>
        </div>
        @endif

        @yield('content')
    </main>

    {{-- Footer --}}
    <footer style="text-align: center; padding: 24px; color: #94a3b8; font-size: 0.75rem; border-top: 1px solid #e2e8f0; margin-top: 40px;">
        &copy; {{ date('Y') }} {{ $__school->school_name ?? 'EduTrustSchool' }}. {{ __('All rights reserved.') }}
    </footer>

    @stack('scripts')
</body>
</html>

