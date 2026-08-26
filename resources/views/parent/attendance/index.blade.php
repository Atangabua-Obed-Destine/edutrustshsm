@extends('parent.layouts.app')
@section('title', __('Attendance'))
@section('heading', __('Attendance'))

@section('content')
@php
    $statusColors = [
        'present' => ['#dcfce7', '#16a34a'], 'absent' => ['#fee2e2', '#dc2626'],
        'late' => ['#fef9c3', '#ca8a04'], 'excused' => ['#dbeafe', '#2563eb'],
    ];
@endphp

<div style="margin-bottom:18px; font-size:0.88rem; color:#64748b;">
    {{ __('Attendance record for') }} <span style="color:#0f172a; font-weight:700;">{{ $studentModel->first_name }} {{ $studentModel->last_name }}</span>
</div>

@if(!$enrollment || $summary['total'] === 0)
    <div class="pp-card" style="padding:44px; text-align:center;">
        <p style="font-size:0.88rem; color:#64748b;">{{ __('No attendance has been recorded for this student yet.') }}</p>
    </div>
@else
    {{-- Rate + summary --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:14px; margin-bottom:22px;">
        <div class="pp-card" style="padding:18px; text-align:center; background:linear-gradient(135deg, #0f172a 0%, #134e4a 100%);">
            <div style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase;">{{ __('Attendance Rate') }}</div>
            <div style="font-size:1.8rem; font-weight:800; color:#5eead4; margin-top:4px;">{{ $rate }}%</div>
        </div>
        <div class="pp-card" style="padding:18px; text-align:center;">
            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase;">{{ __('Present') }}</div>
            <div style="font-size:1.5rem; font-weight:700; color:#16a34a; margin-top:4px;">{{ $summary['present'] }}</div>
        </div>
        <div class="pp-card" style="padding:18px; text-align:center;">
            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase;">{{ __('Absent') }}</div>
            <div style="font-size:1.5rem; font-weight:700; color:#dc2626; margin-top:4px;">{{ $summary['absent'] }}</div>
        </div>
        <div class="pp-card" style="padding:18px; text-align:center;">
            <div style="font-size:0.7rem; color:#64748b; text-transform:uppercase;">{{ __('Late') }}</div>
            <div style="font-size:1.5rem; font-weight:700; color:#ca8a04; margin-top:4px;">{{ $summary['late'] }}</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px,1fr)); gap:18px;">
        {{-- Monthly breakdown --}}
        <div class="pp-card" style="padding:20px;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:14px;">{{ __('Monthly Breakdown') }}</h3>
            @forelse($monthly as $m)
                @php $mTotal = $m->present + $m->absent + $m->late; $mRate = $mTotal > 0 ? round((($m->present + $m->late) / $mTotal) * 100) : 0; @endphp
                <div style="margin-bottom:14px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                        <span style="font-size:0.8rem; font-weight:600; color:#1e293b;">{{ $m->label }}</span>
                        <span style="font-size:0.74rem; color:#64748b;">{{ $m->present }}{{ __('P') }} · {{ $m->absent }}{{ __('A') }} · {{ $m->late }}{{ __('L') }}</span>
                    </div>
                    <div style="height:6px; background:#f1f5f9; border-radius:99px; overflow:hidden;">
                        <div style="height:100%; width:{{ $mRate }}%; background:{{ $mRate >= 80 ? '#16a34a' : ($mRate >= 50 ? '#ca8a04' : '#dc2626') }}; border-radius:99px;"></div>
                    </div>
                </div>
            @empty
                <p style="font-size:0.82rem; color:#94a3b8;">{{ __('No monthly data.') }}</p>
            @endforelse
        </div>

        {{-- Recent records --}}
        <div class="pp-card" style="padding:20px;">
            <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:14px;">{{ __('Recent Days') }}</h3>
            @foreach($recent as $r)
                @php $sc = $statusColors[$r->status] ?? ['#f1f5f9', '#475569']; @endphp
                <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px solid #f8fafc;">
                    <span style="font-size:0.82rem; color:#475569;">{{ \Illuminate\Support\Carbon::parse($r->date)->format('D, d M Y') }}</span>
                    <span style="font-size:0.7rem; font-weight:600; padding:3px 10px; border-radius:99px; background:{{ $sc[0] }}; color:{{ $sc[1] }}; text-transform:capitalize;">{{ $r->status }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
@endsection
