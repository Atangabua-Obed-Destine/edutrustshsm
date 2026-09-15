@extends('layouts.admin')
@section('title', __('Partial Payments'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'XAF';
    $money = fn ($v) => number_format((float) $v, 0, '.', ' ');
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.fee-reports.index') }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Fee Reports & Analytics') }}</a>
            <h1 class="text-2xl font-bold text-gray-800 mt-1">{{ __('Partial Payments') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('Fees a family has started paying but not finished, oldest due date first.') }}</p>
        </div>
        @can('fee-report.export')
        <a href="{{ route('admin.fee-reports.partial.csv', request()->query()) }}" class="border border-gray-300 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium">{{ __('Export CSV') }}</a>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            [__('Fees'), number_format((int) $stats->fees), 'text-gray-800'],
            [__('Total due'), $money($stats->due), 'text-gray-800'],
            [__('Paid so far'), $money($stats->paid), 'text-emerald-700'],
            [__('Still owed'), $money($stats->remaining), 'text-red-700'],
        ] as [$label, $value, $tone])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase tracking-wider font-medium">{{ $label }}</p>
            <p class="text-xl font-bold {{ $tone }} mt-2">{{ $value }}</p>
        </div>
        @endforeach
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Session') }}</label>
            <select name="session_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($sessions as $session)
                <option value="{{ $session->id }}" @selected((int) $sessionId === $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Class') }}</label>
            <select name="class_section_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($sections as $section)
                <option value="{{ $section->id }}" @selected((string) request('class_section_id') === (string) $section->id)>{{ $section->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Fee Category') }}</label>
            <select name="fee_category_id" class="w-full rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('fee_category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Search') }}</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Name or student ID') }}" class="w-full rounded-lg border-gray-300 text-sm">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">{{ __('Student') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Class') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Fee Category') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Total') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Paid') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('Remaining') }}</th>
                    <th class="px-4 py-3 text-left">{{ __('Due Date') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('Payments') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($fees as $fee)
                @php $overdue = $fee->due_date && $fee->due_date->isPast(); @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800">{{ $fee->enrollment?->student?->full_name }}</div>
                        <div class="text-xs text-gray-400">{{ $fee->enrollment?->student?->student_id }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $fee->enrollment?->classSection?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $fee->feeCategory?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-mono">{{ $money($fee->net_amount) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-emerald-700">{{ $money($fee->paid_amount) }}</td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-red-700">{{ $money($fee->balance) }}</td>
                    <td class="px-4 py-3 {{ $overdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                        {{ $fee->due_date?->format('d/m/Y') ?? '—' }}
                        @if($overdue)<span class="block text-xs">{{ __(':days days overdue', ['days' => (int) $fee->due_date->diffInDays(today())]) }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $fee->receipts_count }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-12 text-center text-gray-400">{{ __('No partially paid fees match these filters.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $fees->links() }}</div>
</div>
@endsection
