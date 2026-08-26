@extends('layouts.admin')

@section('title', __('Account Ledger'))
@section('breadcrumb', __('Accounting > Reports > Account Ledger'))

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ $account->account_code }} — {{ $account->account_name }}</h3>
        <a href="{{ route('admin.accounting-reports.general-ledger') }}" class="text-sm text-blue-600 hover:underline">{{ __('← General Ledger') }}</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Entry #') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Description') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Debit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Credit') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <tr class="bg-gray-50"><td colspan="5" class="px-4 py-2 text-sm font-medium text-right">{{ __('Opening Balance') }}</td><td class="px-4 py-2 text-sm text-right font-semibold">{{ number_format($opening, 2) }}</td></tr>
                @forelse($rows as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2.5 text-sm text-gray-600">{{ $r->date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2.5 text-sm font-mono text-gray-700">{{ $r->entry_number }}</td>
                    <td class="px-4 py-2.5 text-sm text-gray-700">{{ $r->description }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ $r->debit > 0 ? number_format($r->debit, 2) : '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-right">{{ $r->credit > 0 ? number_format($r->credit, 2) : '' }}</td>
                    <td class="px-4 py-2.5 text-sm text-right font-medium">{{ number_format($r->balance, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">{{ __('No posted movements in this period.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
