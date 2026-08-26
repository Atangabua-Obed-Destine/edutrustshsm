@extends('parent.layouts.app')
@section('title', __('Announcements'))
@section('heading', __('PTA Announcements'))

@section('content')
@if($announcements->isEmpty())
    <div class="pp-card" style="padding:44px; text-align:center;">
        <p style="font-size:0.88rem; color:#64748b;">{{ __('There are no announcements at the moment.') }}</p>
    </div>
@else
    <div style="display:flex; flex-direction:column; gap:14px; max-width:760px;">
        @foreach($announcements as $a)
            <div class="pp-card" style="padding:20px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                    <h3 style="font-size:1rem; font-weight:700; color:#0f172a;">{{ $a->title }}</h3>
                    <span style="font-size:0.68rem; color:#94a3b8; white-space:nowrap;">{{ $a->published_at?->format('d M Y') }}</span>
                </div>
                <p style="font-size:0.86rem; color:#475569; line-height:1.6; margin-top:8px; white-space:pre-line;">{{ $a->body }}</p>
                @if($a->audience !== 'all')
                    <span style="display:inline-block; margin-top:10px; font-size:0.68rem; font-weight:600; padding:3px 9px; border-radius:99px; background:#f0fdfa; color:#0d9488;">
                        {{ $a->audience === 'form_specific' ? ($a->targetForm->name ?? __('Form')) : ($a->targetClass->name ?? __('Class')) }}
                    </span>
                @endif
            </div>
        @endforeach
    </div>
@endif
@endsection
