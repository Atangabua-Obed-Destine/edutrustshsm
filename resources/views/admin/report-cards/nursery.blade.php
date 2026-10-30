@php
    $student = $termResult->enrollment->student;
    $classSection = $termResult->enrollment->classSection;
    $term = $termResult->term;
    $labels = \App\Models\NurseryAssessment::labels();
    $counts = $card['counts'];
    $total = $card['total'];
    $rated = $total - $counts['unrated'];
    $isParent = auth('guardians')->check();
    $backUrl = $isParent
        ? route('parent.report-cards.index', $student->id)
        : route('admin.report-cards.index', ['class_section_id' => $classSection->id, 'term_id' => $termResult->term_id]);
    $pct = fn ($n) => $rated > 0 ? (int) round($n / $rated * 100) : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Nursery Progress Report') }} — {{ $student->last_name }} {{ $student->first_name }}</title>
    <style>
        @page { size: A4; margin: 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 11px; color: #1a1a1a; line-height: 1.4; background: #f0f0f0; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto; background: white; padding: 10mm; }
        @media print {
            body { background: white; }
            .page { margin: 0; padding: 8mm; width: 100%; min-height: auto; }
            .no-print { display: none !important; }
        }

        .header { text-align: center; border-bottom: 3px double #1e293b; padding-bottom: 8px; margin-bottom: 10px; }
        .header .republic { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #555; }
        .header .school-name { font-size: 20px; font-weight: 800; color: #1e293b; margin: 4px 0; letter-spacing: 0.5px; }
        .header .school-info { font-size: 9px; color: #666; }
        .header .motto { font-style: italic; font-size: 10px; color: #1e293b; margin-top: 2px; }
        .header .report-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-top: 8px; text-transform: uppercase; letter-spacing: 2px; background: #f1f5f9; padding: 5px 20px; display: inline-block; border-radius: 4px; }

        table.info { width: 100%; border-collapse: collapse; margin: 10px 0; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        table.info td { padding: 6px 10px; vertical-align: top; }
        table.info .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 600; }
        table.info .value { font-size: 11px; font-weight: 600; color: #1e293b; }

        .legend { font-size: 9px; color: #475569; margin: 8px 0 4px; }
        .legend .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 3px; vertical-align: middle; }
        .legend span.item { margin-right: 14px; }

        table.area { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10px; page-break-inside: avoid; }
        table.area th { background: #1e293b; color: white; font-weight: 700; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; padding: 4px 6px; text-align: left; }
        table.area th.c { text-align: center; width: 72px; }
        table.area td { border: 1px solid #cbd5e1; padding: 4px 6px; }
        table.area td.c { text-align: center; font-weight: 700; font-size: 9px; }
        table.area tr:nth-child(even) td { background: #f8fafc; }
        .acquired { color: #15803d; }
        .developing { color: #b45309; }
        .not_yet { color: #475569; }
        .unrated { color: #cbd5e1; }
        .was { font-size: 8px; color: #94a3b8; font-weight: 400; }

        table.summary { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.summary td { vertical-align: top; padding: 0 5px; }
        .box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; }
        .box h4 { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
        .bar { height: 9px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 4px 0 6px; font-size: 0; }
        .bar span { display: inline-block; height: 9px; }
        .stat { font-size: 10px; display: flex; justify-content: space-between; padding: 1px 0; }
        .stat b { color: #1e293b; }
        .big { font-size: 22px; font-weight: 800; color: #15803d; line-height: 1; }
        .remark-text { font-size: 10px; color: #1e293b; min-height: 36px; white-space: pre-line; }

        .signatures { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .signatures td { width: 33%; text-align: center; padding: 0 10px; }
        .sig-line { border-top: 1px solid #94a3b8; margin-top: 30px; padding-top: 4px; font-size: 9px; color: #64748b; }

        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #1e293b; color: white; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; z-index: 9999; }
        .print-bar button { padding: 8px 20px; background: white; color: #1e293b; border: none; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; }
    </style>
</head>
<body>
    @unless(isset($pdfMode) && $pdfMode)
    <div class="print-bar no-print">
        <span>{{ __('Nursery Progress Report') }} — {{ $student->last_name }} {{ $student->first_name }}</span>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="{{ $backUrl }}" style="color:#94a3b8; text-decoration:none; font-size:12px;">&larr; {{ __('Back to List') }}</a>
            <button onclick="window.print()">🖨️ {{ __('Print') }}</button>
        </div>
    </div>
    <div style="height:56px;" class="no-print"></div>
    @endunless

    <div class="page">
        <div class="header">
            <div class="republic">{{ __('Republic of Cameroon — Peace, Work, Fatherland') }}</div>
            <div class="school-name">{{ $school->school_name ?? 'School Name' }}</div>
            <div class="school-info">
                {{ $school->address ?? '' }}{{ $school->city ? ', '.$school->city : '' }}{{ $school->region ? ' — '.$school->region : '' }}
                @if($school->phone) &middot; {{ __('Tel:') }} {{ $school->phone }} @endif
                @if($school->po_box) &middot; {{ __('P.O. Box:') }} {{ $school->po_box }} @endif
            </div>
            @if($school->motto)
            <div class="motto">"{{ $school->motto }}"</div>
            @endif
            <div class="report-title">{{ __('Nursery Progress Report') }}</div>
        </div>

        <table class="info">
            <tr>
                <td><div class="label">{{ __('Full Name') }}</div><div class="value">{{ $student->last_name }} {{ $student->first_name }} {{ $student->other_names ?? '' }}</div></td>
                <td><div class="label">{{ __('Student ID') }}</div><div class="value">{{ $student->student_id }}</div></td>
                <td><div class="label">{{ __('Class') }}</div><div class="value">{{ $classSection->name }}</div></td>
                <td><div class="label">{{ __('Academic Year') }}</div><div class="value">{{ $termResult->enrollment->academicSession->name ?? $currentSession->name ?? '' }}</div></td>
                <td><div class="label">{{ __('Term') }}</div><div class="value">{{ $term->name }}</div></td>
                @if($student->date_of_birth)
                <td><div class="label">{{ __('Age') }}</div><div class="value">{{ \Carbon\Carbon::parse($student->date_of_birth)->diffInYears(\Carbon\Carbon::parse($term->end_date)) }} {{ __('yrs') }}</div></td>
                @endif
            </tr>
        </table>

        <div class="legend">
            <span class="item"><span class="dot" style="background:#15803d"></span><b>{{ __('A') }}</b> {{ __('Acquired') }} — {{ __('does it alone, reliably') }}</span>
            <span class="item"><span class="dot" style="background:#b45309"></span><b>{{ __('IP') }}</b> {{ __('In progress') }} — {{ __('does it with help or sometimes') }}</span>
            <span class="item"><span class="dot" style="background:#475569"></span><b>{{ __('NY') }}</b> {{ __('Not yet') }} — {{ __('not seen yet this term') }}</span>
        </div>

        @foreach($card['areas'] as $area)
            <table class="area">
                <thead>
                    <tr>
                        <th>{{ $area->name }}</th>
                        <th class="c">{{ $term->name }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($area->competences as $competence)
                        @php
                            $rating = $competence->rating;
                            $was = $card['previous'][$competence->id] ?? null;
                        @endphp
                        <tr>
                            <td>{{ $competence->description }}</td>
                            <td class="c {{ $rating ?? 'unrated' }}">
                                {{ $rating ? $labels[$rating] : '—' }}
                                @if($was && $was !== $rating && $rating)
                                    <div class="was">{{ __('was :label', ['label' => $labels[$was]]) }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach

        <table class="summary">
            <tr>
                <td style="width:40%">
                    <div class="box">
                        <h4>{{ __('This term at a glance') }}</h4>
                        <div class="big">{{ $counts['acquired'] }}<span style="font-size:11px; color:#64748b; font-weight:600;"> / {{ $rated }} {{ __('acquired') }}</span></div>
                        <div class="bar">
                            <span style="width:{{ $pct($counts['acquired']) }}%; background:#15803d"></span><span style="width:{{ $pct($counts['developing']) }}%; background:#f59e0b"></span><span style="width:{{ $pct($counts['not_yet']) }}%; background:#94a3b8"></span>
                        </div>
                        <div class="stat"><span class="acquired">{{ __('Acquired') }}</span><b>{{ $counts['acquired'] }}</b></div>
                        <div class="stat"><span class="developing">{{ __('In progress') }}</span><b>{{ $counts['developing'] }}</b></div>
                        <div class="stat"><span class="not_yet">{{ __('Not yet') }}</span><b>{{ $counts['not_yet'] }}</b></div>
                        @if($counts['unrated'] > 0)
                            <div class="stat"><span class="unrated">{{ __('Not rated') }}</span><b style="color:#94a3b8">{{ $counts['unrated'] }}</b></div>
                        @endif
                    </div>
                </td>
                <td style="width:25%">
                    <div class="box">
                        <h4>{{ __('Attendance') }}</h4>
                        <div class="stat"><span>{{ __('Days present') }}</span><b>{{ $termResult->days_present ?? '—' }}</b></div>
                        <div class="stat"><span>{{ __('Days absent') }}</span><b>{{ $termResult->days_absent ?? '—' }}</b></div>
                        <div class="stat"><span>{{ __('School days') }}</span><b>{{ $termResult->total_school_days ?? '—' }}</b></div>
                    </div>
                </td>
                <td style="width:35%">
                    <div class="box">
                        <h4>{{ __('How to read this report') }}</h4>
                        <p style="font-size:9px; color:#475569;">{{ __('Children grow at their own pace. A competence still in progress is a competence being learned, and the class teacher will keep working on it. There is no mark and no position in class at this age.') }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <table class="summary" style="margin-top:8px;">
            <tr>
                <td style="width:50%">
                    <div class="box">
                        <h4>{{ __("Class Teacher's Remark") }}</h4>
                        <div class="remark-text">{{ $termResult->class_teacher_remark ?: '' }}</div>
                        @if($classSection->classTeacher)
                            <div style="font-size:8px; color:#94a3b8; margin-top:4px;">{{ $classSection->classTeacher->first_name }} {{ $classSection->classTeacher->last_name }}</div>
                        @endif
                    </div>
                </td>
                <td style="width:50%">
                    <div class="box">
                        <h4>{{ __("Head Teacher's Remark") }}</h4>
                        <div class="remark-text">{{ $termResult->principal_remark ?: '' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="signatures">
            <tr>
                <td><div class="sig-line">{{ __('Class Teacher') }}</div></td>
                <td><div class="sig-line">{{ __('Head Teacher') }}</div></td>
                <td><div class="sig-line">{{ __('Parent / Guardian') }}</div></td>
            </tr>
        </table>
    </div>
</body>
</html>
