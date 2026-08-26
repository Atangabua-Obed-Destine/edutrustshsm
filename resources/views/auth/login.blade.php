@php
    $setting = \App\Models\SchoolSetting::current();
    $schoolName = $setting->school_name ?? 'EduTrust School';
    $schoolShort = $setting->school_short_name ?? $schoolName;
    $logo = ($setting && $setting->logo) ? \Illuminate\Support\Facades\Storage::url($setting->logo) : null;

    $studentCount = \App\Models\Student::count();
    $staffCount = \App\Models\User::query()->whereNotIn('role', ['super_admin', 'admin', 'parent', 'student'])->count();
    $deptCount = \App\Models\Department::count();

    $emailPlaceholder = 'staff@edutrust.i-nnovacmr.com';

    $features = [
        ['title' => __('Academic Management'), 'desc' => __('Sessions, forms, students, exams and report cards'),
         'icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
        ['title' => __('Finance & Accounting'), 'desc' => __('Fees, payroll, budgets and the OHADA ledger'),
         'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
        ['title' => __('Analytics & Reports'), 'desc' => __('Dashboards, statements and performance metrics'),
         'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ['title' => __('Admissions & Communication'), 'desc' => __('Applications, enrolment, staff and guardians'),
         'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Login') }} - {{ $schoolShort }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="min-h-screen flex flex-col lg:flex-row">

        {{-- ── Left: branded panel ── --}}
        <aside class="hidden lg:flex lg:w-1/2 xl:w-7/12 flex-col justify-between p-10 xl:p-14 text-white relative overflow-hidden"
               style="background:linear-gradient(160deg,#0b2a6b 0%,#13439e 45%,#1d4ed8 100%);">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full opacity-10" style="background:#ffffff;"></div>
            <div class="absolute -bottom-32 -left-20 w-96 h-96 rounded-full opacity-5" style="background:#ffffff;"></div>

            <div class="relative">
                {{-- Logo --}}
                <div class="flex items-center gap-3 mb-10">
                    @if($logo)
                        <img src="{{ $logo }}" alt="logo" class="h-14 w-14 object-contain rounded-lg bg-white/10 p-1">
                    @else
                        <div class="h-14 w-14 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.12);">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        </div>
                    @endif
                    <span class="text-xl font-extrabold tracking-tight uppercase leading-tight">{{ $schoolShort }}</span>
                </div>

                <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight">{{ __('Welcome to') }}<br>{{ $schoolName }}</h1>
                <p class="mt-3 text-blue-100/80 text-lg">{{ __('Staff Portal Access') }}</p>

                {{-- Feature cards --}}
                <div class="mt-9 space-y-4 max-w-xl">
                    @foreach($features as $f)
                    <div class="flex items-start gap-4 rounded-2xl p-4 transition" style="background:rgba(255,255,255,0.08);">
                        <div class="shrink-0 h-12 w-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#fb923c,#f97316);">
                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-white">{{ $f['title'] }}</h3>
                            <p class="text-sm text-blue-100/70 mt-0.5">{{ $f['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Stats --}}
            <div class="relative mt-10 pt-6 border-t border-white/15">
                <div class="grid grid-cols-3 gap-6 max-w-md">
                    <div>
                        <p class="text-3xl font-extrabold" style="color:#fb923c;">{{ number_format($staffCount) }}+</p>
                        <p class="text-sm text-blue-100/70 mt-1">{{ __('Staff Members') }}</p>
                    </div>
                    <div>
                        <p class="text-3xl font-extrabold" style="color:#fb923c;">{{ number_format($deptCount) }}+</p>
                        <p class="text-sm text-blue-100/70 mt-1">{{ __('Departments') }}</p>
                    </div>
                    <div>
                        <p class="text-3xl font-extrabold" style="color:#fb923c;">{{ number_format($studentCount) }}+</p>
                        <p class="text-sm text-blue-100/70 mt-1">{{ __('Students') }}</p>
                    </div>
                </div>
            </div>
        </aside>

        {{-- ── Right: login form ── --}}
        <main class="flex-1 flex items-center justify-center p-6 sm:p-10 bg-gray-50">
            <div class="w-full max-w-md">
                {{-- Mobile brand header --}}
                <div class="lg:hidden text-center mb-8">
                    @if($logo)
                        <img src="{{ $logo }}" alt="logo" class="h-16 mx-auto object-contain">
                    @endif
                    <h1 class="text-2xl font-extrabold text-slate-800 mt-2">{{ $schoolName }}</h1>
                    <p class="text-sm text-gray-500">{{ __('Staff Portal Access') }}</p>
                </div>

                <div class="text-center mb-7">
                    <div class="inline-flex h-16 w-16 rounded-2xl items-center justify-center mb-4" style="background:linear-gradient(135deg,#1d4ed8,#0b2a6b);">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-800">{{ __('Login Into Your Dashboard') }}</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ __('Access your staff dashboard and management tools') }}</p>
                </div>

                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-7">
                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-5">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-2">
                                <svg class="h-4 w-4" style="color:#1d4ed8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ __('Email') }}
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                   placeholder="{{ $emailPlaceholder }}" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-2">
                                <svg class="h-4 w-4" style="color:#1d4ed8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                {{ __('Password') }}
                            </label>
                            <div class="relative">
                                <input type="password" name="password" id="password"
                                       class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition"
                                       placeholder="{{ __('Enter your password') }}" required>
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 px-4 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg id="eye-open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg id="eye-closed" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-600">{{ __('Remember Me') }}</span>
                            </label>
                            <span class="text-sm font-semibold" style="color:#1d4ed8;">{{ __('Forgot Your Password?') }}</span>
                        </div>

                        <button type="submit"
                                class="w-full text-white font-bold py-3 px-4 rounded-xl transition duration-200 shadow-lg flex items-center justify-center gap-2"
                                style="background:linear-gradient(to right,#1d4ed8,#0b2a6b);">
                            {{ __('Login') }}
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                </div>

                <div class="flex items-center justify-center gap-6 mt-6 text-sm text-gray-500">
                    <a href="{{ url('/') }}" class="flex items-center gap-1.5 hover:text-slate-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        {{ __('Back to Website') }}
                    </a>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        {{ __('IT Support') }}
                    </span>
                </div>

                <p class="text-center text-gray-400 text-xs mt-6">
                    &copy; {{ date('Y') }} {{ $schoolShort }} · {{ __('Management System') }}
                </p>
            </div>
        </main>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const open = document.getElementById('eye-open');
            const closed = document.getElementById('eye-closed');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            open.classList.toggle('hidden', show);
            closed.classList.toggle('hidden', !show);
        }
    </script>
</body>
</html>
