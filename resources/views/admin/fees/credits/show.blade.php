@extends('layouts.admin')

@section('title', __('Student Credit'))
@section('breadcrumb', __('Fees > Student Credits'))

@section('content')
@php
    $currency = \App\Models\SchoolSetting::current()?->currency ?? 'FCFA';
    $student = $credit->enrollment?->student;
    $tones = [
        'requested' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-blue-100 text-blue-800',
        'rejected' => 'bg-gray-100 text-gray-600',
        'processed' => 'bg-emerald-100 text-emerald-800',
    ];
@endphp

<div class="space-y-6 max-w-5xl">
    <div>
        <a href="{{ route('admin.student-credits.index') }}" class="text-xs text-gray-400 hover:text-gray-600">&larr; {{ __('Student Credits') }}</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ $student?->full_name ?? '—' }}</h1>
        <p class="text-sm text-gray-500">
            {{ $student?->student_id }} · {{ $credit->enrollment?->classSection?->name ?? '—' }}
            · {{ __(ucfirst(str_replace('_', ' ', $credit->source))) }}
            @if($credit->payment) · {{ __('from receipt :r', ['r' => $credit->payment->receipt_number]) }} @endif
        </p>
    </div>

    @if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm text-emerald-700 font-medium">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 font-medium">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <ul class="text-sm text-red-700 list-disc list-inside">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @foreach([
            [__('Amount'), $credit->amount, 'text-gray-900'],
            [__('Applied to fees'), $credit->used_amount, 'text-gray-900'],
            [__('Refunded'), $credit->refunded_amount, 'text-gray-900'],
            [__('Available'), $credit->balance, (float) $credit->balance > 0 ? 'text-emerald-700' : 'text-gray-400'],
        ] as [$label, $value, $tone])
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">{{ $label }}</p>
            <p class="text-xl font-bold {{ $tone }} mt-1">{{ number_format((float) $value, 0, '.', ' ') }}</p>
            <p class="text-xs text-gray-400">{{ $currency }}</p>
        </div>
        @endforeach
    </div>

    @can('student-credit.request-refund')
    @if($refundable > 0)
    <details class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <summary class="text-sm font-semibold text-gray-700 cursor-pointer">{{ __('Request a refund') }}</summary>
        <form method="POST" action="{{ route('admin.student-credits.refunds.request', $credit) }}" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Amount') }}</label>
                <input type="number" name="amount" step="0.01" min="0.01" max="{{ $refundable }}" value="{{ old('amount', $refundable) }}" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <p class="text-xs text-gray-400 mt-1">{{ __('Up to :amount', ['amount' => number_format($refundable, 0, '.', ' ')]) }}</p>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Reason') }}</label>
                <input type="text" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="500"
                       placeholder="{{ __('e.g. Student left the school') }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
            </div>
            <div class="md:col-span-3 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Request Refund') }}</button>
            </div>
        </form>
    </details>
    @endif
    @endcan

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <h2 class="text-sm font-semibold text-gray-700 px-5 pt-5">{{ __('Refunds') }}</h2>
        <div class="divide-y divide-gray-100 mt-3">
            @forelse($credit->refunds as $refund)
            <div class="px-5 py-4 space-y-3">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-lg font-semibold text-gray-900">{{ number_format((float) $refund->amount, 0, '.', ' ') }} {{ $currency }}</p>
                        <p class="text-sm text-gray-600">{{ $refund->reason }}</p>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ __('Requested :date by :user', ['date' => $refund->requested_at?->format('d/m/Y H:i') ?? '—', 'user' => $refund->requestedBy?->full_name ?? '—']) }}
                            @if($refund->reviewed_at)
                            · {{ $refund->status === 'rejected' ? __('Rejected :date by :user', ['date' => $refund->reviewed_at->format('d/m/Y H:i'), 'user' => $refund->reviewedBy?->full_name ?? '—']) : __('Approved :date by :user', ['date' => $refund->reviewed_at->format('d/m/Y H:i'), 'user' => $refund->reviewedBy?->full_name ?? '—']) }}
                            @endif
                            @if($refund->processed_at)
                            · {{ __('Paid :date by :user', ['date' => $refund->processed_at->format('d/m/Y H:i'), 'user' => $refund->processedBy?->full_name ?? '—']) }}
                            @endif
                        </p>
                        @if($refund->rejection_reason)
                        <p class="text-xs text-red-600 mt-1">{{ $refund->rejection_reason }}</p>
                        @endif
                        @if($refund->status === 'processed')
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __(\App\Models\StudentCreditRefund::METHODS[$refund->method] ?? $refund->method) }}
                            @if($refund->reference) · {{ $refund->reference }} @endif
                            @if($refund->paymentAccount) · {{ $refund->paymentAccount->title }} @endif
                        </p>
                        @endif
                    </div>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $tones[$refund->status] ?? '' }}">{{ __(ucfirst($refund->status)) }}</span>
                </div>

                @if($refund->status === 'requested')
                @can('student-credit.approve-refund')
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.student-credits.refunds.approve', $refund) }}">@csrf
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-sm">{{ __('Approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.student-credits.refunds.reject', $refund) }}" class="flex gap-2">@csrf
                        <input type="text" name="rejection_reason" required minlength="5" maxlength="500" placeholder="{{ __('Why is it rejected?') }}"
                               class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <button type="submit" class="border border-red-200 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm">{{ __('Reject') }}</button>
                    </form>
                </div>
                @endcan
                @endif

                @if($refund->status === 'approved')
                @can('student-credit.process-refund')
                <form method="POST" action="{{ route('admin.student-credits.refunds.process', $refund) }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-gray-50 rounded-lg p-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Method') }}</label>
                        <select name="method" required style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            @foreach($methods as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Paid From') }}</label>
                        <select name="payment_account_id" style="appearance:auto;-webkit-appearance:menulist;" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            <option value="">{{ __('Not from a payment account') }}</option>
                            @foreach($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->title }} ({{ number_format((float) $account->current_balance, 0, '.', ' ') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('Reference') }}</label>
                        <input type="text" name="reference" maxlength="100" placeholder="{{ __('Cheque or transfer number') }}" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium"
                                onclick="return confirm('{{ __('Pay this refund out? The money will leave the chosen account.') }}')">{{ __('Pay Refund') }}</button>
                    </div>
                    <div class="md:col-span-4">
                        <input type="text" name="note" maxlength="500" placeholder="{{ __('Note (optional)') }}" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                </form>
                @endcan
                @can('student-credit.approve-refund')
                <form method="POST" action="{{ route('admin.student-credits.refunds.reject', $refund) }}" class="flex gap-2">@csrf
                    <input type="text" name="rejection_reason" required minlength="5" maxlength="500" placeholder="{{ __('Cancel reason') }}"
                           class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <button type="submit" class="border border-red-200 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm">{{ __('Reject') }}</button>
                </form>
                @endcan
                @endif
            </div>
            @empty
            <p class="px-5 py-8 text-center text-sm text-gray-400">{{ __('No refunds against this credit.') }}</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
