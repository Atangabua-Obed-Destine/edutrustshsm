@extends('parent.layouts.app')
@section('title', __('Timetable'))
@section('heading', __('Class Timetable'))

@section('content')
<div style="margin-bottom:18px; font-size:0.88rem; color:#64748b;">
    {{ __('Weekly schedule for') }} <span style="color:#0f172a; font-weight:700;">{{ $studentModel->first_name }} {{ $studentModel->last_name }}</span>
</div>

@if(!$enrollment || $slots->isEmpty())
    <div class="pp-card" style="padding:44px; text-align:center;">
        <p style="font-size:0.88rem; color:#64748b;">{{ __('No timetable has been published for this class yet.') }}</p>
    </div>
@else
    <div class="pp-card" style="overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.8rem; min-width:640px;">
                <thead>
                    <tr style="background:#0f172a; color:#cbd5e1;">
                        <th style="padding:12px 10px; text-align:left; font-size:0.7rem; text-transform:uppercase; position:sticky; left:0; background:#0f172a;">{{ __('Period') }}</th>
                        @foreach($days as $day)
                            <th style="padding:12px 10px; text-align:center; font-size:0.72rem; text-transform:capitalize;">{{ __(ucfirst($day)) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($slots as $slot)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px; background:#f8fafc; position:sticky; left:0;">
                                <div style="font-weight:700; color:#1e293b; font-size:0.78rem;">{{ $slot->label ?: __('Period') . ' ' . $slot->period_number }}</div>
                                <div style="font-size:0.66rem; color:#94a3b8;">{{ \Illuminate\Support\Str::of($slot->start_time)->substr(0,5) }} – {{ \Illuminate\Support\Str::of($slot->end_time)->substr(0,5) }}</div>
                            </td>
                            @foreach($days as $day)
                                @php
                                    $entry = ($entriesByDay[$day] ?? collect())->first(fn ($e) => $e->timetable_slot_id == $slot->id);
                                @endphp
                                <td style="padding:8px; text-align:center; vertical-align:top;">
                                    @if($slot->type === 'break')
                                        <span style="font-size:0.7rem; color:#cbd5e1; font-style:italic;">{{ $slot->label ?: __('Break') }}</span>
                                    @elseif($entry)
                                        <div style="background:#f0fdfa; border:1px solid #ccfbf1; border-radius:7px; padding:7px 6px;">
                                            <div style="font-size:0.76rem; font-weight:600; color:#0f766e;">{{ $entry->subject->name ?? '—' }}</div>
                                            @if($entry->teacher)<div style="font-size:0.64rem; color:#64748b; margin-top:2px;">{{ $entry->teacher->full_name }}</div>@endif
                                            @if($entry->room)<div style="font-size:0.62rem; color:#94a3b8;">{{ $entry->room->name }}</div>@endif
                                        </div>
                                    @else
                                        <span style="color:#e2e8f0;">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
