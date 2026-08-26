@extends('layouts.admin')

@section('title', __('Receipt:') . ' ' . $payment->receipt_number)
@section('breadcrumb', __('Fees > Payments') . ' > ' . $payment->receipt_number)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6" id="receipt">
        {{-- Receipt Header --}}
        <div class="text-center border-b pb-4 mb-4">
            @php $__school = \App\Models\SchoolSetting::current(); @endphp
            <h2 class="text-lg font-bold text-gray-800">{{ $__school->school_name ?? __('School Name') }}</h2>
            <p class="text-xs text-gray-500 mt-1">{{ __('Payment Receipt') }}</p>
            <p class="text-sm font-mono font-bold text-blue-700 mt-2">{{ $payment->receipt_number }}</p>
        </div>

        {{-- Student Info --}}
        <div class="grid grid-cols-2 gap-3 text-sm mb-4">
            <div>
                <p class="text-xs text-gray-500">{{ __('Student Name') }}</p>
                <p class="font-medium">{{ $payment->enrollment->student->full_name }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('Student ID') }}</p>
                <p class="font-mono">{{ $payment->enrollment->student->student_id }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('Class') }}</p>
                <p class="font-medium">{{ $payment->enrollment->classSection?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('Session') }}</p>
                <p class="font-medium">{{ $payment->enrollment->academicSession->name }}</p>
            </div>
        </div>

        {{-- Payment Details --}}
        <div class="bg-gray-50 rounded-lg p-4 mb-4">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-500">{{ __('Amount Paid') }}</p>
                    <p class="text-xl font-bold text-green-700 font-mono">{{ number_format($payment->amount) }} XAF</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('Payment Date') }}</p>
                    <p class="font-medium">{{ $payment->payment_date->format('d M Y') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ __('Payment Method') }}</p>
                    <p class="font-medium capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</p>
                </div>
                @if($payment->transaction_ref)
                <div>
                    <p class="text-xs text-gray-500">{{ __('Transaction Ref') }}</p>
                    <p class="font-mono text-xs">{{ $payment->transaction_ref }}</p>
                </div>
                @endif
                @if($payment->payer_name)
                <div>
                    <p class="text-xs text-gray-500">{{ __('Payer') }}</p>
                    <p>{{ $payment->payer_name }} {{ $payment->payer_phone ? "({$payment->payer_phone})" : '' }}</p>
                </div>
                @endif
                <div>
                    <p class="text-xs text-gray-500">{{ __('Received By') }}</p>
                    <p>{{ $payment->receivedBy?->full_name ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Allocations --}}
        @if($payment->allocations->count())
        <div class="mb-4">
            <h4 class="text-sm font-semibold text-gray-700 mb-2">{{ __('Payment Allocation') }}</h4>
            <table class="w-full text-sm">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">{{ __('Fee Category') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Amount (XAF)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($payment->allocations as $alloc)
                    <tr>
                        <td class="px-3 py-2">{{ $alloc->studentFee->feeCategory->name }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($alloc->amount) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($payment->notes)
        <div class="text-sm text-gray-500 border-t pt-3 mt-3">
            <strong>{{ __('Notes:') }}</strong> {{ $payment->notes }}
        </div>
        @endif

        <div class="text-center text-xs text-gray-400 mt-4 border-t pt-3">
            {{ __('Printed on') }} {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>

    <div class="flex justify-between mt-4">
        <a href="{{ route('admin.payments.index') }}" class="text-sm text-gray-600 hover:text-gray-800 transition">{{ __('Back to Payments') }}</a>
        <button onclick="window.print()" class="bg-[#1e293b] hover:bg-[#334155] text-white px-4 py-2 rounded-lg text-sm font-medium transition">
            {{ __('Print Receipt') }}
        </button>
    </div>
</div>
@endsection
