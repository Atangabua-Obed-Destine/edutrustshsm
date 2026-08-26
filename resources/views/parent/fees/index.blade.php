@extends('parent.layouts.app')
@section('title', __('Fees'))
@section('heading', __('Fees & Payments'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $statusColors = [
        'paid' => ['#dcfce7', '#16a34a'], 'partial' => ['#fef9c3', '#ca8a04'],
        'unpaid' => ['#fee2e2', '#dc2626'], 'overpaid' => ['#dbeafe', '#2563eb'],
        'waived' => ['#f3e8ff', '#9333ea'],
    ];
@endphp

{{-- Student context bar --}}
<div style="margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
    <div style="font-size:0.88rem; color:#64748b;">{{ __('Fees for') }} <span style="color:#0f172a; font-weight:700;">{{ $studentModel->first_name }} {{ $studentModel->last_name }}</span></div>
    @if(\Illuminate\Support\Facades\Route::has('parent.payments.create'))
    <a href="{{ route('parent.payments.create', ['student_id' => $studentModel->id]) }}"
       style="background:#14b8a6; color:#fff; padding:9px 16px; border-radius:9px; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:7px;"
       onmouseover="this.style.background='#0d9488'" onmouseout="this.style.background='#14b8a6'">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('Submit a Payment') }}
    </a>
    @endif
</div>

@if(!$enrollment)
    <div class="pp-card" style="padding:40px; text-align:center;">
        <p style="font-size:0.9rem; color:#64748b;">{{ __('This student has no active enrollment, so no fees are assigned yet.') }}</p>
    </div>
@else
    {{-- Summary cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(170px,1fr)); gap:14px; margin-bottom:22px;">
        <div class="pp-card" style="padding:18px;">
            <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase;">{{ __('Total Fees') }}</div>
            <div style="font-size:1.25rem; font-weight:700; color:#0f172a; margin-top:4px;">{{ number_format($totals['net'], 0) }} <span style="font-size:0.75rem; color:#94a3b8;">{{ $currency }}</span></div>
        </div>
        <div class="pp-card" style="padding:18px;">
            <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase;">{{ __('Paid') }}</div>
            <div style="font-size:1.25rem; font-weight:700; color:#16a34a; margin-top:4px;">{{ number_format($totals['paid'], 0) }} <span style="font-size:0.75rem; color:#94a3b8;">{{ $currency }}</span></div>
        </div>
        <div class="pp-card" style="padding:18px;">
            <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase;">{{ __('Balance') }}</div>
            <div style="font-size:1.25rem; font-weight:700; color:{{ $totals['balance'] > 0 ? '#dc2626' : '#16a34a' }}; margin-top:4px;">{{ number_format($totals['balance'], 0) }} <span style="font-size:0.75rem; color:#94a3b8;">{{ $currency }}</span></div>
        </div>
    </div>

    {{-- Fee breakdown --}}
    <div class="pp-card" style="overflow:hidden; margin-bottom:22px;">
        <div style="padding:16px 20px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ __('Fee Breakdown') }}</h3>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.84rem;">
                <thead>
                    <tr style="text-align:left; color:#64748b; font-size:0.72rem; text-transform:uppercase; border-bottom:1px solid #f1f5f9;">
                        <th style="padding:11px 20px; font-weight:600;">{{ __('Fee') }}</th>
                        <th style="padding:11px 20px; font-weight:600; text-align:right;">{{ __('Amount') }}</th>
                        <th style="padding:11px 20px; font-weight:600; text-align:right;">{{ __('Paid') }}</th>
                        <th style="padding:11px 20px; font-weight:600; text-align:right;">{{ __('Balance') }}</th>
                        <th style="padding:11px 20px; font-weight:600; text-align:center;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fees as $fee)
                        @php $sc = $statusColors[$fee->status] ?? ['#f1f5f9', '#475569']; @endphp
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:13px 20px; font-weight:600; color:#1e293b;">{{ $fee->feeCategory->name ?? '—' }}</td>
                            <td style="padding:13px 20px; text-align:right; color:#475569;">{{ number_format($fee->net_amount, 0) }}</td>
                            <td style="padding:13px 20px; text-align:right; color:#16a34a;">{{ number_format($fee->paid_amount, 0) }}</td>
                            <td style="padding:13px 20px; text-align:right; font-weight:600; color:{{ $fee->balance > 0 ? '#dc2626' : '#16a34a' }};">{{ number_format($fee->balance, 0) }}</td>
                            <td style="padding:13px 20px; text-align:center;">
                                <span style="font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:99px; background:{{ $sc[0] }}; color:{{ $sc[1] }}; text-transform:capitalize;">{{ $fee->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:30px; text-align:center; color:#94a3b8;">{{ __('No fees assigned yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Payment history --}}
    <div class="pp-card" style="overflow:hidden;">
        <div style="padding:16px 20px; border-bottom:1px solid #f1f5f9;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ __('Verified Payments') }}</h3>
            <p style="font-size:0.76rem; color:#94a3b8; margin-top:2px;">{{ __('Receipts confirmed by the school.') }}</p>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.84rem;">
                <thead>
                    <tr style="text-align:left; color:#64748b; font-size:0.72rem; text-transform:uppercase; border-bottom:1px solid #f1f5f9;">
                        <th style="padding:11px 20px; font-weight:600;">{{ __('Receipt #') }}</th>
                        <th style="padding:11px 20px; font-weight:600;">{{ __('Date') }}</th>
                        <th style="padding:11px 20px; font-weight:600;">{{ __('Method') }}</th>
                        <th style="padding:11px 20px; font-weight:600; text-align:right;">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $p)
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:13px 20px; font-family:monospace; color:#0f172a;">{{ $p->receipt_number }}</td>
                            <td style="padding:13px 20px; color:#475569;">{{ $p->payment_date?->format('d M Y') }}</td>
                            <td style="padding:13px 20px; color:#475569; text-transform:capitalize;">{{ str_replace('_', ' ', $p->payment_method) }}</td>
                            <td style="padding:13px 20px; text-align:right; font-weight:600; color:#16a34a;">{{ number_format($p->amount, 0) }} {{ $currency }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:30px; text-align:center; color:#94a3b8;">{{ __('No payments recorded yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
