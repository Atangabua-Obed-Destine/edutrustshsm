<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Cash Book') }} — {{ $month->translatedFormat('F Y') }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .school { text-align: center; margin-bottom: 10px; }
        .school .name { font-size: 12px; font-weight: bold; }
        .school .meta { font-size: 8px; color: #64748b; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 3px 4px; text-align: left; vertical-align: top; }
        thead th { background: #f1f5f9; font-size: 8px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #cbd5e1; }
        tbody td { border-bottom: 1px solid #e2e8f0; }
        tfoot td { border-top: 1px solid #94a3b8; font-weight: bold; }
        .right { text-align: right; }
        .in { color: #047857; }
        .out { color: #b91c1c; }
        .neg { color: #b91c1c; }
        .bf td { font-style: italic; color: #64748b; background: #f8fafc; }
        .cols { width: 100%; margin-top: 10px; }
        .cols td { vertical-align: top; width: 50%; padding: 0 6px 0 0; border: 0; }
        .cols table { margin-top: 0; }
        .sign { margin-top: 26px; width: 100%; }
        .sign td { width: 33%; text-align: center; font-size: 8px; color: #64748b; padding-top: 22px; border-top: 1px solid #cbd5e1; border-bottom: 0; }
        .foot { margin-top: 14px; font-size: 7px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

<div class="school">
    <div class="name">{{ $school?->school_name ?? __('School') }}</div>
    @if($school?->address || $school?->city)
        <div class="meta">{{ collect([$school?->address, $school?->city, $school?->region])->filter()->join(' · ') }}</div>
    @endif
</div>

<h1>{{ __('Cash Book') }} — {{ $month->translatedFormat('F Y') }}</h1>
<div class="muted">{{ __('Year :label, from :start', ['label' => $year['label'], 'start' => $year['start']->format('d M Y')]) }} · {{ $currency }}</div>

<table>
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Reference') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Heading') }}</th>
            <th class="right">{{ __('In') }}</th>
            <th class="right">{{ __('Out') }}</th>
            <th class="right">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr class="bf">
            <td>{{ $month->copy()->startOfMonth()->format('d/m/Y') }}</td>
            <td></td>
            <td colspan="4">{{ __('Balance brought forward') }}</td>
            <td class="right {{ $book['brought_forward'] < 0 ? 'neg' : '' }}">{{ number_format($book['brought_forward'], 0) }}</td>
        </tr>
        @forelse($book['rows'] as $row)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
            <td>{{ $row['ref'] }}</td>
            <td>{{ $row['description'] }}@if($row['party']) · {{ $row['party'] }}@endif</td>
            <td>{{ $row['heading'] }}</td>
            <td class="right in">{{ $row['direction'] === 'in' ? number_format($row['amount'], 0) : '' }}</td>
            <td class="right out">{{ $row['direction'] === 'out' ? number_format($row['amount'], 0) : '' }}</td>
            <td class="right {{ $row['balance'] < 0 ? 'neg' : '' }}">{{ number_format($row['balance'], 0) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" class="muted" style="text-align:center;padding:12px;">{{ __('No money moved this month.') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4">{{ __('Totals for :month', ['month' => $month->translatedFormat('F Y')]) }}</td>
            <td class="right in">{{ number_format($book['total_in'], 0) }}</td>
            <td class="right out">{{ number_format($book['total_out'], 0) }}</td>
            <td class="right {{ $book['closing'] < 0 ? 'neg' : '' }}">{{ number_format($book['closing'], 0) }}</td>
        </tr>
    </tfoot>
</table>

<table class="cols">
    <tr>
        @foreach([['in', __('Money in, by heading')], ['out', __('Money out, by heading')]] as [$side, $title])
        <td>
            <table>
                <thead><tr><th>{{ $title }}</th><th class="right">{{ $currency }}</th></tr></thead>
                <tbody>
                    @forelse($book['headings'][$side] as $heading => $amount)
                        <tr><td>{{ $heading }}</td><td class="right">{{ number_format($amount, 0) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="muted">{{ __('Nothing this month.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
        @endforeach
    </tr>
</table>

<table class="sign">
    <tr>
        <td>{{ __('Bursar') }}</td>
        <td>{{ __('Checked by') }}</td>
        <td>{{ __('Head of School') }}</td>
    </tr>
</table>

<div class="foot">{{ __('Printed :date', ['date' => now()->format('d M Y H:i')]) }}</div>
</body>
</html>
