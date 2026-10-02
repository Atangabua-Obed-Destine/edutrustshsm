<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; margin: 8px 0 2px; }
        .sub { text-align: center; font-size: 9px; color: #4b5563; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; font-size: 8px; text-transform: uppercase; padding: 4px; border-bottom: 1px solid #d1d5db; text-align: left; }
        td { padding: 3px 4px; border-bottom: 1px solid #f3f4f6; }
        .num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .old { color: #b91c1c; font-weight: bold; }
        .buckets td { border: 1px solid #e5e7eb; text-align: center; padding: 5px; }
        .buckets .label { font-size: 8px; color: #6b7280; text-transform: uppercase; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ $title }}</div>
<div class="sub">{{ __('As of') }} {{ \Illuminate\Support\Carbon::parse($asOf)->format('d/m/Y') }} · {{ $currency }}</div>

<table class="buckets" style="margin-bottom: 10px;">
    <tr>
        @foreach($buckets as $bucket)
        <td><div class="label">{{ $bucket }} {{ __('days') }}</div><div class="num" style="text-align:center;">{{ number_format($totals[$bucket] ?? 0, 0, '.', ' ') }}</div></td>
        @endforeach
        <td><div class="label">{{ __('Total') }}</div><div class="num" style="text-align:center; font-weight:bold;">{{ number_format($totals['total'] ?? 0, 0, '.', ' ') }}</div></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            @if($student)
            <th>{{ __('Student') }}</th><th>{{ __('Class') }}</th><th>{{ __('Fee') }}</th><th>{{ __('Due') }}</th>
            @else
            <th>{{ __('Account') }}</th><th>{{ __('Oldest entry') }}</th>
            @endif
            <th class="num">{{ __('Days') }}</th>
            <th>{{ __('Bucket') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        <tr>
            @if($student)
            <td>{{ $row->student?->first_name }} {{ $row->student?->last_name }} <span style="color:#9ca3af;">{{ $row->student?->student_id }}</span></td>
            <td>{{ $row->class ?? '—' }}</td>
            <td>{{ $row->category ?? '—' }}</td>
            <td>{{ $row->due_date?->format('d/m/Y') ?? '—' }}</td>
            @else
            <td><span style="color:#9ca3af;">{{ $row->account->account_code }}</span> {{ $row->account->account_name }}</td>
            <td>{{ $row->oldest_entry?->format('d/m/Y') ?? '—' }}</td>
            @endif
            <td class="num {{ $row->days_overdue > 90 ? 'old' : '' }}">{{ $row->days_overdue }}</td>
            <td>{{ $row->bucket }}</td>
            <td class="num">{{ number_format($row->balance, 2, '.', ' ') }}</td>
        </tr>
        @empty
        <tr><td colspan="{{ $student ? 7 : 5 }}" style="text-align:center; color:#9ca3af; padding: 14px;">{{ __('Nothing outstanding.') }}</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
