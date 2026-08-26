@php
    use App\Models\SchoolSetting;
    use App\Support\ParentContext;

    $school = SchoolSetting::current();
    $schoolName = $school->school_name ?? 'EduTrust';
    $schoolShort = $school->school_short_name ?? $schoolName;
    $logo = ($school && $school->logo) ? asset('storage/' . $school->logo) : null;
    $guardian = ParentContext::guardian();
    $children = $guardian ? ParentContext::children() : collect();
    $activeStudent = $guardian ? ParentContext::currentStudent() : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Parent Portal')) — {{ $schoolName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }
        a { text-decoration: none; color: inherit; }
        .pp-shell { display: flex; min-height: 100vh; }
        .pp-sidebar {
            width: 260px; background: #0f172a; color: #cbd5e1; flex-shrink: 0;
            display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 60;
            transform: translateX(0); transition: transform 0.25s ease;
        }
        .pp-main { flex: 1; margin-left: 260px; display: flex; flex-direction: column; min-width: 0; }
        .pp-nav-link {
            display: flex; align-items: center; gap: 11px; padding: 10px 18px; font-size: 0.85rem;
            color: #cbd5e1; border-left: 3px solid transparent; transition: all 0.15s;
        }
        .pp-nav-link:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .pp-nav-link.active { background: rgba(20,184,166,0.12); color: #5eead4; border-left-color: #14b8a6; }
        .pp-nav-link svg { width: 18px; height: 18px; flex-shrink: 0; }
        .pp-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 55; }
        @media (max-width: 900px) {
            .pp-sidebar { transform: translateX(-100%); }
            .pp-sidebar.open { transform: translateX(0); }
            .pp-main { margin-left: 0; }
            .pp-overlay.open { display: block; }
        }
        .pp-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
    </style>
    @stack('styles')
