@extends('parent.layouts.app')
@section('title', __('Subjects & Teachers'))
@section('heading', __('Subjects & Teachers'))

@section('content')
<div style="margin-bottom:18px; font-size:0.88rem; color:#64748b;">
    {{ __('Registered subjects for') }} <span style="color:#0f172a; font-weight:700;">{{ $studentModel->first_name }} {{ $studentModel->last_name }}</span>
</div>

@if(!$enrollment || $subjects->isEmpty())
    <div class="pp-card" style="padding:44px; text-align:center;">
        <p style="font-size:0.88rem; color:#64748b;">{{ __('No subjects registered for this student yet.') }}</p>
    </div>
@else
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(290px,1fr)); gap:16px;">
        @foreach($subjects as $s)
            <div class="pp-card" style="padding:18px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                    <div style="min-width:0;">
                        <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $s->name }}</div>
                        @if($s->code)<div style="font-size:0.72rem; color:#94a3b8; font-family:monospace;">{{ $s->code }}</div>@endif
                        @if($s->department)<div style="font-size:0.72rem; color:#64748b; margin-top:2px;">{{ $s->department }}</div>@endif
                    </div>
                    <span style="font-size:0.68rem; font-weight:600; padding:3px 9px; border-radius:99px; background:#f0fdfa; color:#0d9488; white-space:nowrap;">{{ __('Coeff') }} {{ rtrim(rtrim((string)$s->coefficient, '0'), '.') }}</span>
                </div>
                <div style="border-top:1px solid #f1f5f9; padding-top:12px;">
                    @if($s->teacher)
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:34px; height:34px; border-radius:50%; background:#0f172a; display:flex; align-items:center; justify-content:center; font-size:0.72rem; font-weight:700; color:#5eead4; flex-shrink:0;">{{ mb_substr($s->teacher, 0, 1) }}</div>
                            <div style="min-width:0;">
                                <div style="font-size:0.82rem; font-weight:600; color:#1e293b;">{{ $s->teacher }}</div>
                                <div style="font-size:0.7rem; color:#94a3b8;">{{ __('Subject Teacher') }}</div>
                            </div>
                        </div>
                        <div style="display:flex; gap:8px; margin-top:10px; flex-wrap:wrap;">
                            @if($s->teacher_phone)
                                <a href="tel:{{ $s->teacher_phone }}" style="flex:1; min-width:120px; display:flex; align-items:center; justify-content:center; gap:6px; font-size:0.74rem; color:#0d9488; background:#f0fdfa; padding:7px; border-radius:7px; font-weight:600;">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    {{ $s->teacher_phone }}
                                </a>
                            @endif
                            @if($s->teacher_email)
                                <a href="mailto:{{ $s->teacher_email }}" style="flex:1; min-width:120px; display:flex; align-items:center; justify-content:center; gap:6px; font-size:0.74rem; color:#475569; background:#f8fafc; padding:7px; border-radius:7px; font-weight:600;">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    {{ __('Email') }}
                                </a>
                            @endif
                        </div>
                    @else
                        <p style="font-size:0.78rem; color:#94a3b8;">{{ __('No teacher assigned yet.') }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
