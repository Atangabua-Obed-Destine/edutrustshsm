@extends('layouts.admin')

@section('title', __('Staff List'))
@section('breadcrumb', __('Human Resources > Staff List'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Staff List') }}</h3>
        <a href="{{ route('admin.staff.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add New') }}</a>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Department') }}</label>
            <select name="department_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($departments as $d)<option value="{{ $d->id }}" {{ (string)request('department_id')===(string)$d->id?'selected':'' }}>{{ $d->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Designation') }}</label>
            <select name="designation_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($designations as $d)<option value="{{ $d->id }}" {{ (string)request('designation_id')===(string)$d->id?'selected':'' }}>{{ $d->title }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Role') }}</label>
            <select name="role" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                <option value="teacher" {{ request('role')==='teacher'?'selected':'' }}>{{ __('Teacher') }}</option>
                <option value="staff" {{ request('role')==='staff'?'selected':'' }}>{{ __('Staff') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Work Shift') }}</label>
            <select name="work_shift_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($workShifts as $w)<option value="{{ $w->id }}" {{ (string)request('work_shift_id')===(string)$w->id?'selected':'' }}>{{ $w->title }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Search') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Staff ID') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Department') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Designation') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Salary Type') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Basic Salary') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
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
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($s->basic_salary, 0) }}</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs {{ $s->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $s->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        <a href="{{ route('admin.staff.show', $s) }}" class="text-emerald-600 hover:text-emerald-800 text-xs font-medium">{{ __('View') }}</a>
                        <a href="{{ route('admin.staff.edit', $s) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.staff.destroy', $s) }}" class="inline" onsubmit="return confirm('{{ __('Delete this staff member?') }}')">@csrf @method('DELETE')<button class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button></form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No staff found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $staff->links() }}</div>
</div>
@endsection
