@php
    use App\Models\SchoolSetting;
    use App\Models\Student;
    use App\Models\Guardian;

    $setting = SchoolSetting::current();
    $schoolName = $setting->school_name ?? 'EduTrust School';
    $schoolShort = $setting->school_short_name ?? $schoolName;
    $logo = ($setting && $setting->logo) ? \Illuminate\Support\Facades\Storage::url($setting->logo) : null;

    $studentCount = Student::count();
    $guardianCount = Guardian::where('portal_access', true)->whereNotNull('password')->count();

    $features = [
        ['title' => __('Academic Progress'), 'desc' => __('View report cards, grades and class rankings at a glance'),
         'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['title' => __('Fee Tracking'), 'desc' => __('Check balances, payment history and submit receipts online'),
         'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['title' => __('Attendance & Timetable'), 'desc' => __('Monitor daily attendance and view class schedules'),
         'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['title' => __('School Communication'), 'desc' => __('Stay informed with PTA meetings, announcements and levies'),
         'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Parent Login') }} — {{ $schoolShort }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <div class="min-h-screen flex flex-col lg:flex-row">

        {{-- ── Left: branded panel ── --}}
        <aside class="hidden lg:flex lg:w-1/2 xl:w-7/12 flex-col justify-between p-10 xl:p-14 text-white relative overflow-hidden"
               style="background:linear-gradient(160deg,#064e3b 0%,#065f46 45%,#047857 100%);">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full opacity-10" style="background:#ffffff;"></div>
            <div class="absolute -bottom-32 -left-20 w-96 h-96 rounded-full opacity-5" style="background:#ffffff;"></div>

            <div class="relative">
                {{-- Logo --}}
                <div class="flex items-center gap-3 mb-10">
                    @if($logo)
                        <img src="{{ $logo }}" alt="logo" class="h-14 w-14 object-contain rounded-lg bg-white/10 p-1">
                    @else
                        <div class="h-14 w-14 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.12);">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        </div>
                    @endif
                    <span class="text-xl font-extrabold tracking-tight uppercase leading-tight">{{ $schoolShort }}</span>
                </div>

                <h1 class="text-4xl xl:text-5xl font-extrabold leading-tight">{{ __('Welcome to') }}<br>{{ __('Parent Portal') }}</h1>
                <p class="mt-3 text-emerald-100/80 text-lg">{{ __('Stay connected with your child\'s education journey') }}</p>

                {{-- Feature cards --}}
                <div class="mt-9 space-y-4 max-w-xl">
                    @foreach($features as $f)
                    <div class="flex items-start gap-4 rounded-2xl p-4 transition" style="background:rgba(255,255,255,0.08);">
                        <div class="shrink-0 h-12 w-12 rounded-xl flex items-center justify-center" style="background:linear-gradient(135deg,#5eead4,#14b8a6);">
                            <svg class="h-6 w-6 text-emerald-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-white">{{ $f['title'] }}</h3>
                            <p class="text-sm text-emerald-100/70 mt-0.5">{{ $f['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Stats --}}
            <div class="relative mt-10 pt-6 border-t border-white/15">
                <div class="grid grid-cols-3 gap-6 max-w-md">
                    <div>
                        <p class="text-3xl font-extrabold" style="color:#5eead4;">{{ number_format($studentCount) }}+</p>
                        <p class="text-sm text-emerald-100/70 mt-1">{{ __('Students') }}</p>
                    </div>
                    <div>
                        <p class="text-3xl font-extrabold" style="color:#5eead4;">{{ number_format($guardianCount) }}+</p>
                        <p class="text-sm text-emerald-100/70 mt-1">{{ __('Active Parents') }}</p>
                    </div>
                    <div>
                        <p class="text-3xl font-extrabold" style="color:#5eead4;">24/7</p>
                        <p class="text-sm text-emerald-100/70 mt-1">{{ __('Portal Access') }}</p>
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
                    <p class="text-sm text-gray-500">{{ __('Parent / Guardian Portal') }}</p>
                </div>

                <div class="text-center mb-7">
                    <div class="inline-flex h-16 w-16 rounded-2xl items-center justify-center mb-4" style="background:linear-gradient(135deg,#047857,#064e3b);">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-800">{{ __('Parent Sign In') }}</h2>
                    <p class="text-sm text-gray-500 mt-1">{{ __('Sign in to follow your child\'s progress') }}</p>
                </div>

                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-7">
                    @if ($errors->any())
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-5">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('parent.login.submit') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-2">
                                <svg class="h-4 w-4" style="color:#047857;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                {{ __('Email Address') }}
                            </label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                                   placeholder="parent@example.com" required autofocus>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="flex items-center gap-2 text-sm font-semibold text-slate-700 mb-2">
                                <svg class="h-4 w-4" style="color:#047857;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                {{ __('Password') }}
                            </label>
                            <div class="relative">
                                <input type="password" name="password" id="password"
                                       class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition"
                                       placeholder="{{ __('Enter your password') }}" required>
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 px-4 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg id="eye-open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg id="eye-closed" class="h-5 w-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" name="remember" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                <span class="ml-2 text-sm text-gray-600">{{ __('Keep me signed in') }}</span>
                            </label>
                        </div>

                        <button type="submit"
                                class="w-full text-white font-bold py-3 px-4 rounded-xl transition duration-200 shadow-lg flex items-center justify-center gap-2"
                                style="background:linear-gradient(to right,#047857,#064e3b);">
                            {{ __('Sign In') }}
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </form>
                </div>

                {{-- Activation hint --}}
                <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3 mt-5">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-sm font-semibold text-emerald-800">{{ __('First time here?') }}</p>
                            <p class="text-xs text-emerald-700 mt-0.5">{{ __('Ask the school office for an invitation link to activate your account.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-center gap-6 mt-6 text-sm text-gray-500">
                    <a href="{{ url('/') }}" class="flex items-center gap-1.5 hover:text-slate-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        {{ __('Back to Website') }}
                    </a>
                    <a href="{{ route('login') }}" class="flex items-center gap-1.5 hover:text-slate-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        {{ __('Staff Login') }}
                    </a>
                </div>

                <p class="text-center text-gray-400 text-xs mt-6">
                    &copy; {{ date('Y') }} {{ $schoolShort }} · {{ __('Parent Portal') }}
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
