@extends('layouts.apply')
@section('title', __('My Applications'))

@section('content')
{{-- Welcome Header --}}
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h1 style="font-size: 1.35rem; font-weight: 700; color: #0f172a;">{{ __('Welcome back,') }} {{ Auth::guard('applicant')->user()->first_name }}!</h1>
        <p style="font-size: 0.82rem; color: #64748b; margin-top: 3px;">{{ __('Manage your admission applications below.') }}</p>
    </div>
    <a href="{{ route('apply.create') }}"
       style="display: inline-flex; align-items: center; gap: 6px; background: #0ea5e9; color: #fff; padding: 9px 18px; border-radius: 8px; font-size: 0.82rem; font-weight: 600; transition: background 0.2s;"
       onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('New Application') }}
    </a>
</div>

@if($applications->count() === 0)
{{-- Empty State --}}
<div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 60px 28px; text-align: center;">
    <svg width="56" height="56" style="color: #cbd5e1; margin: 0 auto 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    <h2 style="font-size: 1.1rem; font-weight: 600; color: #374151;">{{ __('No Applications Yet') }}</h2>
    <p style="font-size: 0.85rem; color: #64748b; margin-top: 6px; max-width: 380px; margin-left: auto; margin-right: auto;">{{ __('Start your admission journey by submitting a new application for the upcoming academic session.') }}</p>
    <a href="{{ route('apply.create') }}"
       style="display: inline-flex; align-items: center; gap: 6px; background: #0ea5e9; color: #fff; padding: 10px 22px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; margin-top: 20px; transition: background 0.2s;"
       onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        {{ __('Submit Your First Application') }}
    </a>
</div>
@else
{{-- Applications List --}}
<div style="display: flex; flex-direction: column; gap: 14px;">
    @foreach($applications as $app)
    @php $badge = $app->status_badge; @endphp
    <div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px 22px; border-left: 4px solid {{ $badge['color'] }}; transition: box-shadow 0.2s;"
         onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.06)'">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <span style="font-weight: 700; font-size: 0.95rem; color: #0f172a;">{{ $app->application_number }}</span>
                    <span style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;">{{ $badge['label'] }}</span>
                </div>
                <div style="font-size: 0.82rem; color: #475569; margin-bottom: 4px;">
                    {{ $app->full_name }}
                </div>
                <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.78rem; color: #94a3b8;">
                    <span>{{ __('Form:') }} {{ $app->form->name ?? '—' }}</span>
                    @if($app->stream)
                    <span>{{ __('Stream:') }} {{ $app->stream->name }}</span>
                    @endif
                    <span>{{ __('Session:') }} {{ $app->academicSession->name ?? '—' }}</span>
                    <span>{{ __('Applied:') }} {{ $app->created_at->format('M d, Y') }}</span>
                </div>
            </div>
            <a href="{{ route('apply.show', $app) }}"
               style="display: inline-flex; align-items: center; gap: 5px; background: #f1f5f9; color: #475569; padding: 7px 14px; border-radius: 7px; font-size: 0.78rem; font-weight: 500; transition: all 0.2s; flex-shrink: 0;"
               onmouseover="this.style.background='#e2e8f0'; this.style.color='#0f172a'" onmouseout="this.style.background='#f1f5f9'; this.style.color='#475569'">
                {{ __('View Details') }}
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        @if($app->status === 'rejected' && $app->admin_notes)
        <div style="margin-top: 12px; background: #fef2f2; border-radius: 6px; padding: 10px 12px;">
            <span style="font-size: 0.75rem; font-weight: 600; color: #dc2626;">{{ __('Rejection Note:') }}</span>
            <p style="font-size: 0.8rem; color: #7f1d1d; margin-top: 3px;">{{ $app->admin_notes }}</p>
        </div>
        @endif

        @if($app->status === 'accepted')
        <div style="margin-top: 12px; background: #f0fdf4; border-radius: 6px; padding: 10px 12px;">
            <span style="font-size: 0.78rem; color: #166534;">✓ {{ __('Your application has been accepted! The school will proceed with enrollment.') }}</span>
        </div>
        @endif

        @if($app->status === 'enrolled')
        <div style="margin-top: 12px; background: #eff6ff; border-radius: 6px; padding: 10px 12px;">
            <span style="font-size: 0.78rem; color: #1e40af;">🎓 {{ __('Enrolled successfully! Student ID:') }} <strong>{{ $app->student->student_id ?? '' }}</strong></span>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif
@endsection
