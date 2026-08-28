@php
    $school = \App\Models\SchoolSetting::current();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — {{ $school->school_name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="max-w-md w-full text-center">
        <p class="text-7xl font-bold text-slate-800">@yield('code')</p>
        <h1 class="mt-4 text-xl font-semibold text-gray-900">@yield('title')</h1>
        <p class="mt-2 text-sm text-gray-500">@yield('message')</p>

        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="{{ url()->previous() }}"
               class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-100 transition">
                {{ __('Go back') }}
            </a>
            <a href="{{ auth()->check() ? route(auth()->user()->homeRoute() ?? 'login') : route('login') }}"
               class="px-4 py-2.5 rounded-lg bg-slate-800 text-white text-sm font-medium hover:bg-slate-700 transition">
                {{ __('Home') }}
            </a>
        </div>

        @if($school?->school_name)
        <p class="mt-10 text-xs text-gray-400">{{ $school->school_name }}</p>
        @endif
    </div>
</body>
</html>
