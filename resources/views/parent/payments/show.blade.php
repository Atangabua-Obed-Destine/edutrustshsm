@extends('parent.layouts.app')
@section('title', __('Submission Details'))
@section('heading', __('Submission Details'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $statusColors = [
        'pending' => ['#fef9c3', '#ca8a04', __('Pending Review')],
        'approved' => ['#dcfce7', '#16a34a', __('Approved')],
        'rejected' => ['#fee2e2', '#dc2626', __('Rejected')],
    ];
    $sc = $statusColors[$submission->status] ?? ['#f1f5f9', '#475569', ucfirst($submission->status)];
    $isImage = \Illuminate\Support\Str::endsWith(strtolower($submission->receipt_path), ['.jpg', '.jpeg', '.png']);
@endphp

<div style="max-width:680px;">
    <a href="{{ route('parent.payments.index') }}" style="font-size:0.82rem; color:#64748b; display:inline-flex; align-items:center; gap:5px; margin-bottom:16px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        {{ __('Back to submissions') }}
    </a>

    {{-- Status banner --}}
    <div class="pp-card" style="padding:20px; margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase;">{{ __('Status') }}</div>
            <span style="display:inline-block; margin-top:6px; font-size:0.85rem; font-weight:700; padding:5px 14px; border-radius:99px; background:{{ $sc[0] }}; color:{{ $sc[1] }};">{{ $sc[2] }}</span>
        </div>
        <div style="text-align:right;">
            <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase;">{{ __('Amount') }}</div>
            <div style="font-size:1.4rem; font-weight:800; color:#0f172a;">{{ number_format($submission->amount, 0) }} <span style="font-size:0.8rem; color:#94a3b8;">{{ $currency }}</span></div>
        </div>
    </div>

    @if($submission->status === 'approved' && $submission->payment)
    <div class="pp-card" style="padding:14px 18px; margin-bottom:18px; background:#f0fdf4; border:1px solid #bbf7d0;">
        <p style="font-size:0.83rem; color:#166534;">
            <strong>{{ __('Recorded.') }}</strong> {{ __('Official receipt') }}: <span style="font-family:monospace; font-weight:700;">{{ $submission->payment->receipt_number }}</span>. {{ __('Your child’s fee balance has been updated.') }}
        </p>
    </div>
    @elseif($submission->status === 'rejected' && $submission->review_notes)
    <div class="pp-card" style="padding:14px 18px; margin-bottom:18px; background:#fef2f2; border:1px solid #fecaca;">
        <p style="font-size:0.78rem; color:#991b1b; text-transform:uppercase; font-weight:600; margin-bottom:4px;">{{ __('Reason for rejection') }}</p>
        <p style="font-size:0.85rem; color:#b91c1c;">{{ $submission->review_notes }}</p>
    </div>
    @endif

    {{-- Details --}}
    <div class="pp-card" style="padding:22px; margin-bottom:18px;">
        <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:14px;">{{ __('Payment Details') }}</h3>
        @php
            $rows = [
                __('Child') => ($submission->enrollment->student->full_name ?? '—'),
                __('For Fee') => $submission->studentFee ? ($submission->studentFee->feeCategory->name ?? __('Specific fee')) : __('General payment'),
                __('Payment Method') => ucfirst(str_replace('_', ' ', $submission->payment_method)),
                __('Payment Date') => $submission->payment_date?->format('d M Y'),
                __('Reference') => $submission->transaction_ref ?: '—',
                __('Bank') => $submission->bank_name ?: '—',
                __('Submitted') => $submission->created_at?->format('d M Y, H:i'),
            ];
        @endphp
        @foreach($rows as $label => $value)
            <div style="display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f8fafc;">
                <span style="font-size:0.82rem; color:#64748b;">{{ $label }}</span>
                <span style="font-size:0.82rem; color:#1e293b; font-weight:600; text-align:right;">{{ $value }}</span>
            </div>
        @endforeach
        @if($submission->notes)
            <div style="margin-top:12px; background:#f8fafc; border-radius:8px; padding:11px 13px;">
                <span style="font-size:0.72rem; color:#94a3b8; text-transform:uppercase;">{{ __('Your note') }}</span>
                <p style="font-size:0.83rem; color:#475569; margin-top:3px;">{{ $submission->notes }}</p>
            </div>
        @endif
    </div>

    {{-- Receipt preview --}}
    <div class="pp-card" style="padding:22px;">
        <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:14px;">{{ __('Uploaded Receipt') }}</h3>
        @if($isImage)
            <a href="{{ asset('storage/' . $submission->receipt_path) }}" target="_blank">
                <img src="{{ asset('storage/' . $submission->receipt_path) }}" alt="receipt" style="max-width:100%; border-radius:9px; border:1px solid #e2e8f0;">
            </a>
        @else
            <a href="{{ asset('storage/' . $submission->receipt_path) }}" target="_blank"
               style="display:inline-flex; align-items:center; gap:9px; background:#f8fafc; border:1px solid #e2e8f0; padding:12px 16px; border-radius:9px; font-size:0.85rem; color:#0f172a; font-weight:600;">
                <svg width="20" height="20" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                {{ __('Open PDF Receipt') }}
            </a>
        @endif
    </div>
</div>
@endsection
