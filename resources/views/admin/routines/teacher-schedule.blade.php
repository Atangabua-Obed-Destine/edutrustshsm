@extends('layouts.admin')

@section('title', __('Teacher Routines'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Teacher Routines') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('View weekly schedules per teacher') }}</p>
        </div>
        <a href="{{ route('admin.timetable.class-schedule') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ __('Class Schedules') }}
        </a>
    </div>

    {{-- Teacher Selector --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.timetable.teacher-schedule') }}" class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Select Teacher') }}</label>
                <select name="teacher_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none bg-white"
                        style="appearance: auto; -webkit-appearance: menulist;" required>
                    <option value="">{{ __('— Choose a teacher —') }}</option>
                    @foreach($teachers as $t)
                        <option value="{{ $t->id }}" {{ $teacherId == $t->id ? 'selected' : '' }}>{{ $t->full_name }} ({{ ucfirst($t->role) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                    {{ __('Load Schedule') }}
                </button>
            </div>
        </form>
    </div>

    @if($teacherId && $selectedTeacher)
    {{-- Teacher Info Card --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-slate-800 text-white flex items-center justify-center text-lg font-bold shrink-0">
                {{ strtoupper(substr($selectedTeacher->first_name, 0, 1) . substr($selectedTeacher->last_name, 0, 1)) }}
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ $selectedTeacher->full_name }}</h2>
                <p class="text-sm text-gray-500">{{ ucfirst($selectedTeacher->role) }} &middot; {{ $selectedTeacher->email }}</p>
            </div>
            @php
                $totalPeriods = 0;
                foreach ($entries as $dayEntries) { $totalPeriods += $dayEntries->count(); }
            @endphp
            <div class="ml-auto text-right">
                <p class="text-2xl font-bold text-slate-800">{{ $totalPeriods }}</p>
                <p class="text-xs text-gray-500">{{ __('periods/week') }}</p>
            </div>
        </div>
    </div>

    {{-- Weekly Schedule --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-28 border-r border-gray-200">{{ __('Day') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-r border-gray-200">{{ __('Time') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-r border-gray-200">{{ __('Subject') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider border-r border-gray-200">{{ __('Class') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Room') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php $hasAnyEntries = false; @endphp
                    @foreach($days as $day)
                        @php $dayEntries = $entries->get($day, collect()); @endphp
                        @if($dayEntries->count() > 0)
                            @php $hasAnyEntries = true; @endphp
                            @foreach($dayEntries as $i => $entry)
                            <tr class="hover:bg-gray-50">
                                @if($i === 0)
                                <td class="px-4 py-3 border-r border-gray-200 align-top font-semibold text-gray-900 text-sm" rowspan="{{ $dayEntries->count() }}">
                                    {{ ucfirst($day) }}
                                </td>
                                @endif
                                <td class="px-4 py-3 border-r border-gray-200 text-sm text-gray-700 whitespace-nowrap">
                                    @if($entry->start_time && $entry->end_time)
                                        {{ \Carbon\Carbon::parse($entry->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($entry->end_time)->format('g:i A') }}
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200">
                                    <div class="text-sm font-medium text-gray-900">{{ $entry->subject->name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $entry->subject->code ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 border-r border-gray-200 text-sm text-gray-700">
                                    {{ $entry->classSection->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $entry->room->name ?? '—' }}
                                </td>
                            </tr>
                            @endforeach
                        @endif
                    @endforeach

                    @if(!$hasAnyEntries)
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="text-sm">{{ __('No schedule entries found for this teacher.') }}</p>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @elseif(!$teacherId)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('Select a Teacher') }}</h3>
        <p class="text-gray-500">{{ __('Choose a teacher above to view their weekly routine.') }}</p>
    </div>
    @endif
</div>
@endsection
