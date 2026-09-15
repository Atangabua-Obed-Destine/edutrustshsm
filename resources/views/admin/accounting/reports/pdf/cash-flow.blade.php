<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $comparative ? __('Comparative Cash Flow Statement') : __('Cash Flow Statement') }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; margin: 10px 0 2px; }
        .sub { text-align: center; font-size: 9px; color: #4b5563; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; padding: 5px; border-bottom: 1px solid #d1d5db; text-align: right; }
        td { padding: 4px 5px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .section td { font-weight: bold; background: #fafafa; padding-top: 8px; }
        .item td:first-child { padding-left: 16px; }
        .total td { font-weight: bold; border-bottom: 1px solid #d1d5db; }
        .foot td { font-weight: bold; border-top: 2px solid #374151; }
        .warn { color: #b91c1c; margin-top: 8px; }
    </style>
</head>
<body>
@php
    $current = $comparative ? $report['current'] : $report;
    $previous = $comparative ? $report['previous'] : null;
    $money = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $cols = $comparative ? 4 : 2;
@endphp

@include('admin.documents._letterhead')

<div class="title">{{ $comparative ? __('Comparative Cash Flow Statement') : __('Cash Flow Statement') }}</div>
<div class="sub">
    {{ __(':from to :to', ['from' => \Illuminate\Support\Carbon::parse($current['from'])->format('d/m/Y'), 'to' => \Illuminate\Support\Carbon::parse($current['to'])->format('d/m/Y')]) }}
    @if($comparative)
    · {{ __('compared with :from to :to', ['from' => \Illuminate\Support\Carbon::parse($previous['from'])->format('d/m/Y'), 'to' => \Illuminate\Support\Carbon::parse($previous['to'])->format('d/m/Y')]) }}
    @endif
    · {{ $currency }}
</div>

<table>
    <thead>
        <tr>
            <th style="text-align:left;"></th>
            <th>{{ $comparative ? __('Current') : __('Amount') }}</th>
            @if($comparative)<th>{{ __('Previous') }}</th><th>{{ __('Variance') }}</th>@endif
        </tr>
    </thead>
    <tbody>
        @foreach($current['sections'] as $key => $section)
        <tr class="section"><td colspan="{{ $cols }}">{{ $section['title'] }}</td></tr>
        @foreach($section['items'] as $item)
        @php $prior = $comparative ? collect($previous['sections'][$key]['items'])->firstWhere('key', $item['key']) : null; @endphp
        <tr class="item">
            <td>{{ $item['label'] }}</td>
            <td class="num">{{ $money($item['amount']) }}</td>
            @if($comparative)
            <td class="num">{{ $money($prior['amount'] ?? 0) }}</td>
            <td class="num">{{ $money($report['variance'][$key][$item['key']] ?? 0) }}</td>
            @endif
        </tr>
        @endforeach
        <tr class="total">
            <td>{{ __('Net cash from this activity') }}</td>
            <td class="num">{{ $money($section['total']) }}</td>
            @if($comparative)
            <td class="num">{{ $money($previous['sections'][$key]['total']) }}</td>
            <td class="num">{{ $money($report['variance'][$key]['_total']) }}</td>
            @endif
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        @foreach([[__('Net change in cash'), 'net_change'], [__('Cash at the start of the period'), 'opening_cash'], [__('Cash at the end of the period'), 'closing_cash']] as [$label, $field])
        <tr class="foot">
            <td>{{ $label }}</td>
            <td class="num">{{ $money($current[$field]) }}</td>
            @if($comparative)
            <td class="num">{{ $money($previous[$field]) }}</td>
            <td class="num">{{ $money($current[$field] - $previous[$field]) }}</td>
            @endif
        </tr>
        @endforeach
    </tfoot>
</table>

@unless($current['balanced'])
<p class="warn">{{ __('The statement is out by :amount. Every non-cash account is included, so this can only come from a posted journal entry whose debits and credits do not match. Check the trial balance.', ['amount' => $money($current['difference'])]) }}</p>
@endunless
</body>
</html>
