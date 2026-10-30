@extends('parent.layouts.app')
@section('title', __('Notices'))
@section('heading', __('Notices from the school'))

@section('content')
<div style="display:grid; grid-template-columns: minmax(0,1fr); gap:18px; max-width:980px;">

    @if($events->isNotEmpty())
        <div class="pp-card" style="padding:20px;">
            <h3 style="font-size:0.78rem; font-weight:700; color:#0f172a; text-transform:uppercase; letter-spacing:0.06em;">{{ __('Coming up') }}</h3>
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:12px;">
                @foreach($events as $event)
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="min-width:52px; text-align:center; background:#f0fdfa; border-radius:8px; padding:6px 4px;">
                            <div style="font-size:1.05rem; font-weight:800; color:#0d9488; line-height:1;">{{ $event->starts_on->format('j') }}</div>
                            <div style="font-size:0.6rem; color:#0d9488; text-transform:uppercase;">{{ $event->starts_on->format('M') }}</div>
                        </div>
                        <div style="min-width:0;">
                            <div style="font-size:0.86rem; font-weight:600; color:#0f172a;">{{ $event->title }}</div>
                            <div style="font-size:0.72rem; color:#64748b;">
                                {{ $event->whenLabel() }}@if($event->venue) · {{ $event->venue }}@endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <a href="{{ route('parent.notices.events') }}" style="display:inline-block; margin-top:14px; font-size:0.76rem; font-weight:600; color:#0d9488;">{{ __('See the full calendar') }} →</a>
        </div>
    @endif

    @if($notices->isEmpty())
        <div class="pp-card" style="padding:44px; text-align:center;">
            <p style="font-size:0.88rem; color:#64748b;">{{ __('The school has not posted any notices yet.') }}</p>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:14px;">
            @foreach($notices as $notice)
                <a href="{{ route('parent.notices.show', $notice) }}" class="pp-card" style="padding:20px; display:block; text-decoration:none;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                        <div style="min-width:0;">
                            @if($notice->category)
                                <span style="display:inline-block; font-size:0.64rem; font-weight:700; padding:2px 8px; border-radius:99px; background:#f1f5f9; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">{{ $notice->category->name }}</span>
                            @endif
                            <h3 style="font-size:1rem; font-weight:700; color:#0f172a; margin-top:6px;">{{ $notice->title }}</h3>
                        </div>
                        <span style="font-size:0.68rem; color:#94a3b8; white-space:nowrap;">{{ $notice->published_at?->format('d M Y') }}</span>
                    </div>
                    <p style="font-size:0.86rem; color:#475569; line-height:1.6; margin-top:8px;">{{ \Illuminate\Support\Str::limit(strip_tags($notice->body), 180) }}</p>
                    <div style="display:flex; gap:8px; align-items:center; margin-top:10px;">
                        @if($notice->scope !== 'all')
                            <span style="font-size:0.68rem; font-weight:600; padding:3px 9px; border-radius:99px; background:#f0fdfa; color:#0d9488;">{{ $notice->reach() }}</span>
                        @endif
                        @if($notice->attachment)
                            <span style="font-size:0.68rem; color:#64748b;">📎 {{ __('Attachment') }}</span>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div>{{ $notices->links() }}</div>
    @endif
</div>
@endsection
