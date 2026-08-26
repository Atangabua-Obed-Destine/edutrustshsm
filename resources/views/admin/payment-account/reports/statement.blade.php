@extends('layouts.admin')

@section('title', __('Account Statement'))
@section('breadcrumb', __('Payment Accounts > Reports > Statement'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Statement') }}: {{ $account->title }}</h3>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
            <input type="date" name="date_from" value="{{ $from }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
            <input type="date" name="date_to" value="{{ $to }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Opening Balance') }}</p><p class="text-xl font-bold text-gray-800">{{ number_format($opening, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Period Credit') }}</p><p class="text-xl font-bold text-green-700">{{ number_format($periodCredit, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Period Debit') }}</p><p class="text-xl font-bold text-red-700">{{ number_format($periodDebit, 2) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 p-4"><p class="text-xs text-gray-500">{{ __('Closing Balance') }}</p><p class="text-xl font-bold text-blue-700">{{ number_format($closing, 2) }}</p></div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Credit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($transactions as $txn)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn->transaction_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $txn->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ $txn->transaction_type === 'debit' ? number_format($txn->amount, 2) : '' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-700">{{ $txn->transaction_type === 'credit' ? number_format($txn->amount, 2) : '' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($txn->balance_after, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No transactions in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
