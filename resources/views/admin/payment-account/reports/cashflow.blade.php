@extends('layouts.admin')

@section('title', __('Cash Flow Report'))
@section('breadcrumb', __('Payment Accounts > Reports > Cash Flow'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Cash Flow Report') }}</h3>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Account') }}</label>
            <select name="account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All Accounts') }}</option>
                @foreach($accounts as $a)
                    <option value="{{ $a->id }}" {{ (string) request('account_id') === (string) $a->id ? 'selected' : '' }}>{{ $a->title }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('From') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('To') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Type') }}</label>
            <select name="transaction_type" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">{{ __('All Types') }}</option>
                <option value="credit" {{ request('transaction_type') === 'credit' ? 'selected' : '' }}>{{ __('Credit') }}</option>
                <option value="debit" {{ request('transaction_type') === 'debit' ? 'selected' : '' }}>{{ __('Debit') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Method') }}</label>
            <input type="text" name="payment_method" value="{{ request('payment_method') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
            <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-green-600 text-white rounded-xl p-5">
            <p class="text-sm opacity-90">{{ __('Total Credit') }}</p>
            <p class="text-2xl font-bold">{{ number_format($summary['total_credit'], 2) }}</p>
            <p class="text-xs opacity-75">{{ __('Money In') }}</p>
        </div>
        <div class="bg-red-500 text-white rounded-xl p-5">
            <p class="text-sm opacity-90">{{ __('Total Debit') }}</p>
            <p class="text-2xl font-bold">{{ number_format($summary['total_debit'], 2) }}</p>
            <p class="text-xs opacity-75">{{ __('Money Out') }}</p>
        </div>
        <div class="bg-cyan-500 text-white rounded-xl p-5">
            <p class="text-sm opacity-90">{{ __('Net Flow') }}</p>
            <p class="text-2xl font-bold">{{ number_format($summary['net_flow'], 2) }}</p>
            <p class="text-xs opacity-75">{{ __('Credit - Debit') }}</p>
        </div>
        <div class="bg-blue-600 text-white rounded-xl p-5">
            <p class="text-sm opacity-90">{{ __('Total Balance') }}</p>
            <p class="text-2xl font-bold">{{ number_format($summary['total_balance'], 2) }}</p>
            <p class="text-xs opacity-75">{{ __('All Accounts') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Method') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Credit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($transactions as $txn)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn->transaction_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $txn->paymentAccount?->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $txn->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn->payment_method ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ $txn->transaction_type === 'debit' ? number_format($txn->amount, 2) : '' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-700">{{ $txn->transaction_type === 'credit' ? number_format($txn->amount, 2) : '' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format($txn->balance_after, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No data available') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
@endsection
