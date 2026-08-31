<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Leaving Certificate') }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; line-height: 1.6; }
        .title { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase;
                 letter-spacing: 1.5px; margin: 20px 0 6px; }
        .serial { text-align: center; font-size: 9.5px; color: #6b7280; margin-bottom: 16px; }
        table.details { width: 100%; border-collapse: collapse; margin: 14px 0; }
        table.details td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10.5px; }
        .label { color: #6b7280; width: 40%; }
        .cleared { color: #047857; font-weight: bold; }
        .owing { color: #b91c1c; font-weight: bold; }
        .sign { margin-top: 46px; }
        .note { font-size: 9px; color: #6b7280; margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ __('Leaving Certificate') }}</div>
<div class="serial">{{ __('Serial') }}: {{ $student->student_id }} · {{ now()->translatedFormat('j F Y') }}</div>

<p>
    {{ __('This is to certify that the student named below was enrolled at this school and has now left, as recorded here.') }}
</p>

<table class="details">
    <tr>
        <td class="label">{{ __('Full Name') }}</td>
        <td><strong>{{ $student->full_name }}</strong></td>
    </tr>
    <tr>
        <td class="label">{{ __('Student ID') }}</td>
        <td>{{ $student->student_id }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Date of Birth') }}</td>
        <td>
            {{ $student->date_of_birth?->translatedFormat('j F Y') ?? '—' }}
            @if($student->place_of_birth) — {{ $student->place_of_birth }} @endif
        </td>
    </tr>
    <tr>
        <td class="label">{{ __('Gender') }}</td>
        <td>{{ __(ucfirst($student->gender)) }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Nationality') }}</td>
        <td>{{ $student->nationality ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Date of Admission') }}</td>
        <td>{{ $student->admission_date?->translatedFormat('j F Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Last Class Attended') }}</td>
        <td>
            {{ $last?->classSection?->name ?? '—' }}
            @if($last?->academicSession) ({{ $last->academicSession->name }}) @endif
        </td>
    </tr>
    <tr>
        <td class="label">{{ __('Date of Leaving') }}</td>
        <td>{{ $last?->updated_at?->translatedFormat('j F Y') ?? '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Reason for Leaving') }}</td>
        <td>{{ __(ucfirst($student->status)) }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('School Fees') }}</td>
        <td>
            {{-- The question every receiving school asks, answered here rather
                 than left for someone to look up. --}}
            @if($outstanding > 0.005)
                <span class="owing">{{ __('Outstanding: :amount', ['amount' => number_format($outstanding, 0, '.', ' ').' '.($school?->currency ?? 'XAF')]) }}</span>
            @else
                <span class="cleared">{{ __('All fees cleared') }}</span>
            @endif
        </td>
    </tr>
</table>

<p>
    {{ __('To the best of the school\'s knowledge, the above particulars are correct as recorded in the school register.') }}
</p>

<table class="sign" style="width:100%;">
    <tr>
        <td style="text-align:center; width:45%;">
            <div style="border-top:1px solid #374151; padding-top:4px; font-size:10px;">
                {{ __('Registrar') }}
            </div>
        </td>
        <td style="width:10%;"></td>
        <td style="text-align:center; width:45%;">
            <div style="border-top:1px solid #374151; padding-top:4px; font-size:10px;">
                {{ __('The Principal') }}
            </div>
        </td>
    </tr>
</table>

<div class="note">
    {{ __('This certificate is issued without alteration. Any erasure renders it void.') }}
</div>

</body>
</html>
