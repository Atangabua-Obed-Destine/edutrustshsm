@extends('parent.layouts.app')
@section('title', __('School Calendar'))
@section('heading', __('School Calendar'))

@section('content')
<div style="max-width:820px;">
    <h3 style="font-size:0.78rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:12px;">{{ __('Coming up') }}</h3>

    @if($upcoming->isEmpty())
        <div class="pp-card" style="padding:32px; text-align:center;">
            <p style="font-size:0.86rem; color:#64748b;">{{ __('Nothing is scheduled at the moment.') }}</p>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:12px;">
            @foreach($upcoming as $event)
                <div class="pp-card" style="padding:18px; display:flex; gap:14px; align-items:flex-start;">
                    <div style="min-width:58px; text-align:center; background:#f0fdfa; border-radius:8px; padding:8px 4px;">
                        <div style="font-size:1.15rem; font-weight:800; color:#0d9488; line-height:1;">{{ $event->starts_on->format('j') }}</div>
                        <div style="font-size:0.6rem; color:#0d9488; text-transform:uppercase;">{{ $event->starts_on->format('M') }}</div>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
                            <h4 style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $event->title }}</h4>
                            @if($event->isRunning())
                                <span style="font-size:0.64rem; font-weight:700; padding:2px 8px; border-radius:99px; background:#ecfdf5; color:#059669; white-space:nowrap;">{{ __('On now') }}</span>
                            @endif
                        </div>
                        <div style="font-size:0.74rem; color:#64748b; margin-top:3px;">
                            {{ $event->whenLabel() }}
                            @if(! $event->isAllDay()) · {{ \Illuminate\Support\Str::substr($event->starts_at, 0, 5) }}@endif
                            @if($event->venue) · {{ $event->venue }}@endif
                        </div>
                        @if($event->description)
                            <p style="font-size:0.82rem; color:#475569; line-height:1.6; margin-top:8px; white-space:pre-line;">{{ $event->description }}</p>
                        @endif
                        @if($event->scope !== 'all')
                            <span style="display:inline-block; margin-top:8px; font-size:0.66rem; font-weight:600; padding:3px 9px; border-radius:99px; background:#f1f5f9; color:#475569;">{{ $event->reach() }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($past->isNotEmpty())
        <h3 style="font-size:0.78rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:0.06em; margin:26px 0 12px;">{{ __('Earlier') }}</h3>
        <div class="pp-card" style="padding:6px 0;">
            @foreach($past as $event)
                <div style="padding:11px 18px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; gap:12px;">
                    <span style="font-size:0.84rem; color:#475569;">{{ $event->title }}</span>
                    <span style="font-size:0.72rem; color:#94a3b8; white-space:nowrap;">{{ $event->whenLabel() }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
