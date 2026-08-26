@extends('layouts.admin')

@section('title', __('Enrol Sequences'))
@section('breadcrumb', __('Academic > Enrol Sequences'))

@section('content')
<div class="max-w-5xl">
    <div class="mb-6">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Enrol Sequences') }}</h3>
        <p class="text-sm text-gray-500">{{ __('Configure which exam sequences apply to each form, stream, and term') }}</p>
    </div>

    {{-- Summary Bar --}}
    @if($totalTerms > 0 && $totalSequences > 0)
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3 mb-5 flex items-center gap-6">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <span>{{ $totalTerms }} {{ Str::plural(__('term'), $totalTerms) }}</span>
        </div>
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <span>{{ $totalSequences }} {{ Str::plural(__('sequence'), $totalSequences) }} {{ __('available') }}</span>
        </div>
    </div>
    @endif

    @if($totalTerms === 0 || $totalSequences === 0)
    <div class="bg-amber-50 border border-amber-200 text-amber-700 px-5 py-4 rounded-xl text-sm mb-5 space-y-1">
        @if($totalTerms === 0)
            <p>{{ __('No terms defined yet.') }} <a href="{{ route('admin.terms.index') }}" class="underline font-medium">{{ __('Create terms first') }}</a>.</p>
        @endif
        @if($totalSequences === 0)
            <p>{{ __('No exam sequences defined yet.') }} <a href="{{ route('admin.sequences.index') }}" class="underline font-medium">{{ __('Create sequences first') }}</a>.</p>
        @endif
    </div>
    @endif

    {{-- Form Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($forms as $form)
        <a href="{{ route('admin.sequence-enrollments.configure', $form) }}"
           class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 hover:shadow-md hover:border-gray-300 transition group block {{ ($totalTerms === 0 || $totalSequences === 0) ? 'opacity-50 pointer-events-none' : '' }}">
            <div class="flex items-start justify-between mb-3">
                <div>
                    <h4 class="font-semibold text-gray-800 group-hover:text-blue-600 transition">{{ $form->name }}</h4>
                    <span class="font-mono text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded mt-1 inline-block">{{ $form->short_name }}</span>
                </div>
                <div class="flex flex-wrap gap-1">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $form->level === 'first_cycle' ? 'bg-sky-100 text-sky-700' : 'bg-violet-100 text-violet-700' }}">
                        {{ $form->level === 'first_cycle' ? __('1st Cycle') : __('2nd Cycle') }}
                    </span>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $form->education_system === 'english' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                        {{ $form->education_system === 'english' ? __('English') : __('French') }}
                    </span>
                </div>
            </div>

            <div class="space-y-2">
                {{-- Streams count --}}
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    {{ $form->streams_count }} {{ Str::plural('stream', $form->streams_count) }}
                </div>

                {{-- Enrolled sequences count --}}
                @php $assignCount = $enrollmentCounts[$form->id] ?? 0; @endphp
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    {{ $assignCount }} {{ __('sequence assignment(s)') }}
                </div>

                {{-- Terms configured --}}
                @php $tCount = $termCounts[$form->id] ?? 0; @endphp
                <div class="flex items-center text-sm {{ $tCount > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                    <svg class="w-4 h-4 mr-2 {{ $tCount > 0 ? 'text-emerald-500' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    @if($tCount > 0)
                        {{ $tCount }}/{{ $totalTerms }} {{ Str::plural(__('term'), $tCount) }} {{ __('configured') }}
                    @else
                        {{ __('Not configured') }}
                    @endif
                </div>
            </div>

            {{-- Configure arrow --}}
            <div class="mt-4 flex items-center text-xs font-medium text-gray-400 group-hover:text-blue-600 transition">
                {{ __('Configure sequences') }}
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
