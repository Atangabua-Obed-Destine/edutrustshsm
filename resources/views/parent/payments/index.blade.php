@extends('parent.layouts.app')
@section('title', __('Fees & Payments'))
@section('heading', __('Fees & Payments'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $feeStatusColors = [
        'paid' => ['#dcfce7', '#16a34a'], 'partial' => ['#fef9c3', '#ca8a04'],
        'unpaid' => ['#fee2e2', '#dc2626'], 'overpaid' => ['#dbeafe', '#2563eb'],
        'waived' => ['#f3e8ff', '#9333ea'],
    ];
    $subStatusColors = [
        'pending' => ['#fef9c3', '#ca8a04', __('Pending Review')],
        'approved' => ['#dcfce7', '#16a34a', __('Approved')],
        'rejected' => ['#fee2e2', '#dc2626', __('Rejected')],
    ];
@endphp

<div style="margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <p style="font-size:0.88rem; color:#64748b;">{{ __('View your children’s assigned fees and submit payments for verification.') }}</p>
    <a href="{{ route('parent.payments.create') }}"
       style="background:#0f172a; color:#fff; padding:9px 16px; border-radius:9px; font-size:0.82rem; font-weight:600; display:inline-flex; align-items:center; gap:7px;"
       onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='#0f172a'">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('Other Payment') }}
    </a>
</div>

{{-- ── ASSIGNED FEES PER CHILD ── --}}
@forelse($feesByChild as $cid => $block)
    @php $student = $block['student']; $fees = $block['fees']; $t = $block['totals']; @endphp
    <div class="pp-card" style="overflow:hidden; margin-bottom:20px;">
        {{-- child header --}}
        <div style="padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; background:linear-gradient(135deg, #0f172a 0%, #134e4a 100%);">
            <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                <div style="width:40px; height:40px; border-radius:10px; background:rgba(20,184,166,0.25); display:flex; align-items:center; justify-content:center; font-size:0.85rem; font-weight:700; color:#5eead4;">{{ mb_substr($student->first_name,0,1) }}{{ mb_substr($student->last_name,0,1) }}</div>
                <div style="min-width:0;">
                    <div style="font-size:0.95rem; font-weight:700; color:#fff;">{{ $student->first_name }} {{ $student->last_name }}</div>
                    <div style="font-size:0.72rem; color:#94a3b8;">{{ $student->student_id }} · {{ $block['enrollment']->classSection->name ?? $block['enrollment']->classSection->form->name ?? '' }}</div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.66rem; color:#94a3b8; text-transform:uppercase;">{{ __('Balance') }}</div>
                <div style="font-size:1.15rem; font-weight:800; color:{{ $t['balance'] > 0 ? '#fca5a5' : '#86efac' }};">{{ number_format($t['balance'],0) }} <span style="font-size:0.7rem; color:#94a3b8;">{{ $currency }}</span></div>
            </div>
        </div>

        {{-- fee rows --}}
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.84rem;">
                <thead>
                    <tr style="text-align:left; color:#64748b; font-size:0.7rem; text-transform:uppercase; border-bottom:1px solid #f1f5f9;">
                        <th style="padding:11px 20px; font-weight:600;">{{ __('Fee') }}</th>
                        <th style="padding:11px 12px; font-weight:600; text-align:right;">{{ __('Amount') }}</th>
                        <th style="padding:11px 12px; font-weight:600; text-align:right;">{{ __('Paid') }}</th>
                        <th style="padding:11px 12px; font-weight:600; text-align:right;">{{ __('Balance') }}</th>
                        <th style="padding:11px 12px; font-weight:600; text-align:center;">{{ __('Status') }}</th>
                        <th style="padding:11px 20px; font-weight:600; text-align:right;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fees as $fee)
                        @php
                            $sc = $feeStatusColors[$fee->status] ?? ['#f1f5f9', '#475569'];
                            $isPending = in_array($fee->id, $block['pendingFeeIds']);
                        @endphp
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:13px 20px; font-weight:600; color:#1e293b;">{{ $fee->feeCategory->name ?? '—' }}</td>
                            <td style="padding:13px 12px; text-align:right; color:#475569;">{{ number_format($fee->net_amount,0) }}</td>
                            <td style="padding:13px 12px; text-align:right; color:#16a34a;">{{ number_format($fee->paid_amount,0) }}</td>
                            <td style="padding:13px 12px; text-align:right; font-weight:600; color:{{ $fee->balance > 0 ? '#dc2626' : '#16a34a' }};">{{ number_format($fee->balance,0) }}</td>
                            <td style="padding:13px 12px; text-align:center;">
                                <span style="font-size:0.68rem; font-weight:600; padding:3px 9px; border-radius:99px; background:{{ $sc[0] }}; color:{{ $sc[1] }}; text-transform:capitalize;">{{ $fee->status }}</span>
                            </td>
                            <td style="padding:13px 20px; text-align:right;">
                                @if($fee->balance <= 0)
                                    <span style="font-size:0.74rem; color:#16a34a; font-weight:600;">✓ {{ __('Settled') }}</span>
                                @elseif($isPending)
                                    <span style="font-size:0.72rem; color:#ca8a04; font-weight:600;">⏳ {{ __('Under review') }}</span>
                                @else
                                    <a href="{{ route('parent.payments.create', ['student_id' => $student->id, 'fee_id' => $fee->id]) }}"
                                       style="display:inline-block; background:#14b8a6; color:#fff; padding:6px 14px; border-radius:7px; font-size:0.76rem; font-weight:600;"
                                       onmouseover="this.style.background='#0d9488'" onmouseout="this.style.background='#14b8a6'">{{ __('Pay') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:26px; text-align:center; color:#94a3b8;">{{ __('No fees assigned to this student yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@empty
    <div class="pp-card" style="padding:40px; text-align:center; margin-bottom:20px;">
        <p style="font-size:0.9rem; color:#64748b;">{{ __('No active enrollment with assigned fees was found for your children.') }}</p>
    </div>
@endforelse

{{-- ── SUBMISSION HISTORY ── --}}
<div style="margin-top:26px;">
    <h3 style="font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:12px;">{{ __('My Payment Submissions') }}</h3>

    @if($submissions->isEmpty())
        <div class="pp-card" style="padding:30px; text-align:center;">
            <p style="font-size:0.85rem; color:#94a3b8;">{{ __('You have not submitted any payments yet. Use the “Pay” button on a fee above.') }}</p>
        </div>
    @else
        <div class="pp-card" style="overflow:hidden;">
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.84rem;">
                    <thead>
                        <tr style="text-align:left; color:#64748b; font-size:0.7rem; text-transform:uppercase; border-bottom:1px solid #f1f5f9;">
                            <th style="padding:11px 18px; font-weight:600;">{{ __('Date') }}</th>
                            <th style="padding:11px 18px; font-weight:600;">{{ __('Child / Fee') }}</th>
                            <th style="padding:11px 18px; font-weight:600; text-align:right;">{{ __('Amount') }}</th>
                            <th style="padding:11px 18px; font-weight:600; text-align:center;">{{ __('Status') }}</th>
                            <th style="padding:11px 18px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $s)
                            @php $ssc = $subStatusColors[$s->status] ?? ['#f1f5f9', '#475569', ucfirst($s->status)]; @endphp
                            <tr style="border-bottom:1px solid #f8fafc;">
                                <td style="padding:13px 18px; color:#475569;">{{ $s->payment_date?->format('d M Y') }}</td>
                                <td style="padding:13px 18px;">
                                    <div style="color:#1e293b; font-weight:600;">{{ $s->enrollment->student->first_name ?? '—' }} {{ $s->enrollment->student->last_name ?? '' }}</div>
                                    <div style="font-size:0.72rem; color:#94a3b8;">{{ $s->studentFee->feeCategory->name ?? __('General payment') }}</div>
                                </td>
                                <td style="padding:13px 18px; text-align:right; font-weight:600; color:#0f172a;">{{ number_format($s->amount,0) }} {{ $currency }}</td>
                                <td style="padding:13px 18px; text-align:center;">
                                    <span style="font-size:0.68rem; font-weight:600; padding:3px 10px; border-radius:99px; background:{{ $ssc[0] }}; color:{{ $ssc[1] }};">{{ $ssc[2] }}</span>
                                </td>
                                <td style="padding:13px 18px; text-align:right;">
                                    <a href="{{ route('parent.payments.show', $s->id) }}" style="font-size:0.76rem; color:#14b8a6; font-weight:600;">{{ __('View') }} →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div style="margin-top:14px;">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
