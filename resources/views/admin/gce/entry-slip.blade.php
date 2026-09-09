<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('GCE Entry Slip') }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; line-height: 1.5; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase;
                 letter-spacing: 1px; margin: 14px 0 3px; }
        .sub { text-align: center; font-size: 10px; color: #4b5563; margin-bottom: 14px; }
        table.details { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.details td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10.5px; }
        .label { color: #6b7280; width: 26%; }
        .big { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        table.subjects { width: 100%; border-collapse: collapse; }
        table.subjects th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase;
                            padding: 5px; border: 1px solid #e5e7eb; text-align: left; color: #374151; }
        table.subjects td { padding: 5px; border: 1px solid #e5e7eb; font-size: 10px; }
        .code { font-family: DejaVu Sans Mono, monospace; }
        .fees { margin-top: 12px; font-size: 10px; }
        .fees td { padding: 3px 6px; }
        .sign { margin-top: 34px; width: 100%; }
        .note { font-size: 8.5px; color: #6b7280; margin-top: 18px; border-top: 1px solid #e5e7eb; padding-top: 5px; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ __('GCE Entry Slip') }}</div>
<div class="sub">{{ $session->name }} — {{ $session->level_label }} {{ $session->exam_year }}</div>

<table class="details">
    <tr>
        <td class="label">{{ __('Candidate Number') }}</td>
        <td class="big code">{{ $candidate->candidate_number ?? '—' }}</td>
        <td class="label">{{ __('Centre Number') }}</td>
        <td class="code">{{ $session->centre_number ?: '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Name') }}</td>
        <td colspan="3"><strong>{{ $candidate->student?->full_name }}</strong></td>
    </tr>
    <tr>
        <td class="label">{{ __('Student ID') }}</td>
        <td>{{ $candidate->student?->student_id }}</td>
        <td class="label">{{ __('Class') }}</td>
        <td>{{ $candidate->enrollment?->classSection?->name ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Date of Birth') }}</td>
        <td>{{ $candidate->student?->date_of_birth?->translatedFormat('j F Y') ?? '—' }}</td>
        <td class="label">{{ __('Gender') }}</td>
        <td>{{ $candidate->student?->gender ? __(ucfirst($candidate->student->gender)) : '—' }}</td>
    </tr>
</table>

<table class="subjects">
    <thead>
        <tr>
            <th style="width:30px;">#</th>
            <th style="width:80px;">{{ __('Code') }}</th>
            <th>{{ __('Subject') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($candidate->subjects as $subject)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td class="code">{{ $subject->code }}</td>
            <td>{{ $subject->name }}</td>
        </tr>
        @empty
        <tr><td colspan="3" style="text-align:center; color:#9ca3af; font-style:italic;">{{ __('No subjects entered.') }}</td></tr>
        @endforelse
    </tbody>
</table>

<table class="fees">
    <tr>
        <td>{{ __('Entry fee') }}: <strong>{{ number_format((float) $candidate->fee_amount, 0, '.', ' ') }} {{ $school?->currency ?? 'XAF' }}</strong></td>
        <td>{{ __('Paid') }}: <strong>{{ number_format((float) $candidate->amount_paid, 0, '.', ' ') }}</strong></td>
        <td>{{ __('Balance') }}: <strong>{{ number_format($candidate->balance, 0, '.', ' ') }}</strong></td>
        <td>{{ __('Status') }}: <strong>{{ __(ucfirst($candidate->status)) }}</strong></td>
    </tr>
</table>

<table class="sign">
    <tr>
        <td style="text-align:center; width:45%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9.5px;">{{ __('Candidate\'s Signature') }}</div>
        </td>
        <td style="width:10%;"></td>
        <td style="text-align:center; width:45%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9.5px;">{{ __('The Principal') }}</div>
        </td>
    </tr>
</table>

<div class="note">
    {{ __('Check every subject code above. Once entries are submitted to the Board they cannot be changed, and a wrong code means sitting the wrong paper.') }}
</div>

</body>
</html>
