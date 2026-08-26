<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Report Card') }} — {{ $termResult->enrollment->student->last_name }} {{ $termResult->enrollment->student->first_name }}</title>
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

        /* Header */
        .header { text-align: center; border-bottom: 3px double #1e293b; padding-bottom: 8px; margin-bottom: 10px; }
        .header .republic { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #555; }
        .header .school-name { font-size: 20px; font-weight: 800; color: #1e293b; margin: 4px 0; letter-spacing: 0.5px; }
        .header .school-info { font-size: 9px; color: #666; }
        .header .motto { font-style: italic; font-size: 10px; color: #1e293b; margin-top: 2px; }
        .header .report-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-top: 8px; text-transform: uppercase; letter-spacing: 2px; background: #f1f5f9; padding: 5px 20px; display: inline-block; border-radius: 4px; }

        /* Student Info */
        .student-info { display: flex; gap: 10px; margin: 10px 0; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; }
        .student-info .col { flex: 1; }
        .student-info .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 600; }
        .student-info .value { font-size: 11px; font-weight: 600; color: #1e293b; }

        /* Marks Table */
        .marks-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10px; }
        .marks-table th, .marks-table td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: center; }
        .marks-table thead th { background: #1e293b; color: white; font-weight: 700; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; }
        .marks-table tbody td { font-size: 10px; }
        .marks-table tbody td.subject-name { text-align: left; font-weight: 600; max-width: 140px; }
        .marks-table tbody td.teacher-name { text-align: left; font-size: 8px; color: #64748b; max-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .marks-table tbody td.remark-cell { text-align: left; font-size: 8px; color: #475569; max-width: 70px; font-weight: 500; }
        .marks-table tbody tr:nth-child(even) { background: #f8fafc; }
        .marks-table tbody tr:hover { background: #f1f5f9; }
        .marks-table .fail { color: #dc2626; font-weight: 700; }
        .marks-table .pass { color: #16a34a; }
        .rank-cell { font-weight: 700; }
        .avg-highlight { font-weight: 800; font-size: 11px; }

        /* Summary row */
        .marks-table tfoot td { border-top: 2px solid #1e293b; font-weight: 700; background: #f1f5f9; font-size: 11px; }

        /* Bottom Section */
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 12px; }
        .summary-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; }
        .summary-box h4 { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .stat-row { display: flex; justify-content: space-between; padding: 2px 0; font-size: 10px; }
        .stat-row .stat-label { color: #64748b; }
        .stat-row .stat-value { font-weight: 700; color: #1e293b; }

        /* Remark boxes */
        .remark-section { margin-top: 12px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .remark-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; min-height: 60px; }
        .remark-box .remark-title { font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #94a3b8; font-weight: 700; margin-bottom: 4px; }
        .remark-box .remark-text { font-size: 10px; color: #1e293b; min-height: 20px; }

        /* Decision */
        .decision-bar { margin-top: 12px; padding: 8px 14px; border-radius: 6px; text-align: center; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .decision-pass { background: #dcfce7; color: #15803d; border: 2px solid #86efac; }
        .decision-fail { background: #fee2e2; color: #b91c1c; border: 2px solid #fca5a5; }

        /* Signatures */
        .signatures { display: flex; justify-content: space-between; margin-top: 20px; }
        .sig-block { text-align: center; width: 40%; }
        .sig-block .sig-line { border-top: 1px solid #94a3b8; margin-top: 30px; padding-top: 4px; font-size: 9px; color: #64748b; }

        /* Print button */
        .print-bar { position: fixed; top: 0; left: 0; right: 0; background: #1e293b; color: white; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; z-index: 9999; }
        .print-bar button { padding: 8px 20px; background: white; color: #1e293b; border: none; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; }
        .print-bar button:hover { background: #f1f5f9; }
    </style>
</head>
<body>
    @unless(isset($pdfMode) && $pdfMode)
    {{-- Print toolbar --}}
    <div class="print-bar no-print">
        <span>{{ __('Report Card Preview') }} — {{ $termResult->enrollment->student->last_name }} {{ $termResult->enrollment->student->first_name }}</span>
        <div style="display:flex; gap:10px; align-items:center;">
            <a href="{{ route('admin.report-cards.index', ['class_section_id' => $termResult->enrollment->class_section_id, 'term_id' => $termResult->term_id]) }}" style="color:#94a3b8; text-decoration:none; font-size:12px;">&larr; {{ __('Back to List') }}</a>
            <button onclick="window.print()">🖨️ {{ __('Print Report Card') }}</button>
        </div>
    </div>
    <div style="height:56px;" class="no-print"></div>
    @endunless

    <div class="page">
        {{-- Header --}}
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
            <div class="report-title">{{ __('Term Report Card') }}</div>
        </div>

        {{-- Student Info --}}
        @php
            $student = $termResult->enrollment->student;
            $classSection = $termResult->enrollment->classSection;
            $term = $termResult->term;
        @endphp
        <div class="student-info">
            <div class="col">
                <div class="label">{{ __('Full Name') }}</div>
                <div class="value">{{ $student->last_name }} {{ $student->first_name }} {{ $student->other_names ?? '' }}</div>
            </div>
            <div class="col">
                <div class="label">{{ __('Student ID') }}</div>
                <div class="value">{{ $student->student_id }}</div>
            </div>
            <div class="col">
                <div class="label">{{ __('Class') }}</div>
                <div class="value">{{ $classSection->name }}</div>
            </div>
            <div class="col">
                <div class="label">{{ __('Academic Year') }}</div>
                <div class="value">{{ $currentSession->name ?? '' }}</div>
            </div>
            <div class="col">
                <div class="label">{{ __('Term') }}</div>
                <div class="value">{{ $term->name }}</div>
            </div>
        </div>

        {{-- Marks Table --}}
        @php
            $subjectResults = $termResult->subjectResults->sortBy(fn ($r) => $r->subject->name);
            $seqCount = $sequences->count();
            if ($seqCount === 0) $seqCount = 2; // fallback
        @endphp
        <table class="marks-table">
            <thead>
                <tr>
                    <th style="width:28px;">{{ __('S/N') }}</th>
                    <th style="text-align:left; width:130px;">{{ __('Subject') }}</th>
                    @foreach($sequences as $seq)
                    <th>{{ $seq->name }}</th>
                    @endforeach
                    @if($sequences->isEmpty())
                    <th>{{ __('Seq 1') }}</th>
                    <th>{{ __('Seq 2') }}</th>
                    @endif
                    <th>{{ __('Coeff') }}</th>
                    <th>{{ __('Total') }}</th>
                    <th>{{ __('Average') }}</th>
                    <th>{{ __('Rank') }}</th>
                    <th style="text-align:left; width:80px;">{{ __("Teacher's Name") }}</th>
                    <th style="text-align:left; width:65px;">{{ __('Remark') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subjectResults->values() as $i => $sr)
                @php
                    $avg = $sr->term_average;
                    $isFail = $avg !== null && $avg < 10;
                    // Get remark from GradeScale description
                    $remark = '-';
                    if ($avg !== null) {
                        $gs = \App\Models\GradeScale::getGrade((float) $avg);
                        $remark = $gs ? $gs->description : '-';
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="subject-name">{{ $sr->subject->name }}</td>
                    @foreach($sequences as $sIdx => $seq)
                    @php
                        $seqField = 'sequence_' . ($sIdx + 1) . '_score';
                        $seqVal = $sr->$seqField;
                    @endphp
                    <td class="{{ $seqVal !== null && $seqVal < 10 ? 'fail' : '' }}">
                        {{ $seqVal !== null ? number_format($seqVal, 1) : '-' }}
                    </td>
                    @endforeach
                    @if($sequences->isEmpty())
                    <td>{{ $sr->sequence_1_score !== null ? number_format($sr->sequence_1_score, 1) : '-' }}</td>
                    <td>{{ $sr->sequence_2_score !== null ? number_format($sr->sequence_2_score, 1) : '-' }}</td>
                    @endif
                    <td>{{ number_format($sr->coefficient, 1) }}</td>
                    <td>{{ $sr->weighted_score !== null ? number_format($sr->weighted_score, 2) : '-' }}</td>
                    <td class="avg-highlight {{ $isFail ? 'fail' : 'pass' }}">
                        {{ $avg !== null ? number_format($avg, 2) : '-' }}
                    </td>
                    <td class="rank-cell">
                        @if($sr->subject_rank)
                            {{ $sr->subject_rank }}<span style="color:#94a3b8;font-weight:400;">/{{ $sr->subject_total_students }}</span>
                        @else - @endif
                    </td>
                    <td class="teacher-name">{{ $sr->teacher_name ?? '-' }}</td>
                    <td class="remark-cell {{ $isFail ? 'fail' : '' }}">{{ $remark }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right; font-weight:800;">{{ __('TOTAL') }}</td>
                    <td colspan="{{ $sequences->count() ?: 2 }}"></td>
                    <td>{{ number_format($termResult->total_coefficient, 1) }}</td>
                    <td>{{ number_format($termResult->total_weighted_score, 2) }}</td>
                    <td class="avg-highlight {{ $termResult->term_average < 10 ? 'fail' : 'pass' }}" style="font-size:12px;">
                        {{ number_format($termResult->term_average, 2) }}/20
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        {{-- Bottom Stats --}}
        <div class="bottom-grid">
            <div class="summary-box">
                <h4>{{ __('Student Performance') }}</h4>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Term Average') }}</span>
                    <span class="stat-value" style="{{ $termResult->term_average < 10 ? 'color:#dc2626' : 'color:#16a34a' }}">{{ number_format($termResult->term_average, 2) }} / 20</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Overall Grade') }}</span>
                    <span class="stat-value">{{ $termResult->overall_grade }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Class Rank') }}</span>
                    <span class="stat-value">{{ $termResult->class_rank }}<span style="color:#94a3b8; font-weight:400;"> / {{ $termResult->total_students }}</span></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Total Weighted Score') }}</span>
                    <span class="stat-value">{{ number_format($termResult->total_weighted_score, 2) }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Total Coefficient') }}</span>
                    <span class="stat-value">{{ number_format($termResult->total_coefficient, 1) }}</span>
                </div>
            </div>

            <div class="summary-box">
                <h4>{{ __('Class Statistics') }}</h4>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Class Average') }}</span>
                    <span class="stat-value">{{ number_format($termResult->class_average, 2) }} / 20</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Highest Average') }}</span>
                    <span class="stat-value">{{ number_format($termResult->highest_average, 2) }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Lowest Average') }}</span>
                    <span class="stat-value">{{ number_format($termResult->lowest_average, 2) }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Days Present / Total') }}</span>
                    <span class="stat-value">{{ $termResult->days_present ?? 0 }} / {{ $termResult->total_school_days ?? 0 }}</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">{{ __('Days Absent') }}</span>
                    <span class="stat-value" style="{{ ($termResult->days_absent ?? 0) > 5 ? 'color:#dc2626' : '' }}">{{ $termResult->days_absent ?? 0 }}</span>
                </div>
            </div>
        </div>

        {{-- Decision --}}
        @php $passed = $termResult->term_average >= 10; @endphp
        <div class="decision-bar {{ $passed ? 'decision-pass' : 'decision-fail' }}">
            {{ $passed ? __('✓ PASS — Average Above 10/20') : __('✗ BELOW PASS MARK — Average Below 10/20') }}
        </div>

        {{-- Remarks --}}
        <div class="remark-section">
            <div class="remark-box">
                <div class="remark-title">{{ __("Class Teacher's Remark") }}</div>
                <div class="remark-text">{{ $termResult->class_teacher_remark ?? '_______________________________________________' }}</div>
                @if($classSection->classTeacher)
                <div style="font-size:8px; color:#94a3b8; margin-top:4px;">{{ $classSection->classTeacher->full_name }}</div>
                @endif
            </div>
            <div class="remark-box">
                <div class="remark-title">{{ __('Conduct & Discipline') }}</div>
                <div class="remark-text">{{ $termResult->conduct ?? '_______________________________________________' }}</div>
            </div>
        </div>

        <div class="remark-section" style="margin-top:8px;">
            <div class="remark-box" style="grid-column: 1 / -1;">
                <div class="remark-title">{{ __("Principal's Remark") }}</div>
                <div class="remark-text">{{ $termResult->principal_remark ?? '___________________________________________________________________________________________' }}</div>
            </div>
        </div>

        {{-- Signatures --}}
        <div class="signatures">
            <div class="sig-block">
                <div class="sig-line">{{ __("Class Teacher's Signature & Date") }}</div>
            </div>
            <div class="sig-block">
                <div class="sig-line">{{ __("Principal's Signature & Stamp") }}</div>
            </div>
        </div>
    </div>
</body>
</html>
