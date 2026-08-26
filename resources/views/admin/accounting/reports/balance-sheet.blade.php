@extends('layouts.admin')

@section('title', __('Balance Sheet'))
@section('breadcrumb', __('Accounting > Reports > Balance Sheet'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'FCFA')
@php($balanced = abs($totalAssets - ($totalLiab + $totalEquity)) < 0.01)

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Balance Sheet') }} <span class="text-sm text-gray-400">(Bilan)</span></h3>
        <form method="GET" class="flex items-end gap-2">
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('As of') }}</label><input type="date" name="end_date" value="{{ $to }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('View') }}</button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Assets') }} (Actif)</h4>
            @foreach($assets as $row)
            <div class="flex justify-between text-sm py-1"><span class="text-gray-700"><span class="font-mono text-gray-400">{{ $row->account->account_code }}</span> {{ $row->account->account_name }}</span><span>{{ number_format($row->balance, 2) }}</span></div>
            @endforeach
            <div class="flex justify-between text-sm font-bold border-t mt-2 pt-2"><span>{{ __('Total Assets') }}</span><span>{{ number_format($totalAssets, 2) }} {{ $currency }}</span></div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Liabilities & Equity') }} (Passif)</h4>
            @foreach($liabilities as $row)
            <div class="flex justify-between text-sm py-1"><span class="text-gray-700"><span class="font-mono text-gray-400">{{ $row->account->account_code }}</span> {{ $row->account->account_name }}</span><span>{{ number_format($row->balance, 2) }}</span></div>
            @endforeach
            @foreach($equity as $row)
            <div class="flex justify-between text-sm py-1"><span class="text-gray-700"><span class="font-mono text-gray-400">{{ $row->account->account_code }}</span> {{ $row->account->account_name }}</span><span>{{ number_format($row->balance, 2) }}</span></div>
            @endforeach
            <div class="flex justify-between text-sm py-1 text-indigo-700"><span>{{ __('Net Result (current period)') }}</span><span>{{ number_format($netResult, 2) }}</span></div>
            <div class="flex justify-between text-sm font-bold border-t mt-2 pt-2"><span>{{ __('Total Liabilities & Equity') }}</span><span>{{ number_format($totalLiab + $totalEquity, 2) }} {{ $currency }}</span></div>
        </div>
    </div>

    <div class="px-4 py-3 rounded-lg text-sm {{ $balanced ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
        {{ $balanced ? __('✓ Balanced: Assets = Liabilities + Equity') : __('Assets and Liabilities+Equity differ — usually pending year-end closing of the net result into retained earnings.') }}
    </div>
</div>
@endsection
