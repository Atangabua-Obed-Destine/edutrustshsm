@extends('layouts.admin')

@section('title', __('Student Fees') . ' - ' . $student->full_name)
@section('breadcrumb', __('Fees') . ' > ' . __('Student') . ' > ' . $student->student_id)

@section('content')
<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold text-gray-800">{{ $student->full_name }}</h2>
            <p class="text-sm text-gray-500">{{ $student->student_id }} &mdash; {{ $enrollment?->classSection?->name ?? __('Not enrolled') }}</p>
        </div>
        @if($enrollment)
        <a href="{{ route('admin.payments.create', ['student_id' => $student->id]) }}" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            {{ __('Record Payment') }}
        </a>
        @endif
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('Total Fees') }}</p>
            <p class="text-2xl font-bold text-gray-800 font-mono mt-1">{{ number_format($totalFees) }} <span class="text-sm text-gray-400">XAF</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('Total Paid') }}</p>
            <p class="text-2xl font-bold text-green-700 font-mono mt-1">{{ number_format($totalPaid) }} <span class="text-sm text-gray-400">XAF</span></p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <p class="text-xs text-gray-500 uppercase">{{ __('Balance') }}</p>
            <p class="text-2xl font-bold {{ $totalBalance > 0 ? 'text-red-600' : 'text-green-700' }} font-mono mt-1">{{ number_format($totalBalance) }} <span class="text-sm text-gray-400">XAF</span></p>
        </div>
    </div>

    {{-- Fee Breakdown --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h4 class="font-semibold text-gray-700 text-sm">{{ __('Fee Breakdown') }}</h4>
        </div>
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">{{ __('Category') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Original') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Discount') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Net') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Paid') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Balance') }}</th>
                    <th class="px-4 py-2 text-center">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($fees as $fee)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-medium text-gray-700">{{ $fee->feeCategory->name }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ number_format($fee->original_amount) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-orange-600">{{ $fee->discount_amount > 0 ? number_format($fee->discount_amount) : '—' }}</td>
                    <td class="px-4 py-2 text-right font-mono">{{ number_format($fee->net_amount) }}</td>
                    <td class="px-4 py-2 text-right font-mono text-green-600">{{ number_format($fee->paid_amount) }}</td>
                    <td class="px-4 py-2 text-right font-mono {{ $fee->balance > 0 ? 'text-red-600 font-semibold' : 'text-green-600' }}">{{ number_format($fee->balance) }}</td>
                    <td class="px-4 py-2 text-center">
                        @php
                            $sc = ['paid' => 'green', 'partial' => 'yellow', 'unpaid' => 'red', 'waived' => 'blue'];
                            $c = $sc[$fee->status] ?? 'gray';
                        @endphp
                        <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700 capitalize">{{ $fee->status }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">{{ __('No fees assigned.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Payment History --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h4 class="font-semibold text-gray-700 text-sm">{{ __('Payment History') }}</h4>
        </div>
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left">{{ __('Receipt #') }}</th>
                    <th class="px-4 py-2 text-right">{{ __('Amount (XAF)') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Method') }}</th>
                    <th class="px-4 py-2 text-left">{{ __('Date') }}</th>
                    <th class="px-4 py-2 text-center">{{ __('Action') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($payments as $p)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 font-mono text-xs text-blue-600">{{ $p->receipt_number }}</td>
                    <td class="px-4 py-2 text-right font-mono font-semibold text-green-700">{{ number_format($p->amount) }}</td>
                    <td class="px-4 py-2 capitalize text-xs">{{ str_replace('_', ' ', $p->payment_method) }}</td>
                    <td class="px-4 py-2">{{ $p->payment_date->format('d M Y') }}</td>
                    <td class="px-4 py-2 text-center">
                        <a href="{{ route('admin.payments.show', $p) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('View') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('No payments recorded.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
