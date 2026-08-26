@extends('layouts.admin')

@section('title', __('Payment Accounts'))
@section('breadcrumb', __('Payment Accounts'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    @if($unlinkedCount > 0)
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center justify-between">
        <span>{{ __('Total :count records not linked with any account.', ['count' => $unlinkedCount]) }}</span>
        <a href="{{ route('admin.payment-account-report.unlinked') }}" class="text-sm bg-white border border-red-300 px-3 py-1 rounded hover:bg-red-100">{{ __('View Details') }}</a>
    </div>
    @endif

    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Payment Accounts') }}</h3>
        <a href="{{ route('admin.payment-account.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('Add Payment Account') }}</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account Title') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account Number') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account Type') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Opening Balance') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Current Balance') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($accounts as $i => $account)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $i + 1 }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $account->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $account->account_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $account->accountType?->title }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ number_format($account->opening_balance, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">{{ number_format($account->current_balance, 2) }}</td>
                    <td class="px-4 py-3">
                        @if($account->status)
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">{{ __('Active') }}</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap text-sm font-medium">
                        <a href="{{ route('admin.payment-account.account-book', $account) }}" class="text-slate-700 hover:text-slate-900">{{ __('Book') }}</a>
                        <a href="{{ route('admin.payment-account.deposit-form', $account) }}" class="text-green-600 hover:text-green-800">{{ __('Deposit') }}</a>
                        <a href="{{ route('admin.payment-account.withdraw-form', $account) }}" class="text-amber-600 hover:text-amber-800">{{ __('Withdraw') }}</a>
                        <a href="{{ route('admin.payment-account.edit', $account) }}" class="text-blue-600 hover:text-blue-800">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.payment-account.destroy', $account) }}" class="inline" onsubmit="return confirm('{{ __('Delete this account?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No data found') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="5" class="px-4 py-3 text-sm font-semibold text-gray-700 text-right">{{ __('Total Balance:') }}</td>
                    <td class="px-4 py-3 text-sm font-bold text-blue-700 text-right">{{ number_format($totalBalance, 2) }} {{ $currency }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
