<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('State of the School') }} — {{ $term->name }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.5; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 5px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        .school { text-align: center; margin-bottom: 12px; }
        .school .name { font-size: 13px; font-weight: bold; }
        .school .meta { font-size: 9px; color: #64748b; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 5px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        tbody td { border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .centre { text-align: center; }
        .stats td { border: 1px solid #e2e8f0; text-align: center; }
        .stats .label { font-size: 8px; text-transform: uppercase; color: #64748b; }
        .stats .value { font-size: 13px; font-weight: bold; }
        .good { color: #047857; }
        .bad { color: #b91c1c; }
        .warn { color: #b45309; }
        ul.points { margin: 4px 0 0; padding-left: 14px; }
        ul.points li { margin-bottom: 4px; }
        .foot { margin-top: 24px; font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

@php
    $enrolment = $report['enrolment'];
    $academics = $report['academics'];
    $attendance = $report['attendance'];
    $finance = $report['finance'];
    $councils = $report['councils'];

    // A lookup rather than a match inside the expression directive: that
    // form cannot carry a braced expression and compiles to a broken
    // opening tag, which fails only when the page is opened.
    $tones = ['good' => 'good', 'bad' => 'bad', 'warning' => 'warn'];
@endphp

<div class="school">
    <div class="name">{{ $school?->school_name ?? __('School') }}</div>
    @if($school?->address || $school?->city)
        <div class="meta">{{ collect([$school?->address, $school?->city, $school?->region])->filter()->join(' · ') }}</div>
    @endif
</div>

<h1>{{ __('State of the School') }}</h1>
<div class="muted">
    {{ $term->name }}
    @if($report['session']) · {{ $report['session']->name }} @endif
    · {{ $report['window']['from']->format('d M Y') }} – {{ $report['window']['to']->format('d M Y') }}
</div>

@if(count($report['highlights']))
    <h2>{{ __('What this term comes down to') }}</h2>
    <ul class="points">
        @foreach($report['highlights'] as $highlight)
            <li class="{{ $tones[$highlight['tone']] ?? '' }}">{{ $highlight['text'] }}</li>
        @endforeach
    </ul>
@endif

<h2>{{ __('Who is here') }}</h2>
<table class="stats">
    <tr>
        <td><div class="label">{{ __('On the roll') }}</div><div class="value">{{ $enrolment['active'] }}</div></td>
        <td><div class="label">{{ __('Admitted') }}</div><div class="value good">+{{ $enrolment['admitted'] }}</div></td>
        <td><div class="label">{{ __('Left') }}</div><div class="value {{ $enrolment['left'] > 0 ? 'bad' : '' }}">−{{ $enrolment['left'] }}</div></td>
        <td><div class="label">{{ __('Net change') }}</div><div class="value">{{ $enrolment['net'] >= 0 ? '+' : '' }}{{ $enrolment['net'] }}</div></td>
    </tr>
</table>

@if(count($enrolment['by_form']))
    <table style="margin-top:8px;">
        <thead>
            <tr>@foreach($enrolment['by_form'] as $form => $count)<th class="centre">{{ $form }}</th>@endforeach</tr>
        </thead>
        <tbody>
            <tr>@foreach($enrolment['by_form'] as $form => $count)<td class="centre"><strong>{{ $count }}</strong></td>@endforeach</tr>
        </tbody>
    </table>
@endif

<h2>{{ __('Results, attendance and money') }}</h2>
<table>
    <thead>
        <tr>
            <th style="width:34%">{{ __('Measure') }}</th>
            <th style="width:22%" class="right">{{ $term->name }}</th>
            <th style="width:22%" class="right">{{ $report['previous_term']?->name ?? '—' }}</th>
            <th class="right">{{ __('Change') }}</th>
        </tr>
    </thead>
    <tbody>
        @php
            $rows = [
                [__('Average'), $academics['average'], 'average', 2, ''],
                [__('Pass rate'), $academics['pass_rate'], 'pass_rate', 1, '%'],
                [__('Attendance'), $attendance['rate'], 'attendance', 1, '%'],
                [__('Fee collection'), $finance['collection_rate'], 'collection_rate', 1, '%'],
                [__('Surplus'), $finance['surplus'], 'surplus', 0, ''],
            ];
        @endphp
        @foreach($rows as [$label, $value, $key, $dp, $suffix])
            @php($cmp = $report['comparison'][$key] ?? null)
            <tr>
                <td>{{ $label }}</td>
                <td class="right"><strong>{{ $value !== null ? number_format($value, $dp).$suffix : '—' }}</strong></td>
                <td class="right muted">{{ ($cmp['then'] ?? null) !== null ? number_format($cmp['then'], $dp).$suffix : '—' }}</td>
                <td class="right {{ ($cmp['change'] ?? 0) > 0 ? 'good' : (($cmp['change'] ?? 0) < 0 ? 'bad' : '') }}">
                    {{ ($cmp['change'] ?? null) !== null ? (($cmp['change'] > 0 ? '+' : '').number_format($cmp['change'], $dp)) : '—' }}
                </td>
            </tr>
        @endforeach
        <tr>
            <td>{{ __('Pupils needing attention') }}</td>
            <td class="right"><strong>{{ $academics['at_risk'] }}</strong></td>
            <td class="right muted">—</td>
            <td class="right">—</td>
        </tr>
        <tr>
            <td>{{ __('Fees outstanding') }}</td>
            <td class="right"><strong>{{ number_format($finance['outstanding'], 0) }}</strong></td>
            <td class="right muted">—</td>
            <td class="right">—</td>
        </tr>
        <tr>
            <td>{{ __('Staff share of income') }}</td>
            <td class="right"><strong>{{ $finance['staff_share'] !== null ? $finance['staff_share'].'%' : '—' }}</strong></td>
            <td class="right muted">—</td>
            <td class="right">—</td>
        </tr>
    </tbody>
</table>

@if(count($academics['struggling_subjects']))
    <h2>{{ __('Subjects to look at') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Subject') }}</th>
                <th style="width:16%" class="right">{{ __('Average') }}</th>
                <th style="width:16%" class="right">{{ __('Pass rate') }}</th>
                <th style="width:26%" class="right">{{ __('Against pupil average') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($academics['struggling_subjects'] as $subject)
                <tr>
                    <td>{{ $subject['name'] }}</td>
                    <td class="right">{{ number_format($subject['average'], 2) }}</td>
                    <td class="right">{{ $subject['pass_rate'] !== null ? $subject['pass_rate'].'%' : '—' }}</td>
                    <td class="right {{ $subject['delta'] < 0 ? 'bad' : 'good' }}">
                        {{ $subject['delta'] > 0 ? '+' : '' }}{{ number_format($subject['delta'], 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if($councils['held'] > 0)
    <h2>{{ __('Class councils') }}</h2>
    <div class="muted">
        {{ __(':held held, :concluded concluded.', ['held' => $councils['held'], 'concluded' => $councils['concluded']]) }}
    </div>
    @if(count($councils['awards']))
        <table style="margin-top:6px;">
            <thead>
                <tr>@foreach($councils['awards'] as $award => $count)<th class="centre">{{ \App\Models\ClassCouncilEntry::awards()[$award] ?? $award }}</th>@endforeach</tr>
            </thead>
            <tbody>
                <tr>@foreach($councils['awards'] as $award => $count)<td class="centre"><strong>{{ $count }}</strong></td>@endforeach</tr>
            </tbody>
        </table>
    @endif
@endif

<div class="foot">
    {{ __('Prepared :date from the school register. Academic figures cover :coverage% of pupils on the roll.', [
        'date' => now()->format('d M Y'),
        'coverage' => $academics['coverage'] ?? 0,
    ]) }}
</div>

</body>
</html>
