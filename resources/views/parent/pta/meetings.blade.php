@extends('parent.layouts.app')
@section('title', __('Meetings'))
@section('heading', __('PTA Meetings'))

@section('content')
<div style="max-width:760px;">
    {{-- Upcoming --}}
    <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:12px;">{{ __('Upcoming') }}</h3>
    @if($upcoming->isEmpty())
        <div class="pp-card" style="padding:24px; text-align:center; margin-bottom:24px;">
            <p style="font-size:0.85rem; color:#94a3b8;">{{ __('No upcoming meetings scheduled.') }}</p>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:28px;">
            @foreach($upcoming as $m)
                <div class="pp-card" style="padding:18px; border-left:3px solid #14b8a6;">
                    <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $m->title }}</div>
                    <div style="font-size:0.82rem; color:#64748b; margin-top:4px;">📅 {{ $m->meeting_date->format('l, d M Y · H:i') }}@if($m->venue) · 📍 {{ $m->venue }}@endif</div>
                    @if($m->agenda)<p style="font-size:0.83rem; color:#475569; margin-top:8px; white-space:pre-line;">{{ $m->agenda }}</p>@endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Past --}}
    <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:12px;">{{ __('Past Meetings') }}</h3>
    @if($past->isEmpty())
        <div class="pp-card" style="padding:24px; text-align:center;">
            <p style="font-size:0.85rem; color:#94a3b8;">{{ __('No past meetings yet.') }}</p>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:12px;">
            @foreach($past as $m)
                <div class="pp-card" style="padding:18px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
                        <div>
                            <div style="font-size:0.9rem; font-weight:600; color:#1e293b;">{{ $m->title }}</div>
                            <div style="font-size:0.78rem; color:#94a3b8; margin-top:2px;">{{ $m->meeting_date->format('d M Y') }}</div>
                        </div>
                        @if($m->minutes_path)
                            <a href="{{ asset('storage/'.$m->minutes_path) }}" target="_blank" style="font-size:0.76rem; color:#14b8a6; font-weight:600; white-space:nowrap;">📄 {{ __('Minutes') }}</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
