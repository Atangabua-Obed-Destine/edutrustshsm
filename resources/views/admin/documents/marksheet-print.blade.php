<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Class Marksheet') }}</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111827; }
        .title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase;
                 letter-spacing: 1px; margin: 8px 0 2px; }
        .sub { text-align: center; font-size: 9px; color: #4b5563; margin-bottom: 8px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td { border: 1px solid #d1d5db; padding: 3px; }
        table.grid th { background: #f3f4f6; font-size: 7.5px; text-transform: uppercase;
                        letter-spacing: 0.2px; color: #374151; }
        .rot { font-size: 7.5px; }
        .num { text-align: center; }
        .name { text-align: left; white-space: nowrap; }
        .avg { font-weight: bold; background: #fafafa; }
        .fail { color: #b91c1c; }
        .stats { margin-top: 8px; font-size: 8.5px; color: #4b5563; }
        .stats td { padding: 2px 10px 2px 0; }
        .sign { margin-top: 26px; width: 100%; }
    </style>
</head>
<body>

@include('admin.documents._letterhead')

<div class="title">{{ __('Class Marksheet') }}</div>
<div class="sub">
    {{ $section->name }} · {{ $term->name }} · {{ $session->name }}
    · {{ __('Printed :date', ['date' => now()->translatedFormat('j F Y')]) }}
</div>

<table class="grid">
    <thead>
        <tr>
            <th class="num" style="width:22px;">#</th>
            <th style="width:60px;">{{ __('ID') }}</th>
            <th class="name">{{ __('Student') }}</th>
            @foreach($subjects as $subject)
                <th class="num rot">{{ $subject->name }}<br>(x{{ rtrim(rtrim(number_format((float) $subject->coefficient, 2, '.', ''), '0'), '.') }})</th>
            @endforeach
            <th class="num">{{ __('Total') }}</th>
            <th class="num">{{ __('Avg') }}</th>
            <th class="num">{{ __('Grade') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        <tr>
            <td class="num">{{ $row->rank ?? '—' }}</td>
            <td>{{ $row->student?->student_id }}</td>
            <td class="name">{{ $row->student?->last_name }} {{ $row->student?->first_name }}</td>
            @foreach($subjects as $subject)
                @php $cell = $row->by_subject[$subject->id]['term_average'] ?? null; @endphp
                <td class="num {{ $cell !== null && $cell < $passMark ? 'fail' : '' }}">
                    {{ $cell !== null ? number_format((float) $cell, 2) : '—' }}
                </td>
            @endforeach
            <td class="num">{{ number_format((float) $row->total_weighted, 2) }}</td>
            <td class="num avg">{{ $row->average !== null ? number_format((float) $row->average, 2) : '—' }}</td>
            <td class="num">{{ $row->grade }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="{{ $subjects->count() + 6 }}" class="num" style="padding:16px; color:#9ca3af; font-style:italic;">
                {{ __('No students are enrolled in this class for this term.') }}
            </td>
        </tr>
        @endforelse
    </tbody>
</table>

@if($class)
<table class="stats">
    <tr>
        <td>{{ __('Students') }}: <strong>{{ $class['count'] }}</strong></td>
        <td>{{ __('Class average') }}: <strong>{{ number_format($class['average'], 2) }}</strong></td>
        <td>{{ __('Highest') }}: <strong>{{ number_format($class['highest'], 2) }}</strong></td>
        <td>{{ __('Lowest') }}: <strong>{{ number_format($class['lowest'], 2) }}</strong></td>
    </tr>
</table>
@endif

<table class="sign">
    <tr>
        <td style="text-align:center; width:33%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('Class Teacher') }}</div>
        </td>
        <td style="width:1%;"></td>
        <td style="text-align:center; width:33%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('Vice Principal') }}</div>
        </td>
        <td style="width:1%;"></td>
        <td style="text-align:center; width:33%;">
            <div style="border-top:1px solid #374151; padding-top:3px; font-size:9px;">{{ __('The Principal') }}</div>
        </td>
    </tr>
</table>

</body>
</html>
