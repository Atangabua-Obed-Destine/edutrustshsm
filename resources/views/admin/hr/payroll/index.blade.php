@extends('layouts.admin')

@section('title', __('Payroll'))
@section('breadcrumb', __('Human Resources > Payrolls'))

@php($months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'])

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Payroll') }}</h3>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Salary Type') }}</label>
            <select name="salary_type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                <option value="1" {{ request('salary_type')==='1'?'selected':'' }}>{{ __('Fixed') }}</option>
                <option value="2" {{ request('salary_type')==='2'?'selected':'' }}>{{ __('Hourly') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Department') }}</label>
            <select name="department_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($departments as $d)<option value="{{ $d->id }}" {{ (string)request('department_id')===(string)$d->id?'selected':'' }}>{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Month') }} *</label>
            <select name="month" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @foreach($months as $n => $name)<option value="{{ $n }}" {{ $month==$n?'selected':'' }}>{{ __($name) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Year') }} *</label>
            <input type="number" name="year" value="{{ $year }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <p class="px-4 py-3 text-center font-semibold text-gray-600">{{ strtoupper(__($months[$month])) }} {{ $year }}</p>
        <table class="w-full">
            <thead class="bg-blue-500 text-white">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ __('Staff ID') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ __('Department') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ __('Designation') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ __('Salary Type') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($staff as $s)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-mono text-gray-700">{{ $s->staff_id }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $s->full_name }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $s->department?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $s->designation?->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $s->salary_type == 1 ? __('Fixed') : __('Hourly') }}</td>
                    <td class="px-4 py-3">
                        @if($existing->has($s->id))<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">{{ __('Generated') }}</span>
                        @else<span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">{{ __('Not Generated') }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.payroll.generate', ['staff' => $s->id, 'month' => $month, 'year' => $year]) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded text-sm">{{ $existing->has($s->id) ? __('View') : '+' }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No staff with a salary configured.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
