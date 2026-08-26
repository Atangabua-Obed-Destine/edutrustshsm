@extends('parent.layouts.app')
@section('title', $studentModel->first_name . ' ' . $studentModel->last_name)
@section('heading', __('Student Overview'))

@section('content')
@php
    $currency = (\App\Models\SchoolSetting::current()->currency ?? 'FCFA');
    $class = $enrollment?->classSection?->name ?? $enrollment?->classSection?->form?->name ?? __('Not enrolled');
    $teacher = $enrollment?->classSection?->classTeacher;
@endphp

{{-- Profile header --}}
<div class="pp-card" style="overflow: hidden; margin-bottom: 22px;">
    <div style="padding: 24px; display: flex; align-items: center; gap: 18px; background: linear-gradient(135deg, #0f172a 0%, #134e4a 100%); flex-wrap: wrap;">
        @if($studentModel->photo)
            <img src="{{ asset('storage/' . $studentModel->photo) }}" alt="" style="width: 72px; height: 72px; border-radius: 14px; object-fit: cover; border: 2px solid rgba(255,255,255,0.2);">
        @else
            <div style="width: 72px; height: 72px; border-radius: 14px; background: rgba(20,184,166,0.25); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; color: #5eead4;">{{ mb_substr($studentModel->first_name, 0, 1) }}{{ mb_substr($studentModel->last_name, 0, 1) }}</div>
        @endif
        <div style="min-width: 0; flex: 1;">
            <h2 style="font-size: 1.3rem; font-weight: 700; color: #fff;">{{ $studentModel->full_name }}</h2>
            <div style="display: flex; gap: 16px; margin-top: 6px; flex-wrap: wrap;">
                <span style="font-size: 0.78rem; color: #94a3b8;">{{ __('ID') }}: <span style="color:#cbd5e1; font-weight:600;">{{ $studentModel->student_id }}</span></span>
                <span style="font-size: 0.78rem; color: #94a3b8;">{{ __('Class') }}: <span style="color:#cbd5e1; font-weight:600;">{{ $class }}</span></span>
                @if($enrollment?->academicSession)
                    <span style="font-size: 0.78rem; color: #94a3b8;">{{ __('Session') }}: <span style="color:#cbd5e1; font-weight:600;">{{ $enrollment->academicSession->name }}</span></span>
                @endif
            </div>
        </div>
        <span style="font-size:0.72rem; font-weight:600; padding:5px 12px; border-radius:99px; background:{{ $studentModel->status === 'active' ? 'rgba(34,197,94,0.2)' : 'rgba(148,163,184,0.2)' }}; color:{{ $studentModel->status === 'active' ? '#86efac' : '#cbd5e1' }}; text-transform:capitalize;">{{ $studentModel->status }}</span>
    </div>
</div>

{{-- Quick stat cards --}}
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 22px;">
    <a href="{{ route('parent.fees.index', $studentModel->id) }}" class="pp-card" style="padding: 18px; display:block;">
        <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">{{ __('Fee Balance') }}</div>
        <div style="font-size:1.35rem; font-weight:700; color:{{ $feeBalance > 0 ? '#dc2626' : '#16a34a' }}; margin-top:4px;">{{ number_format($feeBalance, 0) }} <span style="font-size:0.8rem; color:#94a3b8;">{{ $currency }}</span></div>
    </a>
    <a href="{{ route('parent.subjects.index', $studentModel->id) }}" class="pp-card" style="padding: 18px; display:block;">
        <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">{{ __('Subjects') }}</div>
        <div style="font-size:1.35rem; font-weight:700; color:#0f172a; margin-top:4px;">{{ $subjectCount }}</div>
    </a>
    <a href="{{ route('parent.report-cards.index', $studentModel->id) }}" class="pp-card" style="padding: 18px; display:block;">
        <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">{{ __('Report Cards') }}</div>
        <div style="font-size:0.92rem; font-weight:600; color:#14b8a6; margin-top:8px;">{{ __('View results') }} →</div>
    </a>
    <a href="{{ route('parent.attendance.index', $studentModel->id) }}" class="pp-card" style="padding: 18px; display:block;">
        <div style="font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:0.05em;">{{ __('Attendance') }}</div>
        <div style="font-size:0.92rem; font-weight:600; color:#14b8a6; margin-top:8px;">{{ __('View record') }} →</div>
    </a>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px;">
    {{-- Personal details --}}
    <div class="pp-card" style="padding: 22px;">
        <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:16px;">{{ __('Personal Details') }}</h3>
        @php
            $details = [
                __('Date of Birth') => $studentModel->date_of_birth?->format('d M Y'),
                __('Gender') => ucfirst($studentModel->gender ?? '—'),
                __('Place of Birth') => $studentModel->place_of_birth,
                __('Nationality') => $studentModel->nationality,
                __('Class Teacher') => $teacher?->full_name,
            ];
        @endphp
        @foreach($details as $label => $value)
            <div style="display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f1f5f9;">
                <span style="font-size:0.82rem; color:#64748b;">{{ $label }}</span>
                <span style="font-size:0.82rem; color:#1e293b; font-weight:600; text-align:right;">{{ $value ?: '—' }}</span>
            </div>
        @endforeach
    </div>

    {{-- Enrollment history (lifecycle) --}}
    <div class="pp-card" style="padding: 22px;">
        <h3 style="font-size:0.95rem; font-weight:700; color:#0f172a; margin-bottom:16px;">{{ __('Enrollment History') }}</h3>
        @if($history->isEmpty())
            <p style="font-size:0.84rem; color:#94a3b8;">{{ __('No enrollment history.') }}</p>
        @else
            <div style="position: relative;">
                @foreach($history as $h)
                    <div style="display:flex; gap:12px; padding-bottom:16px;">
                        <div style="display:flex; flex-direction:column; align-items:center;">
                            <div style="width:11px; height:11px; border-radius:50%; background:{{ $loop->first ? '#14b8a6' : '#cbd5e1' }}; margin-top:4px;"></div>
                            @unless($loop->last)<div style="width:2px; flex:1; background:#e2e8f0; margin-top:2px;"></div>@endunless
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:0.84rem; font-weight:600; color:#1e293b;">{{ $h->academicSession->name ?? '—' }}</div>
                            <div style="font-size:0.76rem; color:#64748b;">
                                {{ $h->classSection->name ?? $h->classSection->form->name ?? '—' }}
                                @if($h->stream) · {{ $h->stream->name }} @endif
                            </div>
                            <span style="display:inline-block; margin-top:4px; font-size:0.68rem; font-weight:600; padding:2px 8px; border-radius:99px; background:#f1f5f9; color:#475569; text-transform:capitalize;">{{ $h->status }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
