<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Academic Record') }} — {{ $student->student_id }}</title>
    <style>
        /* dompdf: tables and floats only — no flexbox, no grid. */
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 5px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        h3 { font-size: 10px; margin: 10px 0 4px; color: #0f172a; }
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
        .stats td { border: 1px solid #e2e8f0; text-align: center; width: 16.6%; }
        .stats .label { font-size: 8px; text-transform: uppercase; color: #64748b; }
        .stats .value { font-size: 13px; font-weight: bold; }
        .fail { color: #b91c1c; }
        .year { margin-top: 12px; }
        .year-head { background: #f8fafc; border: 1px solid #e2e8f0; padding: 5px 6px; font-weight: bold; font-size: 10px; }
        .remark { font-size: 9px; color: #475569; }
        .foot { margin-top: 24px; font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

<div class="school">
    <div class="name">{{ $school?->school_name ?? __('School') }}</div>
    @if($school?->address || $school?->city)
        <div class="meta">{{ collect([$school?->address, $school?->city, $school?->region])->filter()->join(' · ') }}</div>
    @endif
</div>

<h1>{{ __('Academic Record') }}</h1>
<div class="muted">
    {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}
    @if($student->date_of_birth) · {{ __('Born') }} {{ \Illuminate\Support\Carbon::parse($student->date_of_birth)->format('d M Y') }} @endif
</div>

@php($summary = $history['summary'])
@php($passMark = $history['pass_mark'])

<h2>{{ __('Summary') }}</h2>
<table class="stats">
    <tr>
        <td><div class="label">{{ __('Years here') }}</div><div class="value">{{ $summary['years'] }}</div></td>
        <td><div class="label">{{ __('Terms recorded') }}</div><div class="value">{{ $summary['terms'] }}</div></td>
        <td><div class="label">{{ __('Average') }}</div><div class="value">{{ $summary['average'] !== null ? number_format($summary['average'], 2) : '—' }}</div></td>
        <td><div class="label">{{ __('Best term') }}</div><div class="value">{{ $summary['best'] !== null ? number_format($summary['best'], 2) : '—' }}</div></td>
        <td><div class="label">{{ __('Weakest term') }}</div><div class="value">{{ $summary['worst'] !== null ? number_format($summary['worst'], 2) : '—' }}</div></td>
        <td><div class="label">{{ __('Attendance') }}</div><div class="value">{{ $summary['attendance_rate'] !== null ? $summary['attendance_rate'].'%' : '—' }}</div></td>
    </tr>
</table>

<h2>{{ __('Year by year') }}</h2>
@forelse($history['years'] as $year)
    <div class="year">
        <div class="year-head">
            {{ $year['session'] ?? __('Unknown session') }} — {{ $year['class'] ?? '—' }}
            @if($year['stream']) ({{ $year['stream'] }}) @endif
            @if($year['final_average'] !== null)
                · {{ __('Year average') }}: {{ number_format($year['final_average'], 2) }}
            @endif
            @if($year['final_rank']) · {{ __('Position') }}: {{ $year['final_rank'] }} @endif
        </div>

        @if(! $year['has_results'])
            <div class="muted" style="padding:5px 6px;">{{ __('No published results for this year.') }}</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:16%">{{ __('Term') }}</th>
                        <th style="width:10%" class="right">{{ __('Average') }}</th>
                        <th style="width:12%" class="right">{{ __('Class average') }}</th>
                        <th style="width:10%" class="right">{{ __('Position') }}</th>
                        <th style="width:12%">{{ __('Conduct') }}</th>
                        <th>{{ __('Council') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($year['terms'] as $term)
                        <tr>
                            <td>{{ $term['term'] }}</td>
                            <td class="right {{ $term['average'] !== null && $term['average'] < $passMark ? 'fail' : '' }}">
                                {{ $term['average'] !== null ? number_format($term['average'], 2) : '—' }}
                            </td>
                            <td class="right muted">{{ $term['class_average'] !== null ? number_format($term['class_average'], 2) : '—' }}</td>
                            <td class="right">{{ $term['rank'] ? $term['rank'].($term['of'] ? '/'.$term['of'] : '') : '—' }}</td>
                            <td>{{ $term['conduct'] ? __(ucfirst(str_replace('_', ' ', $term['conduct']))) : '—' }}</td>
                            <td>
                                {{ $term['council']['label'] ?? '—' }}
                                @if($term['council']['remark'] ?? null)
                                    <div class="remark">{{ $term['council']['remark'] }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@empty
    <div class="muted">{{ __('This pupil has no enrolment on record yet.') }}</div>
@endforelse

@if(count($history['subjects']))
    <h2>{{ __('Subjects over time') }}</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Subject') }}</th>
                <th style="width:10%" class="right">{{ __('Terms') }}</th>
                <th style="width:12%" class="right">{{ __('Average') }}</th>
                <th style="width:10%" class="right">{{ __('Best') }}</th>
                <th style="width:10%" class="right">{{ __('Worst') }}</th>
                <th style="width:14%" class="right">{{ __('Since the first term') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($history['subjects'] as $subject)
                <tr>
                    <td>{{ $subject['name'] }}</td>
                    <td class="right muted">{{ $subject['terms'] }}</td>
                    <td class="right {{ $subject['average'] < $passMark ? 'fail' : '' }}">{{ number_format($subject['average'], 2) }}</td>
                    <td class="right muted">{{ number_format($subject['best'], 1) }}</td>
                    <td class="right muted">{{ number_format($subject['worst'], 1) }}</td>
                    <td class="right">{{ $subject['change'] !== null ? ($subject['change'] > 0 ? '+' : '').number_format($subject['change'], 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="foot">{{ __('Issued :date', ['date' => now()->format('d M Y')]) }}</div>

</body>
</html>
