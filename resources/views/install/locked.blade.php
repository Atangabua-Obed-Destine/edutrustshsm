@extends('install.layout')

@section('content')
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <h1 class="text-lg font-bold text-gray-900">{{ __('EduTrust is not set up on this server yet') }}</h1>
        <p class="mt-2 text-sm text-gray-600">{{ __('Setting it up needs the private link the installer gives. On the server, in the EduTrust folder, run:') }}</p>
        <pre class="mt-4 bg-gray-900 text-gray-100 text-sm rounded-lg p-3 overflow-x-auto">php artisan edutrust:install</pre>
    </div>
@endsection
