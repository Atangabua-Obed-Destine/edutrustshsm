<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Welcome to EduTrust') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50">
    <header class="text-white" style="background:linear-gradient(160deg,#0b2a6b 0%,#13439e 45%,#1d4ed8 100%);">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-8 flex items-start justify-between gap-4">
            <div>
                <p class="text-2xl font-extrabold tracking-tight">EduTrust</p>
                <p class="text-sm text-blue-100/80">{{ __('powered by') }} <span class="font-semibold tracking-wide">I-NNOVA</span></p>
            </div>
            <div class="flex text-xs font-semibold rounded-lg overflow-hidden border border-white/30">
                <a href="{{ route('lang.switch', 'en') }}" class="px-2.5 py-1.5 {{ app()->getLocale() === 'en' ? 'bg-white text-blue-900' : 'text-white' }}">EN</a>
                <a href="{{ route('lang.switch', 'fr') }}" class="px-2.5 py-1.5 {{ app()->getLocale() === 'fr' ? 'bg-white text-blue-900' : 'text-white' }}">FR</a>
            </div>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8">
        @yield('content')
    </main>
</body>
</html>
