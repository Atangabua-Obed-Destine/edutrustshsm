<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Acceptance Letter') }}</title>
    <style>
        @page { margin: 22mm 18mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; line-height: 1.6; }
        .meta { font-size: 10px; color: #4b5563; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase;
                 letter-spacing: 1px; margin: 18px 0 14px; }
        .box { border: 1px solid #d1d5db; padding: 10px 12px; margin: 12px 0; }
        .box td { padding: 3px 0; font-size: 10.5px; }
        .label { color: #6b7280; width: 42%; }
        ul { margin: 6px 0 0 16px; padding: 0; }
        li { margin-bottom: 3px; }
        .sign { margin-top: 42px; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<table style="width:100%;">
    <tr>
        <td class="meta">{{ __('Ref') }}: {{ $application->application_number }}</td>
        <td class="meta" style="text-align:right;">{{ now()->translatedFormat('j F Y') }}</td>
    </tr>
</table>

<div style="margin-top:10px;">
    <strong>{{ trim($application->first_name.' '.$application->last_name) }}</strong><br>
    <span class="meta">
        {{ collect([$application->home_address, $application->town])->filter()->join(', ') }}
    </span>
</div>

<div class="title">{{ __('Offer of Admission') }}</div>

<p>{{ __('Dear :name,', ['name' => $application->first_name]) }}</p>

<p>
    {{ __('Following the review of your application, I am pleased to inform you that you have been offered a place at :school for the :session academic year.', [
        'school' => $school?->school_name ?? config('app.name'),
        'session' => $application->academicSession?->name ?? '—',
    ]) }}
</p>

<table class="box" style="width:100%; border-collapse:collapse;">
    <tr>
        <td class="label">{{ __('Application Number') }}</td>
        <td><strong>{{ $application->application_number }}</strong></td>
    </tr>
    <tr>
        <td class="label">{{ __('Class Offered') }}</td>
        <td><strong>{{ $application->form?->name ?? '—' }}{{ $application->stream ? ' — '.$application->stream->name : '' }}</strong></td>
    </tr>
    <tr>
        <td class="label">{{ __('Academic Year') }}</td>
        <td>{{ $application->academicSession?->name ?? '—' }}</td>
    </tr>
    @if($application->academicSession?->start_date)
    <tr>
        <td class="label">{{ __('Term Begins') }}</td>
        <td>{{ $application->academicSession->start_date->translatedFormat('j F Y') }}</td>
    </tr>
    @endif
</table>

<p>{{ __('To confirm this place, please report to the school administration on or before the date above with the following:') }}</p>

<ul>
    <li>{{ __('This letter of acceptance') }}</li>
    <li>{{ __('The original birth certificate and one photocopy') }}</li>
    <li>{{ __('The last school report card or transfer certificate') }}</li>
    <li>{{ __('Four recent passport-size photographs') }}</li>
    <li>{{ __('Proof of payment of the registration fee') }}</li>
</ul>

<p style="margin-top:12px;">
    {{ __('This offer is valid until the start of term. A place not confirmed by then may be released to another candidate.') }}
</p>

<p>{{ __('We look forward to welcoming you.') }}</p>

<table class="sign" style="width:100%;">
    <tr>
        <td style="width:55%;"></td>
        <td style="text-align:center;">
            <div style="border-top:1px solid #374151; padding-top:4px; font-size:10px;">
                {{ __('The Principal') }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
