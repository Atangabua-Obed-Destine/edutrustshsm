@extends('layouts.admin')

@section('title', __('Trial Balance'))
@section('breadcrumb', __('Accounting > Reports > Trial Balance'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'FCFA')
@php($balanced = abs($totals['closing_debit'] - $totals['closing_credit']) < 0.01)

@section('content')
<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-700">{{ __('Trial Balance') }}</h3>

    <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap items-end gap-3">
        <div><label class="block text-xs text-gray-500 mb-1">{{ __('Start Date') }}</label><input type="date" name="start_date" value="{{ $from }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        <div><label class="block text-xs text-gray-500 mb-1">{{ __('End Date') }}</label><input type="date" name="end_date" value="{{ $to }}" class="px-3 py-2 border border-gray-300 rounded-lg text-sm"></div>
        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Filter') }}</button>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Code') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Account Name') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Period Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Period Credit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Closing Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Closing Credit') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($rows as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm font-mono text-gray-700">{{ $r->account->account_code }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-800">{{ $r->account->account_name }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ number_format($r->period_debit, 2) }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ number_format($r->period_credit, 2) }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ $r->closing_debit ? number_format($r->closing_debit, 2) : '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ $r->closing_credit ? number_format($r->closing_credit, 2) : '' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No posted entries.') }}</td></tr>
                @endforelse
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200 font-bold">
                <tr>
                    <td colspan="2" class="px-4 py-3 text-sm text-right">{{ __('Total') }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($totals['debit'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($totals['credit'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($totals['closing_debit'], 2) }}</td>
                    <td class="px-4 py-3 text-sm text-right">{{ number_format($totals['closing_credit'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="px-4 py-3 rounded-lg text-sm {{ $balanced ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' }}">
        {{ $balanced ? __('✓ Trial Balance Verified: Debits equal credits') : __('⚠ Out of balance — investigate.') }}
    </div>
</div>
@endsection
