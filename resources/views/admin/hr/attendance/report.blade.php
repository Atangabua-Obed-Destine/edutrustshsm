@extends('layouts.admin')

@section('title', __('Staff Attendance Report'))
@section('breadcrumb', __('Human Resources > Staff Attendance > Report'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Staff Attendance Report') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('The rate counts present and late days against days that were actually workable — leave and holidays are excluded from both sides.') }}
            </p>
        </div>
        <a href="{{ route('admin.staff-attendance.index') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Back to Register') }}
        </a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Month') }}</label>
                <select name="month" class="rounded-lg border-gray-300 text-sm">
                    @foreach(range(1, 12) as $m)
                    <option value="{{ $m }}" @selected($month === $m)>
                        {{ \Illuminate\Support\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Year') }}</label>
                <input type="number" name="year" value="{{ $year }}" class="rounded-lg border-gray-300 text-sm w-28">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Department') }}</label>
                <select name="department_id" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('All departments') }}</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Filter') }}
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Staff') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Present') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Late') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Absent') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Leave') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Holiday') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Rate') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($rows as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">
                        {{ $row->staff->first_name }} {{ $row->staff->last_name }}
                        <span class="text-gray-400 text-xs ml-1">{{ $row->staff->staff_id }}</span>
                    </td>
                    <td class="px-6 py-3 text-center text-gray-900">{{ $row->present }}</td>
                    <td class="px-6 py-3 text-center text-amber-700">{{ $row->late }}</td>
                    <td class="px-6 py-3 text-center {{ $row->absent > 0 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">{{ $row->absent }}</td>
                    <td class="px-6 py-3 text-center text-blue-700">{{ $row->leave }}</td>
                    <td class="px-6 py-3 text-center text-gray-500">{{ $row->holiday }}</td>
                    <td class="px-6 py-3 text-center">
                        @if($row->rate === null)
                            <span class="text-gray-400 text-xs">{{ __('Nothing recorded') }}</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold
                                {{ $row->rate >= 90 ? 'bg-emerald-100 text-emerald-700' : ($row->rate >= 75 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-700') }}">
                                {{ $row->rate }}%
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">{{ __('No active staff found.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
