<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Trends & Analysis') }} — {{ $year['label'] }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 14px 0 4px; color: #1f3a5f; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; }
        .muted { color: #64748b; }
        .note { font-size: 8px; color: #64748b; margin: 3px 0 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { padding: 3px 4px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; font-size: 8px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        tbody td { border-bottom: 1px solid #e2e8f0; }
        tfoot td { border-top: 1px solid #94a3b8; font-weight: bold; }
        .right { text-align: right; }
        .in { color: #047857; }
        .out, .neg { color: #b91c1c; }
        .tiles td { width: 20%; border: 1px solid #e2e8f0; padding: 5px; }
        .tiles .label { font-size: 7px; text-transform: uppercase; color: #64748b; }
        .tiles .value { font-size: 12px; font-weight: bold; }
        .check-ok { color: #047857; }
        .check-no { color: #b45309; }
        .chart { width: 100%; margin-top: 4px; }
        .page-break { page-break-before: always; }
        .foot { margin-top: 14px; font-size: 7px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
@php
    $fmt = fn ($n) => number_format((float) $n, 0);
    $a = $analysis;
    $c = $collection;
    $pacing = $a['pacing'];
@endphp

@include('admin.documents._letterhead')

<h1>{{ __('Trends & Analysis') }} · {{ $year['label'] }}</h1>
<p class="muted">{{ $year['start']->format('d/m/Y') }} – {{ $year['end']->format('d/m/Y') }} · {{ __('Amounts in :currency', ['currency' => $currency]) }} · {{ __('Prepared :when', ['when' => now()->format('d/m/Y H:i')]) }}</p>

<h2>{{ __('Can these figures be taken into a meeting?') }}</h2>
<table>
    @foreach($checks as $check)
        <tr><td style="width: 14px;" class="{{ $check['ok'] ? 'check-ok' : 'check-no' }}">{{ $check['ok'] ? '✓' : '!' }}</td><td><b>{{ $check['label'] }}.</b> {{ $check['detail'] }}</td></tr>
    @endforeach
</table>

@if($a['has_data'])
<table class="tiles" style="margin-top: 10px;">
    <tr>
        @foreach([[__('Opening balance'), $a['opening']], [__('Money in'), $a['total_in']], [__('Money out'), $a['total_out']], [__('Net for the year'), $a['net']], [__('Lowest month-end'), $a['lowest']['balance'] ?? 0]] as [$label, $value])
            <td><div class="label">{{ $label }}</div><div class="value {{ $value < 0 ? 'neg' : '' }}">{{ $fmt($value) }}</div></td>
        @endforeach
    </tr>
</table>

<h2>{{ __('Cash position, month by month') }}</h2>
@if(! empty($charts['cash']))<img class="chart" src="{{ $charts['cash'] }}">@endif
<table>
    <thead><tr><th>{{ __('Month') }}</th><th class="right">{{ __('In') }}</th><th class="right">{{ __('Of which fees') }}</th><th class="right">{{ __('Out') }}</th><th class="right">{{ __('Net') }}</th><th class="right">{{ __('Balance') }}</th></tr></thead>
    <tbody>
        @foreach($a['months'] as $m)
        <tr>
            <td>{{ $m['label'] }}</td>
            <td class="right in">{{ $m['in'] ? $fmt($m['in']) : '—' }}</td>
            <td class="right muted">{{ $m['fees'] ? $fmt($m['fees']) : '—' }}</td>
            <td class="right out">{{ $m['out'] ? $fmt($m['out']) : '—' }}</td>
            <td class="right {{ $m['net'] < 0 ? 'neg' : '' }}">{{ $fmt($m['net']) }}</td>
            <td class="right {{ $m['balance'] < 0 ? 'neg' : '' }}"><b>{{ $fmt($m['balance']) }}</b></td>
        </tr>
        @endforeach
    </tbody>
</table>

@if(! empty($charts['monthly']))
<h2>{{ __('Money in and money out, month by month') }}</h2>
<img class="chart" src="{{ $charts['monthly'] }}">
@endif

<h2>{{ __('Spending against the year') }}</h2>
@if(! $pacing)
    <p class="muted">{{ __('No Income & Expenditure sheet covers this year, so there is no budget to measure spending against.') }}</p>
@else
    @if(! empty($charts['pacing']))<img class="chart" src="{{ $charts['pacing'] }}" style="width: 70%;">@endif
    <table>
        <tr><td>{{ __('Year gone') }}</td><td class="right">{{ number_format($pacing['elapsed'], 0) }}%</td><td>{{ __('Budget spent') }}</td><td class="right">{{ number_format($pacing['consumed'], 0) }}%</td></tr>
        <tr><td>{{ __('Spent') }}</td><td class="right">{{ $fmt($pacing['spent']) }} / {{ $fmt($pacing['budgeted']) }}</td><td>{{ __('At this rate, the year ends at') }}</td><td class="right">{{ $fmt($pacing['projected']) }}</td></tr>
    </table>
    @if($pacing['status'] === 'incomplete')
        <p class="note">{{ __('The sheet looks unfinished, so the comparison is not reliable yet: only :covered of the :lines lines that spent money have a budget figure.', ['covered' => $pacing['lines_budgeted'], 'lines' => $pacing['lines_spending']]) }}</p>
    @endif
@endif

<div class="page-break"></div>

<table style="margin-top: 0;"><tr>
    @foreach([[__('Where the money comes from'), $a['top_in']], [__('Where the money goes'), $a['top_out']]] as [$title, $rows])
    <td style="width: 50%; border: 0; padding: 0 6px 0 0;">
        <h2 style="margin-top: 0;">{{ $title }}</h2>
        <table>
            @forelse($rows as $r)
                <tr><td>{{ $r['heading'] }}</td><td class="right muted">{{ number_format($r['share'], 0) }}%</td><td class="right">{{ $fmt($r['amount']) }}</td></tr>
            @empty
                <tr><td class="muted">{{ __('Nothing yet.') }}</td></tr>
            @endforelse
        </table>
    </td>
    @endforeach
</tr></table>

@if($a['movers'])
<h2>{{ __('What changed, :from to :to', ['from' => $a['movers']['from'], 'to' => $a['movers']['to']]) }}</h2>
<table>
    <thead><tr><th>{{ __('Heading') }}</th><th class="right">{{ $a['movers']['from'] }}</th><th class="right">{{ $a['movers']['to'] }}</th><th class="right">{{ __('Change') }}</th></tr></thead>
    <tbody>
        @foreach($a['movers']['rows'] as $r)
        <tr><td>{{ $r['heading'] }} <span class="muted">({{ $r['direction'] === 'in' ? __('in') : __('out') }})</span></td><td class="right">{{ $fmt($r['previous']) }}</td><td class="right">{{ $fmt($r['current']) }}</td><td class="right"><b>{{ $r['change'] > 0 ? '+' : '−' }}{{ $fmt(abs($r['change'])) }}</b></td></tr>
        @endforeach
    </tbody>
</table>
@endif

<h2>{{ __('Largest single movements') }}</h2>
<table>
    <tbody>
        @foreach($a['largest'] as $row)
        <tr><td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td>{{ $row['ref'] }}</td><td>{{ $row['description'] }}</td><td class="muted">{{ $row['heading'] }}</td><td class="right {{ $row['direction'] === 'in' ? 'in' : 'out' }}">{{ $row['direction'] === 'in' ? '+' : '−' }}{{ $fmt($row['amount']) }}</td></tr>
        @endforeach
    </tbody>
</table>

@if($a['unanalysed']['count'] > 0)
<h2>{{ __('Money on no budget line') }}</h2>
<p class="note">{{ __(':amount, :share% of all the money that moved. It is in the book, but the Income & Expenditure sheet leaves it out, so the sheet understates the year by this much.', ['amount' => $fmt($a['unanalysed']['amount']), 'share' => number_format($a['unanalysed']['share'], 0)]) }}</p>
@endif
@endif

<h2>{{ __('Who has paid, and who has not') }}</h2>
@if(! ($c['has_data'] ?? false))
    <p class="muted">{{ __('No fees have been billed for this year yet.') }}</p>
@else
    @php $p = $c['pupils']; @endphp
    <table class="tiles">
        <tr>
            <td><div class="label">{{ __('Students billed') }}</div><div class="value">{{ $p['pupils'] }}</div></td>
            <td><div class="label">{{ __('Paid in full') }}</div><div class="value in">{{ $p['full'] }}</div></td>
            <td><div class="label">{{ __('Paid in part') }}</div><div class="value">{{ $p['part'] }}</div></td>
            <td><div class="label">{{ __('Paid nothing') }}</div><div class="value out">{{ $p['nothing'] }}</div></td>
            <td><div class="label">{{ __('Collected') }}</div><div class="value">{{ number_format($p['rate'], 0) }}%</div></td>
        </tr>
    </table>
    <p class="note">{{ __(':collected collected of :billed billed; :outstanding outstanding.', ['collected' => $fmt($p['collected']), 'billed' => $fmt($p['billed']), 'outstanding' => $fmt($p['outstanding'])]) }}
        @if($c['arrears']['owed'] > 0) {{ trans_choice('{1} On top of this, :count student still owes :amount from an earlier year.|[2,*] On top of this, :count students still owe :amount from earlier years.', $c['arrears']['pupils'], ['count' => $c['arrears']['pupils'], 'amount' => $fmt($c['arrears']['owed'])]) }} @endif
    </p>
    <table>
        <thead><tr><th>{{ __('Class') }}</th><th class="right">{{ __('Students') }}</th><th class="right">{{ __('Full') }}</th><th class="right">{{ __('Part') }}</th><th class="right">{{ __('None') }}</th><th class="right">{{ __('Outstanding') }}</th><th class="right">{{ __('Rate') }}</th></tr></thead>
        <tbody>
            @foreach($c['by_class'] as $class => $t)
            <tr><td>{{ $class }}</td><td class="right">{{ $t['pupils'] }}</td><td class="right in">{{ $t['full'] }}</td><td class="right">{{ $t['part'] }}</td><td class="right out">{{ $t['nothing'] }}</td><td class="right">{{ $fmt($t['outstanding']) }}</td><td class="right"><b>{{ number_format($t['rate'], 0) }}%</b></td></tr>
            @endforeach
        </tbody>
    </table>
    <table style="margin-top: 8px;">
        <thead><tr><th>{{ __('Fee') }}</th><th class="right">{{ __('Billed') }}</th><th class="right">{{ __('Collected') }}</th><th class="right">{{ __('Outstanding') }}</th><th class="right">{{ __('Rate') }}</th></tr></thead>
        <tbody>
            @foreach($c['by_category'] as $t)
            <tr><td>{{ $t['name'] }}</td><td class="right">{{ $fmt($t['billed']) }}</td><td class="right in">{{ $fmt($t['collected']) }}</td><td class="right">{{ $fmt($t['outstanding']) }}</td><td class="right"><b>{{ number_format($t['rate'], 0) }}%</b></td></tr>
            @endforeach
        </tbody>
    </table>
@endif

<p class="foot">{{ $school?->school_name }} · EduTrust, {{ __('powered by') }} I-NNOVA</p>
</body>
</html>
