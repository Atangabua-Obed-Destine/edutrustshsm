<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Class Council Minutes') }} — {{ $council->reference }}</title>
    <style>
        /* dompdf: keep it to what its renderer handles well — no flexbox, no grid. */
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 16px 0 6px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .school { text-align: center; margin-bottom: 14px; }
        .school .name { font-size: 13px; font-weight: bold; }
        .school .meta { font-size: 9px; color: #64748b; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 5px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        tbody td { border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .centre { text-align: center; }
        .stats td { border: 1px solid #e2e8f0; text-align: center; width: 16.6%; }
        .stats .label { font-size: 8px; text-transform: uppercase; color: #64748b; }
        .stats .value { font-size: 13px; font-weight: bold; }
        .fail { color: #b91c1c; }
        .note { white-space: pre-line; }
        .sign { margin-top: 26px; }
        .sign td { width: 33%; padding-top: 26px; font-size: 9px; }
        .sign .line { border-top: 1px solid #94a3b8; padding-top: 3px; }
        .draft { color: #b45309; font-size: 9px; margin-top: 4px; }
    </style>
</head>
<body>

<div class="school">
    <div class="name">{{ $school?->school_name ?? __('School') }}</div>
    @if($school?->address || $school?->city)
        <div class="meta">{{ collect([$school?->address, $school?->city, $school?->region])->filter()->join(' · ') }}</div>
    @endif
</div>

<h1>{{ __('Class Council Minutes') }}</h1>
<div class="muted">
    {{ $council->classSection?->name }}
    @if($council->classSection?->form) ({{ $council->classSection->form->name }}) @endif
    · {{ $council->term?->name }}
    · {{ $council->reference }}
</div>

@unless($council->isConcluded())
    <div class="draft">{{ __('DRAFT — this council has not been concluded. Its figures may still change.') }}</div>
@endunless

<h2>{{ __('The sitting') }}</h2>
<table>
    <tr>
        <td style="width:50%"><strong>{{ __('Date') }}:</strong> {{ $council->meeting_date?->format('d M Y') ?? '—' }}</td>
        <td><strong>{{ __('Venue') }}:</strong> {{ $council->venue ?: '—' }}</td>
    </tr>
    <tr>
        <td><strong>{{ __('Chaired by') }}:</strong> {{ $council->chair ? $council->chair->first_name.' '.$council->chair->last_name : '—' }}</td>
        <td><strong>{{ __('Secretary') }}:</strong> {{ $council->secretary ? $council->secretary->first_name.' '.$council->secretary->last_name : '—' }}</td>
    </tr>
</table>

<h2>{{ __('What the meeting saw') }}</h2>
<table class="stats">
    <tr>
        <td><div class="label">{{ __('Pupils') }}</div><div class="value">{{ $statistics['students'] ?? '—' }}</div></td>
        <td><div class="label">{{ __('Average') }}</div><div class="value">{{ $statistics['class_average'] !== null ? number_format($statistics['class_average'], 2) : '—' }}</div></td>
        <td><div class="label">{{ __('Pass rate') }}</div><div class="value">{{ $statistics['pass_rate'] !== null ? number_format($statistics['pass_rate'], 1).'%' : '—' }}</div></td>
        <td><div class="label">{{ __('Passed') }}</div><div class="value">{{ $statistics['passed'] ?? '—' }}</div></td>
        <td><div class="label">{{ __('Failed') }}</div><div class="value">{{ $statistics['failed'] ?? '—' }}</div></td>
        <td><div class="label">{{ __('Highest') }}</div><div class="value">{{ $statistics['highest_average'] !== null ? number_format($statistics['highest_average'], 2) : '—' }}</div></td>
    </tr>
</table>

<h2>{{ __('Awards given') }}</h2>
<table>
    <thead>
        <tr>
            @foreach($awards as $value => $label)
                <th class="centre">{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            @foreach($awards as $value => $label)
                <td class="centre"><strong>{{ $tally[$value] ?? 0 }}</strong></td>
            @endforeach
        </tr>
    </tbody>
</table>

<h2>{{ __('Decisions') }}</h2>
<table>
    <thead>
        <tr>
            <th style="width:6%">{{ __('Rank') }}</th>
            <th style="width:28%">{{ __('Pupil') }}</th>
            <th style="width:10%" class="right">{{ __('Average') }}</th>
            <th style="width:18%">{{ __('Award') }}</th>
            <th style="width:12%">{{ __('Conduct') }}</th>
            <th>{{ __('Remark') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($entries as $entry)
            <tr>
                <td>{{ $entry->rank ?? '—' }}</td>
                <td>
                    {{ $entry->student?->first_name }} {{ $entry->student?->last_name }}<br>
                    <span class="muted">{{ $entry->student?->student_id }}</span>
                </td>
                <td class="right {{ $entry->award === 'work_warning' ? 'fail' : '' }}">
                    {{ $entry->average !== null ? number_format($entry->average, 2) : '—' }}
                </td>
                <td>{{ $awards[$entry->award] ?? $entry->award }}</td>
                <td>{{ $entry->conduct ? __(ucfirst(str_replace('_', ' ', $entry->conduct))) : '—' }}</td>
                <td class="note">{{ $entry->remark }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if($council->deliberations)
    <h2>{{ __('What was discussed') }}</h2>
    <div class="note">{{ $council->deliberations }}</div>
@endif

@if($council->action_items)
    <h2>{{ __('What happens next') }}</h2>
    <div class="note">{{ $council->action_items }}</div>
@endif

<h2>{{ __('Who sat') }}</h2>
@if($council->signatures->isNotEmpty())
    <table class="sign">
        @foreach($council->signatures->chunk(3) as $row)
            <tr>
                @foreach($row as $signature)
                    <td><div class="line">{{ $signature->name }}@if($signature->role)<br><span class="muted">{{ $signature->role }}</span>@endif</div></td>
                @endforeach
                @for($i = $row->count(); $i < 3; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>
@else
    <div class="muted">{{ __('Nobody was recorded as sitting on this council.') }}</div>
@endif

</body>
</html>
