<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Cumulative Record') }}</title>
    <style>
        @page { margin: 16mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase;
                 letter-spacing: 1px; margin: 14px 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        table.bio td { padding: 3px 6px; font-size: 10px; border-bottom: 1px solid #f3f4f6; }
        .label { color: #6b7280; width: 18%; }
        table.history { margin-top: 10px; }
        table.history th { background: #f3f4f6; font-size: 8.5px; text-transform: uppercase;
                           letter-spacing: 0.3px; padding: 5px 4px; border: 1px solid #e5e7eb;
                           text-align: left; color: #374151; }
        table.history td { padding: 4px; border: 1px solid #e5e7eb; font-size: 9.5px; }
        .num { text-align: center; }
        .year { background: #fafafa; font-weight: bold; }
        .empty { text-align: center; color: #9ca3af; padding: 20px; font-style: italic; }
        .sign { margin-top: 34px; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ __('Cumulative Academic Record') }}</div>

<table class="bio">
    <tr>
        <td class="label">{{ __('Name') }}</td>
        <td style="width:32%;"><strong>{{ $student->full_name }}</strong></td>
        <td class="label">{{ __('Student ID') }}</td>
        <td>{{ $student->student_id }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Date of Birth') }}</td>
        <td>{{ $student->date_of_birth?->translatedFormat('j F Y') ?? '—' }}</td>
        <td class="label">{{ __('Gender') }}</td>
        <td>{{ __(ucfirst($student->gender)) }}</td>
    </tr>
    <tr>
        <td class="label">{{ __('Admitted') }}</td>
        <td>{{ $student->admission_date?->translatedFormat('j F Y') ?? '—' }}</td>
        <td class="label">{{ __('Status') }}</td>
        <td>{{ __(ucfirst($student->status)) }}</td>
    </tr>
</table>

@if($history->isEmpty())
    <div class="empty">{{ __('No term results have been recorded for this student yet.') }}</div>
@else
    <table class="history">
        <thead>
            <tr>
                <th>{{ __('Year') }}</th>
                <th>{{ __('Term') }}</th>
                <th>{{ __('Class') }}</th>
                <th class="num">{{ __('Average') }}</th>
                <th class="num">{{ __('Grade') }}</th>
                <th class="num">{{ __('Rank') }}</th>
                <th class="num">{{ __('Present') }}</th>
                <th class="num">{{ __('Absent') }}</th>
                <th>{{ __('Conduct') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($history->groupBy('session') as $sessionName => $terms)
                @foreach($terms as $row)
                <tr>
                    <td>{{ $loop->first ? $sessionName : '' }}</td>
                    <td>{{ $row->term ?? '—' }}</td>
                    <td>{{ $row->class ?? '—' }}</td>
                    <td class="num">{{ $row->average !== null ? number_format((float) $row->average, 2) : '—' }}</td>
                    <td class="num">{{ $row->grade ?? '—' }}</td>
                    <td class="num">{{ $row->rank ? $row->rank.'/'.$row->total : '—' }}</td>
                    <td class="num">{{ $row->present ?? '—' }}</td>
                    <td class="num">{{ $row->absent ?? '—' }}</td>
                    <td>{{ $row->conduct ? __(ucfirst(str_replace('_', ' ', $row->conduct))) : '—' }}</td>
                </tr>
                @endforeach

                {{-- The annual figures the promotion decision was actually made on. --}}
                @if($terms->first()->final_average !== null)
                <tr class="year">
                    <td colspan="3">{{ __('Annual result for :year', ['year' => $sessionName]) }}</td>
                    <td class="num">{{ number_format((float) $terms->first()->final_average, 2) }}</td>
                    <td class="num">—</td>
                    <td class="num">{{ $terms->first()->final_rank ?? '—' }}</td>
                    <td colspan="3"></td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif

<table class="sign">
    <tr>
        <td style="width:60%; font-size:9px; color:#6b7280; vertical-align:bottom;">
            {{ __('Issued on :date. This record is a summary of results held in the school register.', ['date' => now()->translatedFormat('j F Y')]) }}
        </td>
        <td style="text-align:center;">
            <div style="border-top:1px solid #374151; padding-top:4px; font-size:10px;">
                {{ __('The Principal') }}
            </div>
        </td>
    </tr>
</table>

</body>
</html>
