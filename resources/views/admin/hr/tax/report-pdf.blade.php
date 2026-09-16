<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Tax Distribution Report') }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111827; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; margin: 8px 0 2px; }
        .sub { text-align: center; font-size: 9px; color: #4b5563; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; padding: 4px; border-bottom: 1px solid #d1d5db; text-align: left; }
        td { padding: 3px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        tfoot td { font-weight: bold; border-top: 2px solid #374151; }
        h4 { font-size: 10px; margin: 14px 0 4px; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ __('Tax Distribution Report') }}</div>
<div class="sub">{{ __('As of') }} {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }} · {{ __('Recomputed under current tax rules — matches what the payslip would pay today.') }}</div>

<table>
    <thead>
        <tr>
            <th>{{ __('Staff') }}</th>
            <th class="num">{{ __('Gross') }}</th>
            <th class="num">{{ __('Emp. Tax') }}</th>
            <th class="num">{{ __('Empr. Tax') }}</th>
            <th class="num">{{ __('Net') }}</th>
            <th class="num">{{ __('Eff. %') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
        <tr>
            <td>{{ $r->staff->full_name }} <span style="color:#9ca3af;">{{ $r->staff->staff_id }}</span></td>
            <td class="num">{{ number_format($r->gross, 0, '.', ' ') }}</td>
            <td class="num">{{ number_format($r->employee_tax, 0, '.', ' ') }}</td>
            <td class="num">{{ number_format($r->employer_tax, 0, '.', ' ') }}</td>
            <td class="num">{{ number_format($r->net, 0, '.', ' ') }}</td>
            <td class="num">{{ $r->effective }}%</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center; color:#9ca3af; padding:14px;">{{ __('No staff with salaries configured.') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td>{{ __('Total') }}</td>
            <td class="num">{{ number_format($totals['gross'], 0, '.', ' ') }}</td>
            <td class="num">{{ number_format($totals['employee_tax'], 0, '.', ' ') }}</td>
            <td class="num">{{ number_format($totals['employer_tax'], 0, '.', ' ') }}</td>
            <td class="num">{{ number_format($totals['net'], 0, '.', ' ') }} {{ $currency }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<h4>{{ __('By Salary Band') }}</h4>
<table style="width: 60%;">
    @foreach($distribution as $b)
    <tr>
        <td>{{ $b->label }} <span style="color:#9ca3af;">({{ $b->count }})</span></td>
        <td class="num">{{ number_format($b->employee_tax, 0, '.', ' ') }}</td>
    </tr>
    @endforeach
</table>

</body>
</html>
