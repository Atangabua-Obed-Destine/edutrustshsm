@extends('layouts.admin')

@section('title', __('Staff Leave'))
@section('breadcrumb', __('Human Resources > Staff Leave'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Staff Leave') }}</h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Requests count against a staff member\'s annual allowance as soon as they are recorded, not only once approved.') }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @if($pendingCount > 0)
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                {{ trans_choice(':count pending|:count pending', $pendingCount, ['count' => $pendingCount]) }}
            </span>
            @endif
            @can('leave-type.view')
            <a href="{{ route('admin.leave-types.index') }}" class="text-sm text-blue-600 hover:underline">
                {{ __('Leave Types') }}
            </a>
            @endcan
        </div>
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
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Year') }}</label>
                <input type="number" name="year" value="{{ $year }}" class="rounded-lg border-gray-300 text-sm w-28">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Staff') }}</label>
                <select name="user_id" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('All staff') }}</option>
                    @foreach($staff as $person)
                    <option value="{{ $person->id }}" @selected(request('user_id') == $person->id)>
                        {{ $person->first_name }} {{ $person->last_name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Type') }}</label>
                <select name="leave_type_id" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('All types') }}</option>
                    @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected(request('leave_type_id') == $type->id)>{{ $type->title }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Status') }}</label>
                <select name="status" class="rounded-lg border-gray-300 text-sm">
                    <option value="">{{ __('All') }}</option>
                    @foreach(['pending', 'approved', 'rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>
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
                    <th class="px-6 py-3 text-left">{{ __('Type') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Dates') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Days') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Used / Allowance') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Pay') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($leaves as $leave)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 font-medium text-gray-900">
                        {{ $leave->user?->first_name }} {{ $leave->user?->last_name }}
                        <span class="text-gray-400 text-xs">{{ $leave->user?->staff_id }}</span>
                    </td>
                    <td class="px-6 py-3 text-gray-600">{{ $leave->leaveType?->title }}</td>
                    <td class="px-6 py-3 text-gray-600 whitespace-nowrap">
                        {{ $leave->from_date?->format('d/m/Y') }} — {{ $leave->to_date?->format('d/m/Y') }}
                    </td>
                    <td class="px-6 py-3 text-center text-gray-900 font-medium">{{ $leave->daysCount() }}</td>
                    <td class="px-6 py-3 text-center text-gray-600">
                        @if($leave->allowance)
                            <span class="{{ $leave->used_days > $leave->allowance ? 'text-red-600 font-semibold' : '' }}">
                                {{ $leave->used_days }} / {{ $leave->allowance }}
                            </span>
                        @else
                            <span class="text-gray-400">{{ __('Unlimited') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-center text-gray-600">{{ __(ucfirst($leave->pay_type)) }}</td>
                    <td class="px-6 py-3 text-center">
                        @php
                            $tone = match($leave->status) {
                                'approved' => 'bg-emerald-100 text-emerald-700',
                                'rejected' => 'bg-red-100 text-red-700',
                                default => 'bg-amber-100 text-amber-800',
                            };
                        @endphp
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $tone }}">
                            {{ __(ucfirst($leave->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        @if($leave->isPending())
                            @can('staff-leave.approve')
                            <form method="POST" action="{{ route('admin.leaves.approve', $leave) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-emerald-600 hover:underline">{{ __('Approve') }}</button>
                            </form>
                            <span class="text-gray-300 mx-1">|</span>
                            <button type="button" class="text-red-600 hover:underline"
                                    onclick="document.getElementById('reject-{{ $leave->id }}').classList.toggle('hidden')">
                                {{ __('Reject') }}
                            </button>
                            @endcan
                        @else
                            <span class="text-gray-400 text-xs">
                                {{ $leave->reviewedBy ? $leave->reviewedBy->first_name.' '.$leave->reviewedBy->last_name : '' }}
                            </span>
                        @endif
                    </td>
                </tr>
                @if($leave->isPending())
                <tr id="reject-{{ $leave->id }}" class="hidden bg-red-50">
                    <td colspan="8" class="px-6 py-3">
                        <form method="POST" action="{{ route('admin.leaves.reject', $leave) }}" class="flex items-center gap-3">
                            @csrf
                            <input type="text" name="review_notes" required maxlength="500"
                                   placeholder="{{ __('Reason for rejection') }}"
                                   class="flex-1 rounded-lg border-gray-300 text-sm">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-500">
                                {{ __('Confirm Rejection') }}
                            </button>
                        </form>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">{{ __('No leave recorded for this year.') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $leaves->links() }}

    @can('staff-leave.create')
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ __('Record a leave request') }}</h3>
        <form method="POST" action="{{ route('admin.leaves.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Staff') }}</label>
                    <select name="user_id" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">{{ __('Select staff') }}</option>
                        @foreach($staff as $person)
                        <option value="{{ $person->id }}" @selected(old('user_id') == $person->id)>
                            {{ $person->first_name }} {{ $person->last_name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Type') }}</label>
                    <select name="leave_type_id" required class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">{{ __('Select') }}</option>
                        @foreach($types as $type)
                        <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                            {{ $type->title }}@if($type->isCapped()) ({{ $type->annual_limit }} {{ __('days') }})@endif
                        </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
                    <input type="date" name="from_date" value="{{ old('from_date') }}" required
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
                    <input type="date" name="to_date" value="{{ old('to_date') }}" required
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Pay') }}</label>
                    <select name="pay_type" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="paid">{{ __('Paid') }}</option>
                        <option value="unpaid">{{ __('Unpaid') }}</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Reason') }}</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" maxlength="1000"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">
                        {{ __('Attachment') }} <span class="text-gray-400">({{ __('optional') }})</span>
                    </label>
                    <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"
                           class="w-full rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            <button type="submit" class="px-4 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Record Request') }}
            </button>
        </form>
    </div>
    @endcan
</div>
@endsection
