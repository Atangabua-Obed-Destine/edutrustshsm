@extends('parent.layouts.app')
@section('title', __('PTA Levies'))
@section('heading', __('PTA Levy Status'))

@section('content')
@php $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA'); @endphp

<div style="max-width:760px;">
    <div class="pp-card" style="padding:14px 18px; margin-bottom:18px; background:#f0fdfa; border:1px solid #ccfbf1;">
        <p style="font-size:0.82rem; color:#115e59; line-height:1.5;">
            {{ __('The PTA levy supports school development projects. To pay, use the') }}
            <a href="{{ route('parent.payments.index') }}" style="color:#0d9488; font-weight:700;">{{ __('payment submission') }}</a>
            {{ __('feature and note "PTA Levy" — the school will verify and record it here.') }}
        </p>
    </div>

    @if(empty($levyStatus))
        <div class="pp-card" style="padding:44px; text-align:center;">
            <p style="font-size:0.88rem; color:#64748b;">{{ __('No PTA levy has been set for your children’s classes yet.') }}</p>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:14px;">
            @foreach($levyStatus as $row)
                @php $pct = $row['levy']->amount > 0 ? round(($row['paid'] / $row['levy']->amount) * 100) : 0; @endphp
                <div class="pp-card" style="padding:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <div>
                            <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $row['student']->first_name }} {{ $row['student']->last_name }}</div>
                            <div style="font-size:0.76rem; color:#64748b;">{{ $row['levy']->description ?: __('PTA Levy') }}@if($row['levy']->due_date) · {{ __('Due') }} {{ $row['levy']->due_date->format('d M Y') }}@endif</div>
                        </div>
                        <span style="font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:99px; background:{{ $row['balance'] <= 0 ? '#dcfce7' : '#fee2e2' }}; color:{{ $row['balance'] <= 0 ? '#16a34a' : '#dc2626' }};">
                            {{ $row['balance'] <= 0 ? __('Fully Paid') : __('Outstanding') }}
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:6px;">
                        <span style="color:#64748b;">{{ __('Paid') }} {{ number_format($row['paid'],0) }} / {{ number_format($row['levy']->amount,0) }} {{ $currency }}</span>
                        <span style="color:{{ $row['balance'] > 0 ? '#dc2626' : '#16a34a' }}; font-weight:600;">{{ number_format($row['balance'],0) }} {{ $currency }} {{ __('left') }}</span>
                    </div>
                    <div style="height:7px; background:#f1f5f9; border-radius:99px; overflow:hidden;">
                        <div style="height:100%; width:{{ min(100,$pct) }}%; background:{{ $pct >= 100 ? '#16a34a' : '#14b8a6' }}; border-radius:99px;"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
