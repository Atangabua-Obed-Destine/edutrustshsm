@extends('layouts.admin')

@section('title', __('Subject Enrollment'))
@section('breadcrumb', __('Academic > Subject Enrollment'))

@section('content')
<div class="max-w-5xl">
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Subject Enrollment') }}</h3>
        <p class="text-sm text-gray-500">{{ __('Assign subjects to forms and configure coefficients per stream') }}</p>
    </div>

    <!-- Form Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($forms as $form)
        <a href="{{ route('admin.subject-enrollments.configure', $form) }}"
           class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md hover:border-gray-300 transition group block">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="font-semibold text-gray-800 group-hover:text-blue-600 transition">{{ $form->name }}</h4>
                    <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded mt-1 inline-block">{{ $form->short_name }}</span>
                </div>
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $form->level === 'first_cycle' ? 'bg-sky-100 text-sky-700' : 'bg-violet-100 text-violet-700' }}">
                    {{ $form->level === 'first_cycle' ? __('1st Cycle') : __('2nd Cycle') }}
                </span>
            </div>

            <div class="space-y-2">
                <!-- Streams info -->
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    {{ $form->streams_count }} {{ Str::plural('stream', $form->streams_count) }}
                </div>

                <!-- Enrolled subjects count -->
                @php $subjectCount = $enrollmentCounts[$form->id] ?? 0; @endphp
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                    {{ $subjectCount }} {{ Str::plural('subject', $subjectCount) }} {{ __('enrolled') }}
                </div>
            </div>

            <!-- Configure arrow -->
            <div class="mt-4 flex items-center text-xs font-medium text-gray-400 group-hover:text-blue-600 transition">
                {{ __('Configure subjects') }}
                <svg class="w-3.5 h-3.5 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
        @endforeach
    </div>

    @if($forms->isEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-400">
        {{ __('No forms found.') }} <a href="{{ route('admin.forms.create') }}" class="text-blue-600 hover:underline">{{ __('Create forms first') }}</a>.
    </div>
    @endif
</div>
@endsection
