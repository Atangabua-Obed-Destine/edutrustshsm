@extends('layouts.admin')

@section('title', __('Account Book'))
@section('breadcrumb', __('Payment Accounts > Account Book'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">{{ $account->title }}</h3>
            <p class="text-sm text-gray-500">{{ $account->accountType?->title }} · {{ __('Balance') }}:
                <span class="font-bold text-blue-700">{{ number_format($account->current_balance, 2) }} {{ $currency }}</span>
            </p>
        </div>
        <div class="space-x-2">
            <a href="{{ route('admin.payment-account.deposit-form', $account) }}" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm">{{ __('Deposit') }}</a>
            <a href="{{ route('admin.payment-account.withdraw-form', $account) }}" class="bg-amber-600 hover:bg-amber-700 text-white px-3 py-2 rounded-lg text-sm">{{ __('Withdraw') }}</a>
            <form method="POST" action="{{ route('admin.payment-account.recompute', $account) }}" class="inline" onsubmit="return confirm('{{ __('Recompute balance from transactions?') }}')">
                @csrf
                <button type="submit" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-3 py-2 rounded-lg text-sm">{{ __('Recompute') }}</button>
            </form>
            <a href="{{ route('admin.payment-account.index') }}" class="text-sm text-gray-600 hover:text-gray-800 px-2">{{ __('Back') }}</a>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Source') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Method') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Credit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Balance') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($transactions as $txn)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn->transaction_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $txn->title ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600">{{ $txn->reference_type ? str_replace('_', ' ', $txn->reference_type) : 'manual' }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $txn->payment_method ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ $txn->transaction_type === 'debit' ? number_format($txn->amount, 2) : '' }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-700">{{ $txn->transaction_type === 'credit' ? number_format($txn->amount, 2) : '' }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-gray-900">{{ number_format($txn->balance_after, 2) }}</td>
                    <td class="px-4 py-3 text-right">
                        @unless($txn->isLinked())
                        <form method="POST" action="{{ route('admin.payment-account.transaction.destroy', $txn) }}" class="inline" onsubmit="return confirm('{{ __('Delete this transaction?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">{{ __('Delete') }}</button>
                        </form>
                        @else
                        <span class="text-xs text-gray-400">{{ __('linked') }}</span>
                        @endunless
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No transactions yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transactions->links() }}</div>
</div>
@endsection
