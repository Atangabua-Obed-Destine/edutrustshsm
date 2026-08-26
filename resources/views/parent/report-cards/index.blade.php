@extends('parent.layouts.app')
@section('title', __('Report Cards'))
@section('heading', __('Report Cards'))

@section('content')
<div style="margin-bottom:18px; font-size:0.88rem; color:#64748b;">
    {{ __('Published results for') }} <span style="color:#0f172a; font-weight:700;">{{ $studentModel->first_name }} {{ $studentModel->last_name }}</span>
</div>

@if($results->isEmpty())
    <div class="pp-card" style="padding:44px; text-align:center;">
        <div style="width:54px; height:54px; border-radius:50%; background:#f1f5f9; display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px;">
            <svg width="26" height="26" style="color:#94a3b8;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <h3 style="font-size:1rem; font-weight:600; color:#334155;">{{ __('No report cards yet') }}</h3>
        <p style="font-size:0.84rem; color:#64748b; margin-top:6px;">{{ __('Report cards appear here once the school publishes them.') }}</p>
    </div>
@else
    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:16px;">
        @foreach($results as $r)
            @php
                $avg = (float) $r->term_average;
                $avgColor = $avg >= 10 ? '#16a34a' : '#dc2626';
            @endphp
            <a href="{{ route('parent.report-cards.show', [$studentModel->id, $r->id]) }}" class="pp-card" style="display:block; padding:20px; transition:transform 0.15s, box-shadow 0.15s;"
               onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 24px rgba(0,0,0,0.1)'"
               onmouseout="this.style.transform='none'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.07)'">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px;">
                    <div>
                        <div style="font-size:0.95rem; font-weight:700; color:#0f172a;">{{ $r->term->name ?? '—' }}</div>
                        <div style="font-size:0.76rem; color:#64748b; margin-top:2px;">{{ $r->enrollment->academicSession->name ?? '' }}</div>
                        <div style="font-size:0.74rem; color:#94a3b8; margin-top:2px;">{{ $r->enrollment->classSection->name ?? $r->enrollment->classSection->form->name ?? '' }}</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:1.4rem; font-weight:800; color:{{ $avgColor }}; line-height:1;">{{ number_format($avg, 2) }}</div>
                        <div style="font-size:0.62rem; color:#94a3b8; text-transform:uppercase; margin-top:2px;">{{ __('Average') }}</div>
                    </div>
                </div>
                <div style="display:flex; gap:14px; padding-top:12px; border-top:1px solid #f1f5f9;">
                    <div>
                        <div style="font-size:0.68rem; color:#94a3b8; text-transform:uppercase;">{{ __('Rank') }}</div>
                        <div style="font-size:0.86rem; font-weight:700; color:#1e293b;">{{ $r->class_rank ?? '—' }}<span style="font-size:0.7rem; color:#94a3b8;">/{{ $r->total_students ?? '—' }}</span></div>
                    </div>
                    <div>
                        <div style="font-size:0.68rem; color:#94a3b8; text-transform:uppercase;">{{ __('Grade') }}</div>
                        <div style="font-size:0.86rem; font-weight:700; color:#1e293b;">{{ $r->overall_grade ?? '—' }}</div>
                    </div>
                    <div style="margin-left:auto; align-self:flex-end;">
                        <span style="font-size:0.74rem; color:#14b8a6; font-weight:600;">{{ __('Open') }} →</span>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
