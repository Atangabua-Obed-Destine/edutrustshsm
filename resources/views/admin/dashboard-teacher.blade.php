@extends('layouts.admin')
@section('title', __('My Classes'))
@section('breadcrumb', __('Dashboard'))

@php
    $time = fn ($entry) => substr((string) ($entry->start_time ?? $entry->slot?->start_time), 0, 5);
    $until = fn ($entry) => substr((string) ($entry->end_time ?? $entry->slot?->end_time), 0, 5);
    $subjectCards = $cards->where('nursery', false);
@endphp

@section('content')
<div class="space-y-5">
    <div class="bg-gradient-to-r from-[#1e293b] to-[#334155] rounded-xl p-6 text-white">
        <h2 class="text-2xl font-bold">{{ __('Good :part, :name', ['part' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => auth()->user()->first_name]) }}</h2>
        <p class="text-gray-300 mt-1">{{ $school?->school_name }} · {{ $currentSession?->name }}{{ $term ? ' · '.$term->name : '' }}</p>
        @if($todo > 0)
            <p class="text-sm text-amber-200 mt-2">{{ trans_choice('{1} :count thing is waiting for you below.|[2,*] :count things are waiting for you below.', $todo, ['count' => $todo]) }}</p>
        @else
            <p class="text-sm text-emerald-200 mt-2">{{ __('Nothing is waiting for you. Everything due is done.') }}</p>
        @endif
    </div>

    {{-- The day and the week at a glance --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl p-4 text-white bg-gradient-to-br from-indigo-500 to-violet-600">
            <p class="text-3xl font-bold">{{ $today->count() }}</p>
            <p class="text-sm font-medium">{{ __('Periods today') }}</p>
            <p class="text-xs text-white/80">{{ now()->translatedFormat('l') }}</p>
        </div>
        <div class="rounded-xl p-4 text-white bg-gradient-to-br from-pink-500 to-rose-500">
            <p class="text-3xl font-bold">{{ $subjectCards->count() }}</p>
            <p class="text-sm font-medium">{{ __('Subjects I teach') }}</p>
            <p class="text-xs text-white/80">{{ $currentSession?->name }}</p>
        </div>
        <div class="rounded-xl p-4 text-white bg-gradient-to-br from-sky-500 to-cyan-500">
            <p class="text-3xl font-bold">{{ $week->count() }}</p>
            <p class="text-sm font-medium">{{ __('Periods a week') }}</p>
            <a href="{{ route('admin.timetable.teacher-schedule', ['teacher_id' => auth()->id()]) }}" class="text-xs text-white/90 underline">{{ __('My Timetable') }}</a>
        </div>
    </div>

    @if($cards->isEmpty() && $duties->isEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-10 text-center">
            <h3 class="font-semibold text-gray-800">{{ __('You have no class yet.') }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ __('The head teacher assigns classes and subjects. Once that is done, your classes appear here with their registers and marks.') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Today's periods --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden lg:col-span-2">
            <div class="px-5 py-3 border-b border-gray-100"><h3 class="font-semibold text-gray-800">{{ __("Today's periods") }}</h3></div>
            @if($today->isEmpty())
                <p class="px-5 py-6 text-sm text-gray-400">{{ today()->isWeekend() ? __('No school today.') : __('No period on your timetable today.') }}</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($today as $entry)
                        <li class="px-5 py-2.5 flex items-center gap-3 text-sm">
                            <span class="w-24 shrink-0 font-semibold text-gray-800 tabular-nums">{{ $time($entry) }}–{{ $until($entry) }}</span>
                            <span class="flex-1 min-w-0">
                                @if($entry->subject?->code)<span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[0.7rem] font-semibold mr-1">{{ $entry->subject->code }}</span>@endif
                                <span class="text-gray-700">{{ $entry->subject?->name }}</span>
                            </span>
                            <span class="text-gray-500">{{ $entry->classSection?->name }}</span>
                            @if($entry->room)<span class="hidden sm:inline text-xs text-gray-400">{{ $entry->room->name }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- What only the class teacher does --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h3 class="font-semibold text-gray-800">{{ __('My class') }}</h3></div>
            @forelse($duties as $d)
                <div class="divide-y divide-gray-100 border-b border-gray-100 last:border-0">
                    <p class="px-5 pt-3 pb-1 text-sm font-semibold text-gray-700">{{ $d['section']->name }} <span class="text-xs font-normal text-gray-400">· {{ trans_choice('{0} no pupils|{1} :count pupil|[2,*] :count pupils', $d['pupils'], ['count' => $d['pupils']]) }}</span></p>
                    @can('attendance.mark')
                        <a href="{{ route('admin.attendance.index', ['class_section_id' => $d['section']->id]) }}" class="flex items-center justify-between px-5 py-2.5 hover:bg-gray-50">
                            <span class="text-sm text-gray-700">{{ __("Today's register") }}</span>
                            @if($d['register_taken'])
                                <span class="text-xs font-medium text-emerald-700">{{ __('Taken') }} · {{ __(':n absent', ['n' => $d['absent_today']]) }}</span>
                            @else
                                <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">{{ __('Not taken yet') }}</span>
                            @endif
                        </a>
                    @endcan
                    @if($term)
                        <a href="{{ route('admin.report-cards.remarks', ['class_section_id' => $d['section']->id, 'term_id' => $term->id]) }}" class="flex items-center justify-between px-5 py-2.5 hover:bg-gray-50">
                            <span class="text-sm text-gray-700">{{ __('Report card remarks') }}</span>
                            <span class="text-xs text-teal-700 font-medium">{{ __('Write') }} &rarr;</span>
                        </a>
                    @endif
                </div>
            @empty
                <p class="px-5 py-6 text-sm text-gray-400">{{ __('You are not class teacher of a class.') }}</p>
            @endforelse
        </div>
    </div>

    {{-- My subjects, as cards --}}
    @if($cards->isNotEmpty())
        <div>
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-800">{{ __('My subjects') }}{{ $term ? ' · '.$term->name : '' }}</h3>
                @can('marks-entry.view')<a href="{{ route('admin.marks.index') }}" class="text-sm text-indigo-700 hover:underline">{{ __('Marks Entry') }} &rarr;</a>@endcan
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($cards as $card)
                    @include('admin.marks._subject-card', ['card' => $card, 'term' => $term])
                @endforeach
            </div>
        </div>
    @endif

    @if($notices->isNotEmpty())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-800 mb-3">{{ __('Notices for staff') }}</h3>
            <ul class="divide-y divide-gray-100">
                @foreach($notices as $notice)
                    <li class="py-2 text-sm"><span class="font-medium text-gray-800">{{ $notice->title }}</span> <span class="text-xs text-gray-400">· {{ optional($notice->published_at)->format('d M') }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
