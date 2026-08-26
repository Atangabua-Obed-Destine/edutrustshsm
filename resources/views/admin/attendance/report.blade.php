@extends('layouts.admin')

@section('title', __('Attendance Report'))

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Attendance Report') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('View attendance statistics by class and date range') }}</p>
        </div>
        <a href="{{ route('admin.attendance.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ __('Mark Attendance') }}
        </a>
    </div>

    {{-- Filter Form --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <form method="GET" action="{{ route('admin.attendance.report') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Class') }}</label>
                <select name="class_section_id" class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
                    <option value="">— {{ __('Select Class') }} —</option>
                    @foreach($classSections as $cs)
                        <option value="{{ $cs->id }}" {{ $classSectionId == $cs->id ? 'selected' : '' }}>{{ $cs->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Start Date') }}</label>
                <input type="date" name="start_date" value="{{ $startDate }}"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('End Date') }}</label>
                <input type="date" name="end_date" value="{{ $endDate }}"
                       class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-slate-500 focus:border-slate-500" required>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-6 py-2.5 bg-slate-800 text-white rounded-lg hover:bg-slate-700 font-medium text-sm transition">
                    {{ __('Generate Report') }}
                </button>
            </div>
        </form>
    </div>

    @if($summary)
    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total Students') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $summary->total_students }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Days Tracked') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $summary->total_days }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Avg Present') }}</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ number_format($summary->avg_present, 1) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Avg Absent') }}</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ number_format($summary->avg_absent, 1) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Avg Rate') }}</p>
            <p class="text-2xl font-bold text-slate-800 mt-1">{{ number_format($summary->avg_rate, 1) }}%</p>
        </div>
    </div>

    {{-- Report Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-lg font-semibold text-gray-900">{{ $selectedClass->name ?? '' }}</h2>
            <p class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} — {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-10">#</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Student') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-green-600 uppercase tracking-wider">{{ __('Present') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-red-600 uppercase tracking-wider">{{ __('Absent') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-yellow-600 uppercase tracking-wider">{{ __('Late') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-blue-600 uppercase tracking-wider">{{ __('Excused') }}</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">{{ __('Attendance %') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @foreach($reportData->values() as $i => $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($row->enrollment->student->first_name, 0, 1) . substr($row->enrollment->student->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $row->enrollment->student->last_name }} {{ $row->enrollment->student->first_name }}</div>
                                    <div class="text-xs text-gray-400">{{ $row->enrollment->student->student_id }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-700 text-xs font-bold">{{ $row->present }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ $row->absent > 0 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-400' }} text-xs font-bold">{{ $row->absent }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ $row->late > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-400' }} text-xs font-bold">{{ $row->late }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full {{ $row->excused > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }} text-xs font-bold">{{ $row->excused }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="h-2 rounded-full {{ $row->rate >= 90 ? 'bg-green-500' : ($row->rate >= 75 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                         style="width: {{ $row->rate }}%"></div>
                                </div>
                                <span class="text-xs font-semibold {{ $row->rate >= 90 ? 'text-green-600' : ($row->rate >= 75 ? 'text-yellow-600' : 'text-red-600') }}">{{ $row->rate }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @elseif(!$classSectionId)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <h3 class="text-lg font-medium text-gray-900 mb-1">{{ __('Generate a Report') }}</h3>
        <p class="text-gray-500">{{ __('Select a class and date range to view attendance statistics.') }}</p>
    </div>
    @endif
</div>
@endsection
