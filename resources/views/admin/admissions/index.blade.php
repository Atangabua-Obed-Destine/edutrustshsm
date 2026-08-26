@extends('layouts.admin')
@section('title', __('Admission Applications'))

@section('content')
<div style="padding: 24px;">
    {{-- Page Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.35rem; font-weight: 700; color: #0f172a;">{{ __('Admission Applications') }}</h1>
            <p style="font-size: 0.82rem; color: #64748b; margin-top: 3px;">{{ __('Review and manage online admission applications.') }}
                @if($currentSession)
                <span style="margin-left: 6px; background: #eff6ff; color: #2563eb; padding: 2px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 600;">{{ $currentSession->name }}</span>
                @endif
            </p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 14px; margin-bottom: 24px;">
        @php
        $statCards = [
            ['label' => __('Total'), 'count' => $stats['total'], 'color' => '#475569', 'bg' => '#f1f5f9',  'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['label' => __('Pending'), 'count' => $stats['pending'], 'color' => '#f59e0b', 'bg' => '#fffbeb', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => __('Under Review'), 'count' => $stats['under_review'], 'color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'],
            ['label' => __('Accepted'), 'count' => $stats['accepted'], 'color' => '#22c55e', 'bg' => '#f0fdf4', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => __('Rejected'), 'count' => $stats['rejected'], 'color' => '#ef4444', 'bg' => '#fef2f2', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => __('Enrolled'), 'count' => $stats['enrolled'], 'color' => '#8b5cf6', 'bg' => '#f5f3ff', 'icon' => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
        ];
        @endphp
        @foreach($statCards as $card)
        <div style="background: {{ $card['bg'] }}; border-radius: 10px; padding: 16px; text-align: center; border: 1px solid {{ $card['color'] }}22;">
            <svg width="22" height="22" style="color: {{ $card['color'] }}; margin: 0 auto 6px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/></svg>
            <div style="font-size: 1.3rem; font-weight: 800; color: {{ $card['color'] }};">{{ $card['count'] }}</div>
            <div style="font-size: 0.7rem; color: {{ $card['color'] }}; opacity: 0.8; font-weight: 500;">{{ $card['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Filters --}}
    <div style="background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 18px 20px; margin-bottom: 20px;">
        <form method="GET" action="{{ route('admin.admissions.applications.index') }}" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 160px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Session') }}</label>
                <select name="academic_session_id" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;">
                    <option value="">{{ __('All Sessions') }}</option>
                    @foreach($sessions as $session)
                    <option value="{{ $session->id }}" {{ request('academic_session_id') == $session->id ? 'selected' : '' }}>{{ $session->name }}{{ $session->is_current ? ' ✦' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Status') }}</label>
                <select name="status" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(['pending','under_review','accepted','rejected','enrolled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Form') }}</label>
                <select name="form_id" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;">
                    <option value="">{{ __('All Forms') }}</option>
                    @foreach($forms as $form)
                    <option value="{{ $form->id }}" {{ request('form_id') == $form->id ? 'selected' : '' }}>{{ $form->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1; min-width: 160px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Stream') }}</label>
                <select name="stream_id" style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;">
                    <option value="">{{ __('All Streams') }}</option>
                    @foreach($streams as $stream)
                    <option value="{{ $stream->id }}" {{ request('stream_id') == $stream->id ? 'selected' : '' }}>{{ $stream->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex: 1.5; min-width: 200px;">
                <label style="display: block; font-size: 0.72rem; font-weight: 600; color: #64748b; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Application # or name...') }}"
                       style="width: 100%; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.82rem; outline: none;">
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" style="padding: 8px 18px; background: #0ea5e9; color: #fff; border: none; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer;"
                        onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
                    {{ __('Filter') }}
                </button>
                <a href="{{ route('admin.admissions.applications.index') }}" style="padding: 8px 14px; background: #f1f5f9; color: #64748b; border-radius: 6px; font-size: 0.82rem; font-weight: 500; display: inline-flex; align-items: center;"
                   onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                    {{ __('Clear') }}
                </a>
            </div>
        </form>
    </div>

    {{-- Applications Table --}}
    <div style="background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden;">
        @if($applications->count() === 0)
        <div style="padding: 48px 24px; text-align: center;">
            <svg width="42" height="42" style="color: #cbd5e1; margin: 0 auto 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p style="font-size: 0.88rem; color: #64748b; font-weight: 500;">{{ __('No applications found matching your criteria.') }}</p>
        </div>
        @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Application #') }}</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Applicant') }}</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Session') }}</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Form') }}</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Stream') }}</th>
                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Status') }}</th>
                        <th style="padding: 12px 16px; text-align: left; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Date') }}</th>
                        <th style="padding: 12px 16px; text-align: center; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($applications as $app)
                    @php $badge = $app->status_badge; @endphp
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s;"
                        onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 12px 16px; font-weight: 600; color: #0f172a;">{{ $app->application_number }}</td>
                        <td style="padding: 12px 16px;">
                            <div style="font-weight: 500; color: #0f172a;">{{ $app->full_name }}</div>
                            <div style="font-size: 0.72rem; color: #94a3b8;">{{ $app->gender ? ucfirst($app->gender) : '' }}{{ $app->date_of_birth ? ' · ' . $app->date_of_birth->format('M d, Y') : '' }}</div>
                        </td>
                        <td style="padding: 12px 16px; color: #475569; font-size: 0.78rem; white-space: nowrap;">{{ $app->academicSession->name ?? '—' }}</td>
                        <td style="padding: 12px 16px; color: #475569;">{{ $app->form->name ?? '—' }}</td>
                        <td style="padding: 12px 16px; color: #475569;">{{ $app->stream->name ?? '—' }}</td>
                        <td style="padding: 12px 16px; text-align: center;">
                            <span style="background: {{ $badge['bg'] }}; color: {{ $badge['color'] }}; padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 600; text-transform: uppercase; white-space: nowrap;">{{ $badge['label'] }}</span>
                        </td>
                        <td style="padding: 12px 16px; color: #94a3b8; font-size: 0.78rem; white-space: nowrap;">{{ $app->created_at->format('M d, Y') }}</td>
                        <td style="padding: 12px 16px; text-align: center;">
                            <a href="{{ route('admin.admissions.applications.show', $app) }}"
                               style="display: inline-flex; align-items: center; gap: 4px; background: #eff6ff; color: #2563eb; padding: 5px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; transition: all 0.2s;"
                               onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                {{ __('Review') }}
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($applications->hasPages())
        <div style="padding: 14px 20px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; font-size: 0.78rem; color: #64748b;">
            <span>{{ __('Showing :from to :to of :total', ['from' => $applications->firstItem(), 'to' => $applications->lastItem(), 'total' => $applications->total()]) }}</span>
            <div style="display: flex; gap: 4px;">
                @if($applications->onFirstPage())
                <span style="padding: 6px 12px; background: #f1f5f9; border-radius: 5px; color: #cbd5e1;">{{ __('← Prev') }}</span>
                @else
                <a href="{{ $applications->previousPageUrl() }}" style="padding: 6px 12px; background: #f1f5f9; border-radius: 5px; color: #374151;"
                   onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">{{ __('← Prev') }}</a>
                @endif
                @if($applications->hasMorePages())
                <a href="{{ $applications->nextPageUrl() }}" style="padding: 6px 12px; background: #f1f5f9; border-radius: 5px; color: #374151;"
                   onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">{{ __('Next →') }}</a>
                @else
                <span style="padding: 6px 12px; background: #f1f5f9; border-radius: 5px; color: #cbd5e1;">{{ __('Next →') }}</span>
                @endif
            </div>
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
