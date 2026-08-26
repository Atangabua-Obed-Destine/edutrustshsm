@extends('layouts.admin')

@section('title', __('Accounts Summary'))
@section('breadcrumb', __('Payment Accounts > Reports > Summary'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Accounts Summary') }}</h3>

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

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Type') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Credit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Net') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Txns') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($rows as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                        <a href="{{ route('admin.payment-account-report.statement', $r->account) }}" class="text-blue-600 hover:underline">{{ $r->account->title }}</a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $r->account->accountType?->title }}</td>
                    <td class="px-4 py-3 text-sm text-right text-green-700">{{ number_format($r->credit, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-red-700">{{ number_format($r->debit, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right {{ $r->net < 0 ? 'text-red-700' : 'text-gray-900' }}">{{ number_format($r->net, 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ $r->count }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">{{ number_format($r->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ __('No accounts.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="2" class="px-4 py-3 text-sm font-semibold text-gray-700 text-right">{{ __('Grand Total') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-green-700">{{ number_format($rows->sum('credit'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-red-700">{{ number_format($rows->sum('debit'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold">{{ number_format($rows->sum('net'), 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold">{{ $rows->sum('count') }}</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-blue-700">{{ number_format($rows->sum('balance'), 2) }} {{ $currency }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
