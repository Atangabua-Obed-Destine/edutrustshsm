@extends('layouts.admin')

@section('title', __('Depreciation Report'))
@section('breadcrumb', __('Accounting > Fixed Assets > Depreciation Report'))

@section('content')
@php $money = fn ($v) => number_format((float) $v, 0, '.', ' '); @endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Depreciation Report') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('Depreciation charged over a period, posted and still pending.') }}</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.fixed-assets.depreciation-report', request()->query() + ['export' => 'csv']) }}" class="border border-gray-300 hover:bg-gray-50 px-3 py-1.5 rounded-lg text-sm">CSV</a>
            <a href="{{ route('admin.fixed-assets.index') }}" class="text-sm text-blue-600 hover:underline">{{ __('Back to Fixed Assets') }}</a>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded-lg border-gray-300 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Category') }}</label>
            <select name="category_id" class="rounded-lg border-gray-300 text-sm">
                <option value="">{{ __('All') }}</option>
                @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">{{ __('Filter') }}</button>
    </form>

    @if($totals['overdue'] > 0)
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
        {{ trans_choice(':count period has come due but is not in the ledger yet.|:count periods have come due but are not in the ledger yet.', $totals['overdue'], ['count' => $totals['overdue']]) }}
    </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Posted') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $money($totals['posted']) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Pending') }}</p>
            <p class="text-2xl font-bold text-gray-500 mt-1">{{ $money($totals['pending']) }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <h2 class="text-sm font-semibold text-gray-700 px-5 pt-5">{{ __('By category') }}</h2>
        <table class="w-full text-sm mt-3">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">{{ __('Category') }}</th>
                    <th class="px-5 py-3 text-center">{{ __('Assets') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Posted') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Pending') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($byCategory as $row)
                <tr>
                    <td class="px-5 py-3 text-gray-800">{{ $row->name }}</td>
                    <td class="px-5 py-3 text-center">{{ $row->assets }}</td>
                    <td class="px-5 py-3 text-right font-mono">{{ $money($row->posted) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-gray-500">{{ $money($row->pending) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-8 text-center text-gray-500">{{ __('No depreciation falls in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <h2 class="text-sm font-semibold text-gray-700 px-5 pt-5">{{ __('Periods') }}</h2>
        <table class="w-full text-sm mt-3">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-5 py-3 text-left">{{ __('Period') }}</th>
                    <th class="px-5 py-3 text-left">{{ __('Asset') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Amount') }}</th>
                    <th class="px-5 py-3 text-right">{{ __('Book Value') }}</th>
                    <th class="px-5 py-3 text-center">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($lines as $line)
                <tr>
                    <td class="px-5 py-2 text-gray-600">{{ $line->period_date->format('d/m/Y') }}</td>
                    <td class="px-5 py-2"><span class="text-gray-400">{{ $line->asset?->code }}</span> {{ $line->asset?->name }}</td>
                    <td class="px-5 py-2 text-right font-mono">{{ $money($line->amount) }}</td>
                    <td class="px-5 py-2 text-right font-mono text-gray-500">{{ $money($line->book_value) }}</td>
                    <td class="px-5 py-2 text-center text-xs">
                        @if($line->is_posted)
                        <span class="text-emerald-700">{{ __('Posted') }} {{ $line->journalEntry?->entry_number }}</span>
                        @else
                        <span class="{{ $line->period_date->lte(today()) ? 'text-amber-700 font-medium' : 'text-gray-400' }}">{{ __('Pending') }}</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
