@extends('layouts.admin')

@section('title', __('Fund Transfers'))
@section('breadcrumb', __('Payment Accounts > Fund Transfers'))

@php($currency = \App\Models\SchoolSetting::current()->currency ?? 'CFA')

@section('content')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-700">{{ __('Fund Transfers') }}</h3>
        <a href="{{ route('admin.payment-account-transfer.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium">+ {{ __('New Transfer') }}</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('From Account') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('To Account') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Note') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('Created By') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($transfers as $i => $t)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $transfers->firstItem() + $i }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $t->transfer_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $t->fromAccount?->title }}</td>
                    <td class="px-4 py-3 text-sm text-gray-900">{{ $t->toAccount?->title }}</td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-blue-700">{{ number_format($t->amount, 2) }} {{ $currency }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $t->note ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $t->createdBy?->full_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('admin.payment-account-transfer.destroy', $t) }}" class="inline" onsubmit="return confirm('{{ __('Reverse and delete this transfer?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ __('No transfers found') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $transfers->links() }}</div>
</div>
@endsection
