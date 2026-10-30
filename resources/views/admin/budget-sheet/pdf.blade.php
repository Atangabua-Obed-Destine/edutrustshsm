<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $budget->title }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        .header { text-align: center; border-bottom: 2px double #1e293b; padding-bottom: 6px; margin-bottom: 8px; }
        .header .school { font-size: 15px; font-weight: 800; }
        .header .title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }
        .header .period { font-size: 9px; color: #555; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th, table.sheet td { padding: 3px 5px; border-bottom: 1px solid #e5e7eb; }
        table.sheet th { font-size: 8px; text-transform: uppercase; color: #555; border-bottom: 2px solid #999; }
        tr.section td { background: #1e293b; color: #fff; font-weight: 700; text-transform: uppercase; font-size: 8px; }
        tr.header td { background: #eef2f7; font-weight: 700; }
        tr.subtotal td { background: #f8fafc; font-style: italic; color: #555; }
        tr.total td { background: #f1f5f9; font-weight: 700; border-top: 1.5px solid #999; }
        .tabular-nums { font-variant-numeric: tabular-nums; }
        .text-right { text-align: right; } .text-left { text-align: left; }
        .pl-6 { padding-left: 14px !important; }
        .font-mono { font-family: DejaVu Sans Mono, monospace; color: #666; }
        .text-emerald-700 { color: #15803d; } .text-red-600 { color: #dc2626; } .text-gray-300 { color: #cbd5e1; } .text-gray-500 { color: #6b7280; }
        .text-xs, .text-\[11px\] { font-size: 8px; }
        details { display: none; }
        .signatures { width: 100%; margin-top: 24px; }
        .signatures td { width: 33%; text-align: center; font-size: 8px; color: #555; padding-top: 28px; }
        .line { border-top: 1px solid #999; margin: 0 20px; padding-top: 3px; }
        .note { margin-top: 8px; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school">{{ $school->school_name ?? '' }}</div>
        <div class="title">{{ __('Income & Expenditure') }} — {{ $budget->fiscal_year }}</div>
        <div class="period">{{ $budget->start_date->format('d M Y') }} — {{ $budget->end_date->format('d M Y') }} · {{ str_replace('_', ' ', $budget->status) }} · {{ __('Printed :date', ['date' => now()->format('d M Y')]) }}</div>
    </div>

    @include('admin.budget-sheet._table', ['print' => true, 'editable' => false])

    @if(array_sum($unallocated) > 0)
        <p class="note">{{ __(':amount moved in this period but sits on no line.', ['amount' => number_format(array_sum($unallocated))]) }} {{ collect($unallocated)->map(fn ($v, $k) => __($k).': '.number_format($v))->join(' · ') }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td><div class="line">{{ __('Bursar') }}</div></td>
            <td><div class="line">{{ __('Checked by') }}</div></td>
            <td><div class="line">{{ __('Head of School') }}</div></td>
        </tr>
    </table>
</body>
</html>
