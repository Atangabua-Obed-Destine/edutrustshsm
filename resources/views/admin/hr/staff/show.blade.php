@extends('layouts.admin')
@section('title', __('Staff Detail'))
@section('breadcrumb', __('Human Resources > Staff > :n', ['n' => $staff->staff_id]))
@section('content')
<div class="max-w-3xl space-y-4">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">{{ $staff->full_name }}</h3>
                <p class="text-sm text-gray-500">{{ $staff->staff_id }} · {{ $staff->designation?->title }} · {{ $staff->department?->name }}</p>
            </div>
            <a href="{{ route('admin.staff.edit', $staff) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Edit') }}</a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-4 text-sm">
            <div><span class="text-gray-400">{{ __('Email') }}:</span> {{ $staff->email }}</div>
            <div><span class="text-gray-400">{{ __('Phone') }}:</span> {{ $staff->phone ?? '—' }}</div>
            <div><span class="text-gray-400">{{ __('Salary Type') }}:</span> {{ $staff->salary_type == 1 ? __('Fixed') : __('Hourly') }}</div>
            <div><span class="text-gray-400">{{ __('Basic Salary') }}:</span> {{ number_format($staff->basic_salary, 2) }}</div>
            <div><span class="text-gray-400">{{ __('Joining') }}:</span> {{ optional($staff->joining_date)->format('d/m/Y') ?? '—' }}</div>
            <div><span class="text-gray-400">{{ __('ID Validity') }}:</span> {{ $staff->id_card_validity }}</div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h4 class="font-semibold text-gray-700 mb-3">{{ __('Recent Payrolls') }}</h4>
        @forelse($staff->payrolls->sortByDesc('salary_month')->take(6) as $p)
        <div class="flex justify-between text-sm border-b border-gray-50 py-1.5"><span>{{ $p->salary_month }}</span><span>{{ number_format($p->net_salary, 2) }}</span><span class="{{ $p->status ? 'text-green-600' : 'text-gray-400' }}">{{ $p->status ? __('Paid') : __('Unpaid') }}</span></div>
        @empty<p class="text-sm text-gray-500">{{ __('No payrolls yet.') }}</p>@endforelse
    </div>
</div>
@endsection
