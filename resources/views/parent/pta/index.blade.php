@extends('parent.layouts.app')
@section('title', __('PTA'))
@section('heading', __('Parent-Teacher Association'))

@section('content')
@php $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA'); @endphp

{{-- Next meeting --}}
@if($nextMeeting)
<div class="pp-card" style="overflow:hidden; margin-bottom:20px;">
    <div style="padding:20px 22px; background:linear-gradient(135deg, #0f172a 0%, #134e4a 100%); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
        <div>
            <div style="font-size:0.68rem; color:#5eead4; text-transform:uppercase; letter-spacing:0.06em;">{{ __('Next PTA Meeting') }}</div>
            <div style="font-size:1.1rem; font-weight:700; color:#fff; margin-top:4px;">{{ $nextMeeting->title }}</div>
            <div style="font-size:0.82rem; color:#cbd5e1; margin-top:3px;">📅 {{ $nextMeeting->meeting_date->format('l, d M Y · H:i') }}@if($nextMeeting->venue) · 📍 {{ $nextMeeting->venue }}@endif</div>
        </div>
        <a href="{{ route('parent.pta.meetings') }}" style="background:rgba(255,255,255,0.12); color:#fff; padding:8px 16px; border-radius:8px; font-size:0.8rem; font-weight:600;">{{ __('All meetings') }} →</a>
    </div>
</div>
@endif

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px,1fr)); gap:18px;">
    {{-- Announcements --}}
    <div class="pp-card" style="padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ __('Announcements') }}</h3>
            <a href="{{ route('parent.pta.announcements') }}" style="font-size:0.76rem; color:#14b8a6; font-weight:600;">{{ __('See all') }}</a>
        </div>
        @forelse($announcements as $a)
            <div style="padding:11px 0; border-bottom:1px solid #f1f5f9;">
                <div style="font-size:0.86rem; font-weight:600; color:#1e293b;">{{ $a->title }}</div>
                <div style="font-size:0.78rem; color:#64748b; margin-top:2px;">{{ \Illuminate\Support\Str::limit($a->body, 90) }}</div>
                <div style="font-size:0.68rem; color:#94a3b8; margin-top:3px;">{{ $a->published_at?->diffForHumans() }}</div>
            </div>
        @empty
            <p style="font-size:0.82rem; color:#94a3b8;">{{ __('No announcements right now.') }}</p>
        @endforelse
    </div>

    {{-- Levy status --}}
    <div class="pp-card" style="padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ __('PTA Levy Status') }}</h3>
            <a href="{{ route('parent.pta.levies') }}" style="font-size:0.76rem; color:#14b8a6; font-weight:600;">{{ __('Details') }}</a>
        </div>
        @forelse($levyStatus as $row)
            <div style="padding:11px 0; border-bottom:1px solid #f1f5f9;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.84rem; font-weight:600; color:#1e293b;">{{ $row['student']->first_name }}</span>
                    <span style="font-size:0.78rem; font-weight:700; color:{{ $row['balance'] > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ $row['balance'] > 0 ? number_format($row['balance'],0) . ' ' . $currency . ' ' . __('due') : __('Paid') }}
                    </span>
                </div>
            </div>
        @empty
            <p style="font-size:0.82rem; color:#94a3b8;">{{ __('No PTA levy set for your children yet.') }}</p>
        @endforelse
    </div>
</div>
@endsection
