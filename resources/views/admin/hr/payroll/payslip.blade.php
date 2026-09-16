<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Payslip') }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .slip { page-break-after: always; }
        .slip:last-child { page-break-after: auto; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 6px 0 2px; }
        .sub { text-align: center; font-size: 9.5px; color: #4b5563; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 3px 5px; border-bottom: 1px solid #f3f4f6; }
        .label { color: #6b7280; width: 22%; }
        .lines th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase; padding: 5px; text-align: left; border-bottom: 1px solid #d1d5db; }
        .lines td { padding: 4px 5px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .section td { font-weight: bold; background: #fafafa; }
        .net td { font-weight: bold; font-size: 12px; border-top: 2px solid #374151; padding-top: 6px; }
        .note { font-size: 8.5px; color: #92400e; margin-top: 6px; }
        .sign { margin-top: 30px; }
    </style>
</head>
<body>

@foreach($slips as $slip)
@php
    $p = $slip->payroll;
    $u = $p->user;
    $money = fn ($v) => number_format((float) $v, 0, '.', ' ');
    $month = \Illuminate\Support\Carbon::parse($p->salary_month.'-01')->translatedFormat('F Y');
@endphp
<div class="slip">
    @include('admin.documents._letterhead')

    <div class="title">{{ __('Payslip') }}</div>
    <div class="sub">{{ $month }}</div>

    <table class="info">
        <tr>
            <td class="label">{{ __('Staff') }}</td><td><strong>{{ $u?->full_name ?? '—' }}</strong></td>
            <td class="label">{{ __('Staff ID') }}</td><td>{{ $u?->staff_id ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Department') }}</td><td>{{ $u?->department?->name ?? '—' }}</td>
            <td class="label">{{ __('Designation') }}</td><td>{{ $u?->designation?->title ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">{{ __('Status') }}</td><td>{{ $p->status ? __('Paid') : __('Unpaid') }}@if($p->pay_date) — {{ $p->pay_date->format('d/m/Y') }}@endif</td>
            <td class="label">{{ __('Payment Method') }}</td>
            <td>
                {{ $p->payment_method ? __(ucfirst(str_replace('_', ' ', $p->payment_method))) : '—' }}
                @if($p->bankAccount) — {{ $p->bankAccount->bank_name }} {{ $p->bankAccount->account_number }} @endif
            </td>
        </tr>
    </table>

    <table class="lines" style="margin-top: 12px;">
        <thead>
            <tr><th>{{ __('Description') }}</th><th class="num">{{ __('Amount') }} ({{ $currency }})</th></tr>
        </thead>
        <tbody>
            <tr class="section"><td colspan="2">{{ __('Earnings') }}</td></tr>
            <tr><td>{{ __('Basic salary') }}</td><td class="num">{{ $money($p->basic_salary) }}</td></tr>
            @foreach($p->details->where('status', 1) as $d)
            <tr><td>{{ $d->title }}</td><td class="num">{{ $money($d->amount) }}</td></tr>
            @endforeach
            @if((float) $p->bonus > 0)
            <tr><td>{{ __('Bonus') }}</td><td class="num">{{ $money($p->bonus) }}</td></tr>
            @endif
            @foreach($p->details->where('status', 0) as $d)
            <tr><td>{{ $d->title }}</td><td class="num">−{{ $money($d->amount) }}</td></tr>
            @endforeach
            <tr class="section"><td>{{ __('Gross salary') }}</td><td class="num">{{ $money($p->gross_salary) }}</td></tr>

            <tr class="section"><td colspan="2">{{ __('Taxes and contributions withheld') }}</td></tr>
            @if($slip->itemised)
                @foreach($slip->taxLines as $line)
                @if((float) $line['employee'] > 0)
                <tr><td>{{ $line['title'] }}</td><td class="num">−{{ $money($line['employee']) }}</td></tr>
                @endif
                @endforeach
            @endif
            <tr><td>{{ __('Total withheld') }}</td><td class="num">−{{ $money($p->tax) }}</td></tr>
        </tbody>
        <tfoot>
            <tr class="net"><td>{{ __('Net pay') }}</td><td class="num">{{ $money($p->net_salary) }} {{ $currency }}</td></tr>
        </tfoot>
    </table>

    @unless($slip->itemised)
    <p class="note">{{ __('The tax rules have changed since this payroll was generated, so only the totals actually withheld are shown.') }}</p>
    @endunless

    <table class="lines" style="margin-top: 10px; width: 55%;">
        <tr class="section"><td colspan="2">{{ __('Employer contributions (not deducted from pay)') }}</td></tr>
        @if($slip->itemised)
            @foreach($slip->taxLines as $line)
            @if((float) $line['employer'] > 0)
            <tr><td>{{ $line['title'] }}</td><td class="num">{{ $money($line['employer']) }}</td></tr>
            @endif
            @endforeach
        @endif
        <tr><td>{{ __('Total') }}</td><td class="num">{{ $money($p->employer_tax) }}</td></tr>
        <tr><td>{{ __('Total cost to the school') }}</td><td class="num">{{ $money($p->total_cost) }}</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td style="text-align:center; width:45%;"><div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('Employee signature') }}</div></td>
            <td style="width:10%;"></td>
            <td style="text-align:center; width:45%;"><div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('For the school') }}</div></td>
        </tr>
    </table>
</div>
@endforeach

</body>
</html>
