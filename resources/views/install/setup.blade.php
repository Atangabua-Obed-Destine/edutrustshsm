@extends('install.layout')

@section('content')
    <h1 class="text-2xl font-bold text-gray-900">{{ __('Welcome to EduTrust') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('Two things to start: tell us about the school, and create the super admin who will manage everything else.') }}</p>

    @if($errors->any())
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('install.store', $token) }}" class="mt-6 space-y-6">
        @csrf

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900">{{ __('The school') }}</h2>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="school_name" class="block text-sm font-medium text-gray-700">{{ __('School name') }}</label>
                    <input id="school_name" type="text" name="school_name" value="{{ old('school_name') }}" required class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="school_short_name" class="block text-sm font-medium text-gray-700">{{ __('Short name') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
                    <input id="school_short_name" type="text" name="school_short_name" value="{{ old('school_short_name') }}" class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="school_code" class="block text-sm font-medium text-gray-700">{{ __('School code') }}</label>
                    <input id="school_code" type="text" name="school_code" value="{{ old('school_code') }}" maxlength="10" required class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm uppercase">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Letters and numbers. Every student matricule starts with it, so choose it carefully.') }}</p>
                </div>
            </div>

            <fieldset class="mt-5">
                <legend class="block text-sm font-medium text-gray-700">{{ __('Which levels does the school run?') }}</legend>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach([
                        'nursery_primary' => [__('Nursery / Primary only'), __('Nursery 1–3 and Class 1–6')],
                        'secondary' => [__('Secondary / High School only'), __('Form 1–5 and Lower/Upper Sixth')],
                        'both' => [__('Both'), __('A combined school running nursery/primary and secondary')],
                    ] as $value => [$title, $desc])
                        <label class="flex items-start gap-2 border border-gray-200 rounded-xl p-3 cursor-pointer hover:border-blue-500">
                            <input type="radio" name="school_level_mode" value="{{ $value }}" class="mt-1" @checked(old('school_level_mode') === $value) required>
                            <span>
                                <span class="block text-sm font-semibold text-gray-800">{{ $title }}</span>
                                <span class="block text-xs text-gray-500">{{ $desc }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900">{{ __('The super admin') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('This account can do everything, and creates all the others.') }}</p>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">{{ __('First name') }}</label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">{{ __('Last name') }}</label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                    <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password" class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters.') }}</p>
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm password') }}</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password" class="mt-1 w-full rounded-lg border-gray-300 border px-3 py-2 text-sm">
                </div>
            </div>
        </section>

        <button type="submit" class="w-full text-white font-bold py-3 px-4 rounded-xl shadow-lg" style="background:linear-gradient(to right,#1d4ed8,#0b2a6b);">
            {{ __('Create the school') }}
        </button>
    </form>
@endsection
