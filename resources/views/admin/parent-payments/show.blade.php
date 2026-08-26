@extends('layouts.admin')
@section('title', __('Review Submission'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $isImage = \Illuminate\Support\Str::endsWith(strtolower($submission->receipt_path), ['.jpg', '.jpeg', '.png']);
    $statusColors = [
        'pending' => ['bg-amber-100', 'text-amber-700'],
        'approved' => ['bg-green-100', 'text-green-700'],
        'rejected' => ['bg-red-100', 'text-red-700'],
    ];
    $sc = $statusColors[$submission->status] ?? ['bg-gray-100', 'text-gray-600'];
@endphp

<div class="max-w-5xl mx-auto" x-data="{ showReject: false }">
    @if(session('success'))
        <div class="mb-5 bg-green-50 border border-green-200 rounded-lg p-3 text-sm text-green-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <a href="{{ route('admin.parent-payments.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ __('Back to submissions') }}
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {{-- Left: details + actions --}}
        <div class="lg:col-span-3 space-y-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-gray-800">{{ __('Submission') }} #{{ $submission->id }}</h3>
                    <span class="px-3 py-1 rounded-full {{ $sc[0] }} {{ $sc[1] }} text-xs font-semibold capitalize">{{ $submission->status }}</span>
                </div>

                <dl class="space-y-3">
                    @php
                        $rows = [
                            __('Parent') => $submission->guardian->display_name ?? '—',
                            __('Contact') => ($submission->guardian->primary_phone ?? '') . ' ' . ($submission->guardian->primary_email ?? ''),
                            __('Child') => $submission->enrollment->student->full_name ?? '—',
                            __('Class') => $submission->enrollment->classSection->name ?? '—',
                            __('Paying For') => $submission->studentFee ? ($submission->studentFee->feeCategory->name ?? __('Specific fee')) : __('Any outstanding fee (FIFO)'),
                            __('Amount') => number_format($submission->amount, 0) . ' ' . $currency,
                            __('Method') => ucfirst(str_replace('_', ' ', $submission->payment_method)),
                            __('Payment Date') => $submission->payment_date?->format('d M Y'),
                            __('Reference') => $submission->transaction_ref ?: '—',
                            __('Bank') => $submission->bank_name ?: '—',
                        ];
                    @endphp
                    @foreach($rows as $label => $value)
                        <div class="flex justify-between border-b border-gray-50 pb-2">
                            <dt class="text-sm text-gray-500">{{ $label }}</dt>
                            <dd class="text-sm font-medium text-gray-800 text-right">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if($submission->notes)
                    <div class="mt-4 bg-gray-50 rounded-lg p-3">
                        <p class="text-xs text-gray-400 uppercase">{{ __('Parent note') }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ $submission->notes }}</p>
                    </div>
                @endif
            </div>

            {{-- Outstanding fees preview --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('Current Fee Status') }}</h3>
                @if($fees->isEmpty())
                    <p class="text-sm text-gray-400">{{ __('No fees on this enrollment.') }}</p>
                @else
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-50">
                            @foreach($fees as $fee)
                                <tr>
                                    <td class="py-2 text-gray-700">{{ $fee->feeCategory->name ?? '—' }}</td>
                                    <td class="py-2 text-right text-gray-500">{{ number_format($fee->paid_amount, 0) }} / {{ number_format($fee->net_amount, 0) }}</td>
                                    <td class="py-2 text-right font-semibold {{ $fee->balance > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($fee->balance, 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="text-xs text-gray-400 mt-3">{{ __('On approval, the amount is allocated to outstanding fees oldest-first.') }}</p>
                @endif
            </div>

            {{-- Actions --}}
            @if($submission->isPending())
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">{{ __('Decision') }}</h3>
                    <div class="flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('admin.parent-payments.approve', $submission) }}"
                              onsubmit="return confirm('{{ __('Approve this payment and record an official receipt?') }}')">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 bg-green-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-green-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ __('Approve & Record Payment') }}
                            </button>
                        </form>
                        <button type="button" @click="showReject = !showReject" class="inline-flex items-center gap-2 bg-red-50 text-red-600 px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-red-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            {{ __('Reject') }}
                        </button>
                    </div>

                    <div x-show="showReject" x-cloak class="mt-4 border-t border-gray-100 pt-4">
                        <form method="POST" action="{{ route('admin.parent-payments.reject', $submission) }}">
                            @csrf
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reason for rejection') }} <span class="text-red-500">*</span></label>
                            <textarea name="review_notes" rows="2" required placeholder="{{ __('e.g. Receipt unclear, amount mismatch...') }}"
                                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-400 outline-none"></textarea>
                            <button type="submit" class="mt-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-red-700">{{ __('Confirm Rejection') }}</button>
                        </form>
                    </div>
                </div>
            @elseif($submission->status === 'approved' && $submission->payment)
                <div class="bg-green-50 border border-green-200 rounded-xl p-5">
                    <p class="text-sm text-green-800">{{ __('Approved by') }} {{ $submission->reviewedBy->full_name ?? '—' }} {{ __('on') }} {{ $submission->reviewed_at?->format('d M Y, H:i') }}.
                    {{ __('Receipt') }}: <a href="{{ route('admin.payments.show', $submission->payment) }}" class="font-mono font-bold underline">{{ $submission->payment->receipt_number }}</a></p>
                </div>
            @elseif($submission->status === 'rejected')
                <div class="bg-red-50 border border-red-200 rounded-xl p-5">
                    <p class="text-sm text-red-800"><strong>{{ __('Rejected') }}</strong> {{ __('by') }} {{ $submission->reviewedBy->full_name ?? '—' }}.</p>
                    @if($submission->review_notes)<p class="text-sm text-red-700 mt-1">{{ $submission->review_notes }}</p>@endif
                </div>
            @endif
        </div>

        {{-- Right: receipt preview --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:sticky lg:top-24">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ __('Uploaded Receipt') }}</h3>
                @if($isImage)
                    <a href="{{ asset('storage/' . $submission->receipt_path) }}" target="_blank">
                        <img src="{{ asset('storage/' . $submission->receipt_path) }}" alt="receipt" class="w-full rounded-lg border border-gray-200">
                    </a>
                    <p class="text-xs text-gray-400 mt-2 text-center">{{ __('Click to open full size') }}</p>
                @else
                    <a href="{{ asset('storage/' . $submission->receipt_path) }}" target="_blank"
                       class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-lg p-4 hover:bg-gray-100">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span class="text-sm font-medium text-gray-700">{{ __('Open PDF Receipt') }}</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
<style>[x-cloak]{display:none!important;}</style>
@endsection
