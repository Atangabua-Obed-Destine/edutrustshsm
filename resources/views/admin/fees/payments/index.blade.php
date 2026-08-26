@extends('layouts.admin')

@section('title', __('Payments'))
@section('breadcrumb', __('Fees > Payments'))

@section('content')
<div class="space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h2 class="text-xl font-bold text-gray-800">{{ __('Payments') }}</h2>
        <a href="{{ route('admin.payments.create') }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition inline-flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Record Payment') }}
        </a>
    </div>

    {{-- Search --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by receipt number, student name or ID...') }}"
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Search') }}</button>
        </form>
    </div>

    {{-- Payments Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 text-left">{{ __('Receipt #') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Student') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Class') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Amount (XAF)') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Method') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Date') }}</th>
                        <th class="px-4 py-3 text-left">{{ __('Received By') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-blue-600 font-medium">{{ $payment->receipt_number }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $payment->enrollment->student->full_name }}</div>
                            <div class="text-xs text-gray-400">{{ $payment->enrollment->student->student_id }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $payment->enrollment->classSection?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-green-700">{{ number_format($payment->amount) }}</td>
                        <td class="px-4 py-3 text-gray-600 capitalize text-xs">{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $payment->payment_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $payment->receivedBy?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                            <p class="font-medium">{{ __('No payments recorded yet.') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">{{ $payments->links() }}</div>
        @endif
    </div>
</div>
@endsection
