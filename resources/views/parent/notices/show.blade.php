@extends('parent.layouts.app')
@section('title', $notice->title)
@section('heading', __('Notice'))

@section('content')
<div class="pp-card" style="padding:26px; max-width:760px;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
        <div style="min-width:0;">
            @if($notice->category)
                <span style="display:inline-block; font-size:0.64rem; font-weight:700; padding:2px 8px; border-radius:99px; background:#f1f5f9; color:#475569; text-transform:uppercase; letter-spacing:0.04em;">{{ $notice->category->name }}</span>
            @endif
            <h2 style="font-size:1.2rem; font-weight:800; color:#0f172a; margin-top:8px;">{{ $notice->title }}</h2>
        </div>
        <span style="font-size:0.7rem; color:#94a3b8; white-space:nowrap;">{{ $notice->published_at?->format('d M Y') }}</span>
    </div>

    <p style="font-size:0.9rem; color:#334155; line-height:1.7; margin-top:16px; white-space:pre-line;">{{ $notice->body }}</p>

    @if($notice->attachment)
        <a href="{{ route('parent.files.notice', $notice) }}" target="_blank"
           style="display:inline-block; margin-top:18px; font-size:0.8rem; font-weight:600; color:#0d9488;">
            📎 {{ __('Open the attachment') }}
        </a>
    @endif

    <div style="display:flex; gap:8px; align-items:center; margin-top:20px; padding-top:16px; border-top:1px solid #e2e8f0;">
        @if($notice->scope !== 'all')
            <span style="font-size:0.68rem; font-weight:600; padding:3px 9px; border-radius:99px; background:#f0fdfa; color:#0d9488;">{{ $notice->reach() }}</span>
        @endif
        <span style="font-size:0.68rem; color:#94a3b8;">{{ $notice->reference }}</span>
    </div>
</div>

<a href="{{ route('parent.notices.index') }}" style="display:inline-block; margin-top:16px; font-size:0.78rem; color:#64748b;">← {{ __('Back to notices') }}</a>
@endsection
