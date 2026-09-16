@extends('layouts.admin')

@section('title', __('Year-End Closing'))
@section('breadcrumb', __('Accounting > Fiscal Years > Year-End Closing'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
    $net = $preview['net'];
    $ready = $outstanding === [];
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

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
    @endif

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

    @unless($fiscalYear->is_closed)
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Checked by the system') }}</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($automatic as $check)
                <li class="flex items-start gap-2">
                    <span class="mt-0.5 font-bold {{ $check['passed'] ? 'text-emerald-600' : 'text-red-600' }}">{{ $check['passed'] ? '✓' : '✗' }}</span>
                    <span>
                        <span class="text-gray-800">{{ $check['label'] }}</span>
                        @if($check['detail'])<span class="block text-xs text-red-600">{{ $check['detail'] }}</span>@endif
                    </span>
                </li>
                @endforeach
            </ul>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700">{{ __('Confirmed by a person') }}</h3>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($manual as $item)
                <li class="flex items-start justify-between gap-3">
                    <span class="flex items-start gap-2">
                        <span class="mt-0.5 font-bold {{ $item['confirmed'] ? 'text-emerald-600' : 'text-gray-300' }}">{{ $item['confirmed'] ? '✓' : '○' }}</span>
                        <span>
                            <span class="text-gray-800">{{ $item['label'] }}</span>
                            @if($item['confirmed'])
                            <span class="block text-xs text-gray-400">{{ __('Confirmed by :user on :date', ['user' => $users[$item['by']] ?? '—', 'date' => \Illuminate\Support\Carbon::parse($item['at'])->format('d/m/Y H:i')]) }}</span>
                            @endif
                        </span>
                    </span>
                    @can('fiscal-year.close')
                    <form method="POST" action="{{ route('admin.fiscal-years.closing.confirm', $fiscalYear) }}">
                        @csrf
                        <input type="hidden" name="item" value="{{ $item['key'] }}">
                        <input type="hidden" name="confirmed" value="{{ $item['confirmed'] ? 0 : 1 }}">
                        <button type="submit" class="text-xs {{ $item['confirmed'] ? 'text-gray-500' : 'text-blue-600' }} hover:underline">
                            {{ $item['confirmed'] ? __('Undo') : __('Confirm') }}
                        </button>
                    </form>
                    @endcan
                </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endunless

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

    @can('fiscal-year.close')
    @if(! $fiscalYear->is_closed && $preview['lines']->isNotEmpty())
    <div class="{{ $ready ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200' }} border rounded-xl p-6">
        @if($ready)
        <p class="text-sm text-amber-900">{{ __('Every check has passed. The closing entry can be reversed afterwards if you need to reopen the year.') }}</p>
        <form method="POST" action="{{ route('admin.fiscal-years.close', $fiscalYear) }}" class="mt-4"
              onsubmit="return confirm('{{ __('Post the closing entry and close this fiscal year?') }}')">
            @csrf
            <button type="submit" class="px-5 py-2.5 bg-slate-800 text-white rounded-lg text-sm font-medium hover:bg-slate-700">
                {{ __('Close Fiscal Year') }}
            </button>
        </form>
        @else
        <p class="text-sm text-gray-700">{{ trans_choice('The year cannot be closed until :count item is dealt with.|The year cannot be closed until :count items are dealt with.', count($outstanding), ['count' => count($outstanding)]) }}</p>
        @endif
    </div>
    @endif

    @if($fiscalYear->is_closed)
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700">{{ __('Reopen this year') }}</h3>
        <p class="text-sm text-gray-500 mt-1">{{ __('The closing entry is reversed and the year opens for posting again. Say why, so the next person to close it knows.') }}</p>
        <form method="POST" action="{{ route('admin.fiscal-years.reopen', $fiscalYear) }}" class="mt-3 flex flex-wrap gap-2"
              onsubmit="return confirm('{{ __('Reverse the closing entry and reopen this year?') }}')">
            @csrf
            <input type="text" name="reason" required minlength="5" maxlength="500" placeholder="{{ __('Why is the year being reopened?') }}"
                   class="flex-1 min-w-[16rem] px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <button type="submit" class="px-4 py-2 border border-red-200 text-red-700 hover:bg-red-50 rounded-lg text-sm font-medium">{{ __('Reopen Year') }}</button>
        </form>
    </div>
    @endif
    @endcan

    @if($history->isNotEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <h3 class="text-sm font-semibold text-gray-700 px-6 pt-5">{{ __('Closing history') }}</h3>
        <table class="w-full text-sm mt-3">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">{{ __('Status') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Closed') }}</th>
                    <th class="px-6 py-3 text-right">{{ __('Net Result') }}</th>
                    <th class="px-6 py-3 text-left">{{ __('Reversed') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($history as $closing)
                <tr>
                    <td class="px-6 py-3">{{ __(ucfirst(str_replace('_', ' ', $closing->status))) }}</td>
                    <td class="px-6 py-3 text-gray-600">
                        @if($closing->closed_at){{ $closing->closed_at->format('d/m/Y') }} · {{ $closing->closedBy?->full_name }} @if($closing->closingEntry)<span class="text-gray-400">{{ $closing->closingEntry->entry_number }}</span>@endif @else — @endif
                    </td>
                    <td class="px-6 py-3 text-right font-mono">{{ $closing->net_result !== null ? number_format((float) $closing->net_result, 2) : '—' }}</td>
                    <td class="px-6 py-3 text-gray-600">
                        @if($closing->reversed_at){{ $closing->reversed_at->format('d/m/Y') }} · {{ $closing->reversedBy?->full_name }}<span class="block text-xs">{{ $closing->reversal_reason }}</span>@else — @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
