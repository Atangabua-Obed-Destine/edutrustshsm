@php
    $setting = \App\Models\SchoolSetting::current();
    $schoolName = $setting->school_name ?? 'EduTrust School';
    $schoolShort = $setting->school_short_name ?? $schoolName;
    $logo = ($setting && $setting->logo) ? \Illuminate\Support\Facades\Storage::url($setting->logo) : null;

    // The same look as the EdutrustPay console: ink on parchment, one gold accent.
    $features = [
        ['M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.42A12 12 0 0119 17.5 12 12 0 0112 20a12 12 0 01-7-2.5 12 12 0 01.84-6.92L12 14z',
         __('Academic Management'), __('Sessions, forms, students, exams and report cards')],
        ['M9 7h6 M9 11h.01 M12 11h.01 M15 11h.01 M9 14h.01 M12 14h.01 M15 14h.01 M9 17h.01 M12 17h3 M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
         __('Finance & Accounting'), __('Fees, payroll, budgets and the OHADA ledger')],
        ['M17 20h5v-2a3 3 0 00-5.36-1.86 M17 20H7 M17 20v-2c0-.66-.13-1.28-.36-1.86 M7 20H2v-2a3 3 0 015.36-1.86 M7 20v-2c0-.66.13-1.28.36-1.86 M7.36 16.14a5 5 0 019.28 0 M15 7a3 3 0 11-6 0 3 3 0 016 0z',
         __('Admissions & Communication'), __('Applications, enrolment, staff and guardians')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ __('Sign in') }} — {{ $schoolShort }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Ruled faintly, like a register page. */
        .ledger-rule {
            background-image:
                linear-gradient(to bottom, rgba(255, 255, 255, 0.045) 1px, transparent 1px),
                linear-gradient(to right, rgba(232, 181, 74, 0.10) 1px, transparent 1px);
            background-size: 100% 34px, 100% 100%;
            background-position: 0 0, 72px 0;
            background-repeat: repeat, no-repeat;
        }
        .glow { filter: blur(70px); }
        @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        .rise { animation: rise .7s ease-out both; }
        .rise-2 { animation-delay: .12s; }
        .rise-3 { animation-delay: .24s; }
        @media (prefers-reduced-motion: reduce) { .rise { animation: none; } }
    </style>
</head>
<body class="h-full antialiased bg-[#f2efe9] text-[#16202e]" style="font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;">

<div class="flex min-h-full flex-col lg:grid lg:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)]">

    {{-- ═════════════ The school, before anyone is asked for anything. ═════════════ --}}
    <aside class="relative overflow-hidden text-white lg:sticky lg:top-0 lg:h-screen"
           style="background-color: #0b1a2e; background-image: linear-gradient(155deg, #0b1a2e 0%, #13294a 45%, #1f3a5f 100%);">
        <div class="ledger-rule absolute inset-0" aria-hidden="true"></div>
        <div class="glow absolute -right-24 -top-24 h-80 w-80 rounded-full bg-[#e8b54a]/20" aria-hidden="true"></div>
        <div class="glow absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-[#3b6fb0]/30" aria-hidden="true"></div>

        <div class="relative mx-auto flex h-full max-w-xl flex-col px-6 py-8 sm:px-10 lg:py-12">
            <div class="rise flex items-center gap-3">
                @if($logo)
                    <img src="{{ $logo }}" alt="" class="h-11 w-11 shrink-0 rounded-xl bg-white p-1 object-contain">
                @else
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#e8b54a]/40 bg-white/[0.06]">
                        <svg viewBox="0 0 24 24" class="h-6 w-6 text-[#e8b54a]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l7 2.6v5.3c0 4.4-2.9 7.9-7 9.5-4.1-1.6-7-5.1-7-9.5V5.6L12 3z"/><path d="M9 12l2 2 4-4"/></svg>
                    </span>
                @endif
                <div class="leading-tight">
                    <p class="font-serif text-xl">{{ $schoolShort }}</p>
                    <p class="text-xs text-white/65">EduTrust · {{ __('powered by') }} <span class="font-semibold tracking-[0.18em] text-[#e8b54a]">I-NNOVA</span></p>
                </div>
            </div>

            <div class="rise rise-2 mt-8 lg:mt-14">
                {{-- A school's full name can run to three lines, so it sits above the headline, small. --}}
                <p class="max-w-md text-xs font-semibold uppercase tracking-[0.16em] text-white/55">{{ $schoolName }} · {{ __('Staff portal') }}</p>
                <h2 class="mt-3 font-serif text-3xl leading-tight sm:text-4xl">
                    {{ __('Welcome back.') }}<br>
                    <span class="text-[#e8b54a]">{{ __('The whole school, in one place.') }}</span>
                </h2>
                <p class="mt-3 max-w-md text-sm leading-relaxed text-white/75 sm:text-[15px]">
                    {{ __('Classes, marks, report cards, fees and the school\'s books, in one place for the people who run the school.') }}
                </p>
            </div>

            <ul class="rise rise-3 mt-10 hidden space-y-3 lg:block">
                @foreach($features as [$icon, $title, $text])
                    <li class="flex gap-4 rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#e8b54a] text-[#0b1a2e]">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-semibold">{{ $title }}</span>
                            <span class="mt-0.5 block text-[13px] leading-snug text-white/70">{{ $text }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>

    {{-- ═════════════ Signing in. ═════════════ --}}
    <main class="relative flex flex-1 items-center justify-center px-4 py-10 sm:px-8"
          style="background: radial-gradient(1100px 560px at 100% 0%, rgba(232, 181, 74, 0.09), transparent 60%), radial-gradient(900px 520px at 0% 100%, rgba(31, 58, 95, 0.07), transparent 60%);">

        <div class="absolute right-4 top-4 flex overflow-hidden rounded-lg border border-[#c8c0b3] text-xs font-semibold sm:right-8">
            @foreach(['en' => 'EN', 'fr' => 'FR'] as $code => $label)
                <a href="{{ route('lang.switch', $code) }}"
                   class="px-2.5 py-1.5 {{ app()->getLocale() === $code ? 'bg-[#1f3a5f] text-white' : 'text-[#46566b] hover:bg-white' }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="w-full max-w-md">
            <div class="text-center">
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl text-white shadow-lg shadow-[#1f3a5f]/25"
                      style="background: linear-gradient(145deg, #1f3a5f, #0b1a2e);">
                    <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="8" r="3.5"/>
                        <path d="M2.5 20c.6-3.6 3.3-6 6.5-6 1.4 0 2.7.4 3.7 1.2"/>
                        <path d="M18 12l3.5 1.3v2.6c0 2.2-1.5 4-3.5 4.8-2-.8-3.5-2.6-3.5-4.8v-2.6L18 12z" stroke="#e8b54a"/>
                    </svg>
                </span>
                <h1 class="mt-5 font-serif text-3xl">{{ __('Sign in') }}</h1>
                <p class="mt-2 text-sm text-[#6f8096]">{{ __('For the school\'s staff: administration, teachers and the bursary.') }}</p>
            </div>

            <form method="POST" action="{{ route('login') }}"
                  class="mt-8 space-y-5 rounded-2xl border border-[#ded8cf] bg-white p-6 shadow-xl shadow-[#0b1a2e]/[0.06] sm:p-8">
                @csrf

                @if ($errors->any())
                    <div class="flex gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2.5 text-sm text-rose-800" role="alert">
                        <svg viewBox="0 0 24 24" class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5v.5"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div>
                    <label for="email" class="flex items-center gap-1.5 text-sm font-semibold">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 text-[#1f3a5f]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5l8.5 6 8.5-6"/></svg>
                        {{ __('Email') }}
                    </label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}"
                           placeholder="name@school.com"
                           class="mt-1.5 w-full rounded-xl border border-[#c8c0b3] bg-white px-4 py-3 text-sm placeholder:text-[#6f8096]/70 focus:border-[#1f3a5f] focus:outline-none focus:ring-2 focus:ring-[#1f3a5f]/25">
                </div>

                <div>
                    <label for="password" class="flex items-center gap-1.5 text-sm font-semibold">
                        <svg viewBox="0 0 24 24" class="h-4 w-4 text-[#1f3a5f]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 018 0v3"/></svg>
                        {{ __('Password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                               placeholder="{{ __('Your password') }}"
                               class="w-full rounded-xl border border-[#c8c0b3] bg-white py-3 pl-4 pr-12 text-sm placeholder:text-[#6f8096]/70 focus:border-[#1f3a5f] focus:outline-none focus:ring-2 focus:ring-[#1f3a5f]/25">
                        <button type="button" id="toggle-password"
                                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-xl text-[#6f8096] hover:text-[#16202e] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1f3a5f]/40"
                                aria-label="{{ __('Show password') }}" aria-controls="password" aria-pressed="false"
                                data-show="{{ __('Show password') }}" data-hide="{{ __('Hide password') }}">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>
                                <path data-slash d="M4 20L20 4" hidden/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <label class="flex items-center gap-2 text-[#46566b]">
                        <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-[#c8c0b3] text-[#1f3a5f] focus:ring-[#1f3a5f]">
                        {{ __('Stay signed in') }}
                    </label>
                    <button type="button" id="forgot" class="font-semibold text-[#1f3a5f] hover:underline" aria-controls="forgot-note" aria-expanded="false">
                        {{ __('Forgot your password?') }}
                    </button>
                </div>
                <p id="forgot-note" hidden class="-mt-2 rounded-lg bg-[#eef2f7] px-3 py-2 text-xs leading-relaxed text-[#46566b]">
                    {{ __('There is no reset by email yet. Ask the school\'s administrator to set a new password for you.') }}
                </p>

                <button type="submit"
                        class="group flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#1f3a5f]/30 transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1f3a5f]/50 focus-visible:ring-offset-2"
                        style="background: linear-gradient(100deg, #1f3a5f 0%, #13294a 100%);">
                    {{ __('Sign in') }}
                    <svg viewBox="0 0 24 24" class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-[#6f8096]">
                {{ __('Not staff?') }}
                <a href="{{ route('parent.login') }}" class="font-semibold text-[#1f3a5f] hover:underline">{{ __('Parent portal') }}</a>
                ·
                <a href="{{ route('apply.login') }}" class="font-semibold text-[#1f3a5f] hover:underline">{{ __('Online admissions') }}</a>
            </p>
            <p class="mt-2 text-center text-xs text-[#6f8096]">
                © {{ now()->year }} {{ $schoolShort }} · EduTrust, {{ __('powered by') }} <span class="font-semibold tracking-[0.14em] text-[#46566b]">I-NNOVA</span>
            </p>
        </div>
    </main>
</div>

<script>
    (() => {
        const toggle = document.getElementById('toggle-password');
        const field = document.getElementById('password');
        toggle?.addEventListener('click', () => {
            const showing = field.type === 'text';
            field.type = showing ? 'password' : 'text';
            toggle.setAttribute('aria-pressed', String(! showing));
            toggle.setAttribute('aria-label', showing ? toggle.dataset.show : toggle.dataset.hide);
            toggle.querySelector('[data-slash]').hidden = showing;
        });

        const forgot = document.getElementById('forgot');
        const note = document.getElementById('forgot-note');
        forgot?.addEventListener('click', () => {
            note.hidden = ! note.hidden;
            forgot.setAttribute('aria-expanded', String(! note.hidden));
        });
    })();
</script>
</body>
</html>
