@extends('layouts.admin')

@section('title', __('Year-End Closing'))
@section('breadcrumb', __('Accounting > Fiscal Years > Year-End Closing'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
    $net = $preview['net'];
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                {{ __('Year-End Closing') }} <span class="text-gray-400">{{ $fiscalYear->name }}</span>
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Closing zeroes every revenue and expense account into equity, in one balanced entry.') }}
            </p>
        </div>
        <a href="{{ route('admin.fiscal-years.index') }}" class="text-sm text-blue-600 hover:underline">
            {{ __('Back to Fiscal Years') }}
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total Revenue') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($preview['revenue'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Total Expenses') }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($preview['expenses'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Net Result') }}</p>
            <p class="text-2xl font-bold mt-1 {{ $net < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                {{ number_format($net, 2) }}
            </p>
            <p class="text-xs text-gray-400 mt-1">
                {{ $net < 0 ? __('Loss — debited to retained earnings') : __('Profit — credited to retained earnings') }}
            </p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Accounts to be closed') }}</h3>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Account Code') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Account Name') }}</th>
                    <th class="px-6 py-3 text-center">{{ __('Class') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Balance') }} ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($preview['lines'] as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-gray-500">{{ $row->account->account_code }}</td>
                    <td class="px-6 py-3 text-gray-900">{{ $row->account->account_name }}</td>
                    <td class="px-6 py-3 text-center text-gray-600">{{ $row->account->class_number }}</td>
                    <td class="px-6 py-3 text-right font-medium text-gray-900">{{ number_format($row->balance, 2) }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                        {{ __('There is nothing to close: no revenue or expense was posted in this year.') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($preview['lines']->isNotEmpty() && ! $fiscalYear->is_closed)
    @can('fiscal-year.close')
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
        <p class="text-sm text-amber-900">
            {{ __('Every period must already be closed and every entry posted. The closing entry can be reversed afterwards if you need to reopen the year.') }}
        </p>
        <form method="POST" action="{{ route('admin.fiscal-years.close', $fiscalYear) }}" class="mt-4"
              onsubmit="return confirm('{{ __('Post the closing entry and close this fiscal year?') }}')">
            @csrf
            <button type="submit" class="px-5 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Close Fiscal Year') }}
            </button>
        </form>
    </div>
    @endcan
    @endif
</div>
@endsection
