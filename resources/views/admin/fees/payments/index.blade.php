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

    {{-- Filters, sorting and paging redraw only this part (data-list-region). --}}
    <div class="space-y-4" data-list-region="payments">
    {{-- Search --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
        <form method="GET" class="flex gap-3">
            @if(request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="dir" value="{{ request('dir') }}">@endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by receipt number, student name or ID...') }}"
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 outline-none">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">{{ __('Search') }}</button>
        </form>
    </div>

    {{-- Payments Table --}}
    @php($canPrint = auth()->user()->can('fee-collection.receipt'))
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" data-bulk-scope>
        <x-list.toolbar :list="$list" :rows="$payments" />
        @if($canPrint)
        <x-list.bulk>
            <form method="GET" action="{{ route('admin.collect-fees.receipts') }}" target="_blank" data-bulk-form data-no-swap data-no-busy>
                <button type="submit">{{ __('Print receipts') }}</button>
            </form>
        </x-list.bulk>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        @if($canPrint)<th class="w-8 pl-4 py-3"><x-list.check-all /></th>@endif
                        <x-list.th :list="$list" key="receipt" class="px-4 py-3 text-left" />
                        <x-list.th :list="$list" key="student" class="px-4 py-3 text-left" />
                        <x-list.th :list="$list" key="class" class="px-4 py-3 text-left" />
                        <x-list.th :list="$list" key="amount" class="px-4 py-3 text-right" />
                        <x-list.th :list="$list" key="method" class="px-4 py-3 text-left" />
                        <x-list.th :list="$list" key="date" class="px-4 py-3 text-left" />
                        <x-list.th :list="$list" key="received_by" class="px-4 py-3 text-left" />
                        <th class="px-4 py-3 text-center">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($payments as $payment)
                    <tr class="hover:bg-gray-50">
                        @if($canPrint)<td class="pl-4 py-3"><x-list.check :value="$payment->id" :label="__('Tick receipt :r', ['r' => $payment->receipt_number])" /></td>@endif
                        <td class="px-4 py-3 font-mono text-xs text-blue-600 font-medium">
                            {{ $payment->receipt_number }}
                            @if($payment->isReversed())
                            <span class="ml-1 inline-block px-1.5 py-0.5 rounded bg-red-50 text-red-700 font-sans text-[10px] font-semibold uppercase">{{ __('Reversed') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $payment->enrollment->student->full_name }}</div>
                            <div class="text-xs text-gray-400">{{ $payment->enrollment->student->student_id }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $payment->enrollment->classSection?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono font-semibold {{ $payment->isReversed() ? 'text-gray-400 line-through' : 'text-green-700' }}">{{ number_format($payment->amount) }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $payment->method_label }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $payment->payment_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $payment->receivedBy?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('admin.payments.show', $payment) }}" class="text-blue-600 hover:text-blue-800 text-xs font-medium">{{ __('View') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $canPrint ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-400">
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
</div>
@endsection
