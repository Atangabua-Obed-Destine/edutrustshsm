<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Leaving Certificate') }} — {{ $student->student_id }}</title>
    <style>
        @page { margin: 20mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.6; }
        .school { text-align: center; border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 22px; }
        .school .name { font-size: 16px; font-weight: bold; letter-spacing: 0.5px; }
        .school .meta { font-size: 9px; color: #64748b; margin-top: 2px; }
        h1 { font-size: 14px; text-align: center; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 4px; }
        .ref { text-align: center; font-size: 9px; color: #64748b; margin-bottom: 24px; }
        table.facts { width: 100%; border-collapse: collapse; margin: 16px 0; }
        table.facts td { padding: 5px 4px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.facts td.label { width: 36%; color: #64748b; }
        table.facts td.value { font-weight: bold; }
        .body-text { margin: 20px 0; text-align: justify; }
        .sign { margin-top: 48px; width: 100%; }
        .sign td { width: 50%; font-size: 10px; padding-top: 30px; }
        .sign .line { border-top: 1px solid #94a3b8; padding-top: 4px; width: 80%; }
        .foot { margin-top: 30px; font-size: 8px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>

<div class="school">
    <div class="name">{{ $school?->school_name ?? __('School') }}</div>
    <div class="meta">
        {{ collect([$school?->address, $school?->city, $school?->region])->filter()->join(' · ') }}
        @if($school?->phone) · {{ $school->phone }} @endif
    </div>
</div>

<h1>{{ __('Leaving Certificate') }}</h1>
<div class="ref">{{ __('Ref') }}: {{ $student->student_id }}</div>

<div class="body-text">
    {{ __('This is to certify that the pupil named below was enrolled at this school and has left on the date stated. The particulars are taken from the school register.') }}
</div>

<table class="facts">
    <tr>
        <td class="label">{{ __('Name') }}</td>
        <td class="value">{{ $student->first_name }} {{ $student->last_name }} {{ $student->other_names }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Matricule') }}</td>
        <td class="value">{{ $student->student_id }}</td>
    </tr>
    @if($student->date_of_birth)
        <tr>
            <td class="label">{{ __('Date of birth') }}</td>
            <td class="value">
                {{ \Illuminate\Support\Carbon::parse($student->date_of_birth)->format('d M Y') }}
                @if($student->place_of_birth) — {{ $student->place_of_birth }} @endif
            </td>
        </tr>
    @endif
    @if($student->guardian)
        <tr>
            <td class="label">{{ __('Guardian') }}</td>
            <td class="value">{{ $student->guardian->guardian_name }}</td>
        </tr>
    @endif
    <tr>
        <td class="label">{{ __('Admitted') }}</td>
        <td class="value">{{ $student->admission_date ? \Illuminate\Support\Carbon::parse($student->admission_date)->format('d M Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Last class attended') }}</td>
        <td class="value">
            {{ $last?->classSection?->name ?? '—' }}
            @if($last?->academicSession) ({{ $last->academicSession->name }}) @endif
        </td>
    </tr>
    <tr>
        <td class="label">{{ __('Last day') }}</td>
        <td class="value">{{ $student->withdrawal_date ? \Illuminate\Support\Carbon::parse($student->withdrawal_date)->format('d M Y') : '—' }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Reason for leaving') }}</td>
        <td class="value">
            {{ __(ucfirst($student->status)) }}
            @if($student->destination_school) — {{ __('transferred to :school', ['school' => $student->destination_school]) }} @endif
        </td>
    </tr>
    @if($student->withdrawal_reason)
        <tr>
            <td class="label">{{ __('Note') }}</td>
            <td class="value">{{ $student->withdrawal_reason }}</td>
        </tr>
    @endif
</table>

<div class="body-text">
    {{ __('No liability of the school is implied by this certificate, and it is issued at the request of the parent or guardian.') }}
</div>

<table class="sign">
    <tr>
        <td><div class="line">{{ __('Class teacher') }}</div></td>
        <td><div class="line">{{ __('Principal') }}</div></td>
    </tr>
</table>

<div class="foot">{{ __('Issued :date', ['date' => now()->format('d M Y')]) }}</div>

</body>
</html>
