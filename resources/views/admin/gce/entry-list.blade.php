<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('GCE Entry List') }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase;
                 letter-spacing: 1px; margin: 8px 0 2px; }
        .sub { text-align: center; font-size: 9px; color: #4b5563; margin-bottom: 8px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #d1d5db; padding: 3px 4px; }
        table.grid th { background: #f3f4f6; font-size: 7.5px; text-transform: uppercase;
                        color: #374151; text-align: left; }
        .code { font-family: DejaVu Sans Mono, monospace; }
        .num { text-align: center; }
        .totals { margin-top: 8px; font-size: 8.5px; color: #4b5563; }
        .totals td { padding: 2px 12px 2px 0; }
        .sign { margin-top: 24px; width: 100%; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ __('GCE Entry List') }}</div>
<div class="sub">
    {{ $session->name }} — {{ $session->level_label }} {{ $session->exam_year }}
    @if($session->centre_number) · {{ __('Centre') }} {{ $session->centre_number }} @endif
    · {{ __('Printed :date', ['date' => now()->translatedFormat('j F Y')]) }}
</div>

<table class="grid">
    <thead>
        <tr>
            <th class="num" style="width:24px;">#</th>
            <th style="width:80px;">{{ __('Candidate No.') }}</th>
            <th style="width:70px;">{{ __('Student ID') }}</th>
            <th style="width:150px;">{{ __('Name') }}</th>
            <th style="width:70px;">{{ __('Class') }}</th>
            <th class="num" style="width:32px;">{{ __('No.') }}</th>
            <th>{{ __('Subject Codes') }}</th>
            <th style="width:60px;">{{ __('Status') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($candidates as $candidate)
        <tr>
            <td class="num">{{ $loop->iteration }}</td>
            <td class="code">{{ $candidate->candidate_number ?? '—' }}</td>
            <td>{{ $candidate->student?->student_id }}</td>
            <td>{{ $candidate->student?->last_name }} {{ $candidate->student?->first_name }}</td>
            <td>{{ $candidate->enrollment?->classSection?->name ?? '—' }}</td>
            <td class="num">{{ $candidate->subjects->count() }}</td>
            <td class="code">{{ $candidate->subjects->pluck('code')->join(' ') }}</td>
            <td>{{ __(ucfirst($candidate->status)) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="8" class="num" style="padding:16px; color:#9ca3af; font-style:italic;">
                {{ __('No candidates entered for this series yet.') }}
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>{{ __('Candidates') }}: <strong>{{ $candidates->count() }}</strong></td>
        <td>{{ __('Subject Entries') }}: <strong>{{ $candidates->sum(fn ($c) => $c->subjects->count()) }}</strong></td>
        <td>{{ __('Fees Due') }}: <strong>{{ number_format($candidates->sum(fn ($c) => (float) $c->fee_amount), 0, '.', ' ') }} {{ $school?->currency ?? 'XAF' }}</strong></td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td style="text-align:center; width:45%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('Registrar') }}</div>
        </td>
        <td style="width:10%;"></td>
        <td style="text-align:center; width:45%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('The Principal') }}</div>
        </td>
    </tr>
</table>

</body>
</html>
