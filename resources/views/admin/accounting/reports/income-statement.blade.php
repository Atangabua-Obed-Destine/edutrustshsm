@extends('layouts.admin')

@section('title', __('Income Statement'))
@section('breadcrumb', __('Accounting > Reports > Income Statement'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'FCFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Income Statement') }} <span class="text-sm text-gray-400">(Compte de Résultat)</span></h3>
        <form method="GET" class="flex items-end gap-2">
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('From') }}</label><input type="date" name="start_date" value="{{ $from }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <div><label class="block text-xs text-gray-500 mb-1">{{ __('To') }}</label><input type="date" name="end_date" value="{{ $to }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
            <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('View') }}</button>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h4 class="font-semibold text-gray-700 mb-3 border-b pb-2">{{ __('Revenue') }} (Class 7)</h4>
        @forelse($revenue as $row)
        <div class="flex justify-between text-sm py-1"><span class="text-gray-700"><span class="font-mono text-gray-400">{{ $row->account->account_code }}</span> {{ $row->account->account_name }}</span><span>{{ number_format($row->balance, 2) }}</span></div>
        @empty<p class="text-sm text-gray-500">{{ __('No revenue posted.') }}</p>@endforelse
        <div class="flex justify-between text-sm font-bold border-t mt-2 pt-2 text-green-700"><span>{{ __('Total Revenue') }}</span><span>{{ number_format($totalRevenue, 2) }}</span></div>

        <h4 class="font-semibold text-gray-700 mb-3 mt-5 border-b pb-2">{{ __('Expenses') }} (Class 6)</h4>
        @forelse($expense as $row)
        <div class="flex justify-between text-sm py-1"><span class="text-gray-700"><span class="font-mono text-gray-400">{{ $row->account->account_code }}</span> {{ $row->account->account_name }}</span><span>{{ number_format($row->balance, 2) }}</span></div>
        @empty<p class="text-sm text-gray-500">{{ __('No expenses posted.') }}</p>@endforelse
        <div class="flex justify-between text-sm font-bold border-t mt-2 pt-2 text-red-700"><span>{{ __('Total Expenses') }}</span><span>{{ number_format($totalExpense, 2) }}</span></div>

        <div class="flex justify-between text-base font-bold border-t-2 border-gray-300 mt-4 pt-3">
            <span>{{ __('Net Result') }}</span>
            <span class="{{ $netResult >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($netResult, 2) }} {{ $currency }}</span>
        </div>
    </div>
</div>
@endsection
