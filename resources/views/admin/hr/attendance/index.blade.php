@extends('layouts.admin')

@section('title', __('Staff Attendance'))
@section('breadcrumb', __('Human Resources > Staff Attendance'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Staff Attendance') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('A record of who was in. Attendance does not affect pay.') }}
            </p>
        </div>
        <a href="{{ route('admin.staff-attendance.report') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Monthly Report') }}
        </a>
    </div>

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="text-sm text-red-800 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Date') }}</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}" class="rounded-lg border-gray-300 text-sm">
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
                {{ __('Load') }}
            </button>
            @if($recorded > 0)
            <span class="text-xs text-gray-500">
                {{ trans_choice(':count record saved for this day|:count records saved for this day', $recorded, ['count' => $recorded]) }}
            </span>
            @endif
        </div>
    </form>

    @if($staff->isEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center text-gray-500">
        {{ __('No active staff found.') }}
    </div>
    @else
    @can('staff-attendance.mark')
    <form method="POST" action="{{ route('admin.staff-attendance.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date->toDateString() }}">

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
            <div class="px-6 py-3 border-b border-gray-200 flex flex-wrap items-center gap-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Mark all as') }}</span>
                @foreach(['present' => __('Present'), 'absent' => __('Absent'), 'holiday' => __('Holiday')] as $value => $label)
                <button type="button" data-mark-all="{{ $value }}"
                        class="px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200">
                    {{ $label }}
                </button>
                @endforeach
            </div>

            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left">{{ __('Staff') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('Status') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('In') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('Out') }}</th>
                        <th class="px-6 py-3 text-left">{{ __('Note') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($staff as $i => $person)
                    @php
                        $record = $existing->get($person->id);
                        $default = $record?->status ?? ($onLeave->has($person->id) ? 'leave' : 'present');
                        $statuses = [
                            'present' => __('Present'),
                            'absent' => __('Absent'),
                            'late' => __('Late'),
                            'leave' => __('Leave'),
                            'holiday' => __('Holiday'),
                        ];
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <input type="hidden" name="attendance[{{ $i }}][user_id]" value="{{ $person->id }}">
                            <span class="font-medium text-gray-900">{{ $person->first_name }} {{ $person->last_name }}</span>
                            <span class="text-gray-400 text-xs ml-1">{{ $person->staff_id }}</span>
                            @if($onLeave->has($person->id))
                            <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                {{ __('On approved leave') }}
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            <select name="attendance[{{ $i }}][status]" class="status-select rounded-lg border-gray-300 text-sm">
                                @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($default === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-6 py-3">
                            <input type="time" name="attendance[{{ $i }}][check_in]"
                                   value="{{ $record?->check_in ? substr($record->check_in, 0, 5) : '' }}"
                                   class="rounded-lg border-gray-300 text-sm w-28">
                        </td>
                        <td class="px-6 py-3">
                            <input type="time" name="attendance[{{ $i }}][check_out]"
                                   value="{{ $record?->check_out ? substr($record->check_out, 0, 5) : '' }}"
                                   class="rounded-lg border-gray-300 text-sm w-28">
                        </td>
                        <td class="px-6 py-3">
                            <input type="text" name="attendance[{{ $i }}][note]" maxlength="255"
                                   value="{{ $record?->note }}" class="w-full rounded-lg border-gray-300 text-sm">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            <button type="submit" class="px-5 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Save Attendance') }}
            </button>
        </div>
    </form>

    <script>
        document.querySelectorAll('[data-mark-all]').forEach(function (button) {
            button.addEventListener('click', function () {
                var status = button.getAttribute('data-mark-all');
                document.querySelectorAll('.status-select').forEach(function (select) {
                    select.value = status;
                });
            });
        });
    </script>
    @endcan
    @endif
</div>
@endsection