</head>
<body>
<div class="pp-shell" x-data="{ sidebarOpen: false }">

    {{-- Sidebar --}}
    <aside class="pp-sidebar" :class="{ 'open': sidebarOpen }">
        <div style="padding: 20px 18px; border-bottom: 1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:11px;">
            @if($logo)
                <img src="{{ $logo }}" alt="logo" style="width:38px; height:38px; border-radius:8px; object-fit:cover; background:#fff;">
            @else
                <div style="width:38px; height:38px; border-radius:8px; background:#14b8a6; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff;">{{ mb_substr($schoolShort, 0, 1) }}</div>
            @endif
            <div style="min-width:0;">
                <div style="font-weight:700; font-size:0.9rem; color:#fff; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $schoolShort }}</div>
                <div style="font-size:0.62rem; color:#64748b; letter-spacing:0.06em; text-transform:uppercase;">{{ __('Parent Portal') }}</div>
            </div>
        </div>

        @if($activeStudent)
        {{-- Active child indicator --}}
        <div style="padding: 14px 18px; border-bottom: 1px solid rgba(255,255,255,0.08);">
            <div style="font-size:0.62rem; color:#64748b; letter-spacing:0.06em; text-transform:uppercase; margin-bottom:5px;">{{ __('Viewing') }}</div>
            <div style="display:flex; align-items:center; gap:9px;">
                <div style="width:30px; height:30px; border-radius:50%; background:#14b8a6; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:700; color:#fff;">{{ mb_substr($activeStudent->first_name, 0, 1) }}{{ mb_substr($activeStudent->last_name, 0, 1) }}</div>
                <div style="min-width:0;">
                    <div style="font-size:0.8rem; color:#fff; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $activeStudent->first_name }} {{ $activeStudent->last_name }}</div>
                    <div style="font-size:0.66rem; color:#64748b;">{{ $activeStudent->student_id }}</div>
                </div>
            </div>
        </div>
        @endif

        <nav style="flex:1; overflow-y:auto; padding: 10px 0;">
            @php $sid = $activeStudent?->id; @endphp
            <a href="{{ route('parent.dashboard') }}" class="pp-nav-link {{ request()->routeIs('parent.dashboard') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                {{ __('My Children') }}
            </a>

            @if($sid)
            <div style="padding: 14px 18px 6px; font-size:0.62rem; color:#475569; letter-spacing:0.08em; text-transform:uppercase;">{{ __('Student') }}</div>
            <a href="{{ route('parent.student.show', $sid) }}" class="pp-nav-link {{ request()->routeIs('parent.student.show') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                {{ __('Overview') }}
            </a>
            <a href="{{ route('parent.fees.index', $sid) }}" class="pp-nav-link {{ request()->routeIs('parent.fees.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                {{ __('Fees') }}
            </a>
            <a href="{{ route('parent.report-cards.index', $sid) }}" class="pp-nav-link {{ request()->routeIs('parent.report-cards.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('Report Cards') }}
            </a>
            <a href="{{ route('parent.subjects.index', $sid) }}" class="pp-nav-link {{ request()->routeIs('parent.subjects.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                {{ __('Subjects & Teachers') }}
            </a>
            <a href="{{ route('parent.timetable.index', $sid) }}" class="pp-nav-link {{ request()->routeIs('parent.timetable.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                {{ __('Timetable') }}
            </a>
            <a href="{{ route('parent.attendance.index', $sid) }}" class="pp-nav-link {{ request()->routeIs('parent.attendance.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                {{ __('Attendance') }}
            </a>
            @endif

            @if(\Illuminate\Support\Facades\Route::has('parent.payments.index') || \Illuminate\Support\Facades\Route::has('parent.pta.index'))
            <div style="padding: 14px 18px 6px; font-size:0.62rem; color:#475569; letter-spacing:0.08em; text-transform:uppercase;">{{ __('Payments & PTA') }}</div>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('parent.payments.index'))
            <a href="{{ route('parent.payments.index') }}" class="pp-nav-link {{ request()->routeIs('parent.payments.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                {{ __('Submit Payment') }}
            </a>
            @endif
            @if(\Illuminate\Support\Facades\Route::has('parent.pta.index'))
            <a href="{{ route('parent.pta.index') }}" class="pp-nav-link {{ request()->routeIs('parent.pta.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                {{ __('PTA') }}
            </a>
            @endif
        </nav>

        <div style="padding: 14px 18px; border-top: 1px solid rgba(255,255,255,0.08); font-size:0.62rem; color:#475569; text-align:center;">
            {{ __('Powered by') }} <span style="color:#14b8a6; font-weight:700;">I-NNOVA</span>
        </div>
    </aside>

    {{-- Mobile overlay --}}
    <div class="pp-overlay" :class="{ 'open': sidebarOpen }" @click="sidebarOpen = false"></div>

    {{-- Main --}}
    <div class="pp-main">
        {{-- Topbar --}}
        <header style="background:#fff; border-bottom:1px solid #e2e8f0; padding: 0 24px; height:62px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50;">
            <div style="display:flex; align-items:center; gap:14px;">
                <button @click="sidebarOpen = !sidebarOpen" style="display:none; background:none; border:none; cursor:pointer; color:#334155;" class="pp-burger">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 style="font-size:1.05rem; font-weight:700; color:#0f172a;">@yield('heading', __('Parent Portal'))</h1>
            </div>
            <div style="display:flex; align-items:center; gap:14px;">
                @if($guardian)
                <div style="text-align:right; display:none;" class="pp-userinfo">
                    <div style="font-size:0.82rem; font-weight:600; color:#1e293b;">{{ $guardian->display_name }}</div>
                    <div style="font-size:0.68rem; color:#64748b;">{{ $guardian->primary_email }}</div>
                </div>
                <form method="POST" action="{{ route('parent.logout') }}">
                    @csrf
                    <button type="submit" style="background:#f1f5f9; border:1px solid #e2e8f0; color:#475569; padding:7px 14px; border-radius:7px; font-size:0.78rem; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;"
                            onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        {{ __('Logout') }}
                    </button>
                </form>
                @endif
            </div>
        </header>

        <main style="flex:1; padding: 24px;">
            @if(session('success'))
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:9px; padding:13px 16px; margin-bottom:20px; display:flex; align-items:center; gap:9px;">
                <svg width="18" height="18" style="color:#22c55e; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size:0.85rem; color:#166534; font-weight:500;">{{ session('success') }}</span>
            </div>
            @endif
            @if(session('error'))
            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:9px; padding:13px 16px; margin-bottom:20px; display:flex; align-items:center; gap:9px;">
                <svg width="18" height="18" style="color:#ef4444; flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span style="font-size:0.85rem; color:#b91c1c; font-weight:500;">{{ session('error') }}</span>
            </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<style>
    @media (max-width: 900px) { .pp-burger { display:block !important; } }
    @media (min-width: 640px) { .pp-userinfo { display:block !important; } }
</style>
@stack('scripts')
</body>
</html>
