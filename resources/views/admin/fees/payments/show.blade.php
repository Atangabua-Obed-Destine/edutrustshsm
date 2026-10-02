@extends('layouts.admin')

@section('title', __('Receipt:') . ' ' . $payment->receipt_number)
@section('breadcrumb', __('Fees > Payments') . ' > ' . $payment->receipt_number)

@section('content')
<div class="max-w-2xl mx-auto">
    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-4 text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif

    @if($payment->isReversed())
    {{-- Kept on file, but no longer money received. --}}
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
        <p class="text-sm font-semibold text-red-800">{{ __('This payment has been reversed') }}</p>
        <p class="text-sm text-red-700 mt-1">{{ $payment->reversal_reason }}</p>
        <p class="text-xs text-red-600 mt-1">
            {{ __('Reversed on :date by :user', ['date' => $payment->reversed_at?->format('d M Y, H:i') ?? '—', 'user' => $payment->reversedBy?->full_name ?? '—']) }}
        </p>
    </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 {{ $payment->isReversed() ? 'opacity-75' : '' }}" id="receipt">
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

    @can('payment.reverse')
    @if($payment->verification_status === 'verified')
    <details class="mt-6 bg-white rounded-xl border border-red-200 p-4">
        <summary class="text-sm font-medium text-red-700 cursor-pointer">{{ __('Reverse this payment') }}</summary>
        <form method="POST" action="{{ route('admin.payments.reverse', $payment) }}" class="mt-3 space-y-3"
              onsubmit="return confirm('{{ __('Reverse this payment? The fees, payment plan, credit, payment account and ledger will all be put back.') }}')">
            @csrf
            <p class="text-xs text-gray-500">{{ __('Use this for a payment recorded in error. The receipt stays on file, marked reversed, and every balance it changed is put back.') }}</p>
            <textarea name="reason" rows="3" required minlength="5" maxlength="500"
                      placeholder="{{ __('Why is this payment being reversed?') }}"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">{{ old('reason') }}</textarea>
            @error('reason')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Reverse Payment') }}</button>
        </form>
    </details>
    @endif
    @endcan
</div>
@endsection
