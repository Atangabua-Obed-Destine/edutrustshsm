<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Student ID Cards') }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #e5e7eb; }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .cards-grid { padding: 0; gap: 6mm; }
            .id-card { break-inside: avoid; box-shadow: none; }
        }

        /* Print toolbar */
        .toolbar {
            position: sticky; top: 0; z-index: 50;
            background: #1e293b; color: white; padding: 12px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .toolbar h2 { font-size: 16px; font-weight: 600; }
        .toolbar button {
            padding: 8px 20px; background: #2563eb; color: white; border: none;
            border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .toolbar button:hover { background: #1d4ed8; }

        /* Grid layout: 2 cards per row (credit-card size ~85.6mm x 53.98mm) */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }

        /* ───── CARD ───── */
        .id-card {
            width: 100%;
            aspect-ratio: 8.56 / 10.8;
            border-radius: 10px;
            overflow: hidden;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }

        /* ── FRONT ── */
        .card-front { display: flex; flex-direction: column; height: 100%; }

        /* Header band */
        .card-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 8px 12px 6px;
            text-align: center;
            position: relative;
        }
        .card-header .school-logo {
            width: 36px; height: 36px; border-radius: 50%; object-fit: contain;
            background: white; padding: 2px; display: inline-block; vertical-align: middle;
        }
        .card-header .school-logo-placeholder {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,0.2); display: inline-flex;
            align-items: center; justify-content: center; font-size: 14px; font-weight: 800;
        }
        .card-header .school-name {
            font-size: 11px; font-weight: 800; letter-spacing: 0.5px;
            text-transform: uppercase; margin-top: 2px; line-height: 1.2;
        }
        .card-header .school-location {
            font-size: 7px; color: #94a3b8; letter-spacing: 0.5px; margin-top: 1px;
        }
        .card-header .card-label {
            display: inline-block; margin-top: 4px;
            background: #f59e0b; color: #1e293b;
            font-size: 7px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;
            padding: 2px 10px; border-radius: 3px;
        }

        /* Body */
        .card-body {
            flex: 1; padding: 10px 12px 8px;
            display: flex; gap: 10px;
        }

        /* Photo */
        .card-photo {
            width: 72px; height: 88px; flex-shrink: 0;
            border-radius: 6px; border: 2px solid #e2e8f0;
            overflow: hidden; background: #f1f5f9;
        }
        .card-photo img { width: 100%; height: 100%; object-fit: cover; }
        .card-photo .no-photo {
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; font-weight: 800; color: #94a3b8; background: #f1f5f9;
        }

        /* Details */
        .card-details { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .card-details .student-name {
            font-size: 12px; font-weight: 800; color: #1e293b;
            text-transform: uppercase; line-height: 1.2; margin-bottom: 6px;
        }
        .card-details .detail-row {
            display: flex; font-size: 8px; margin-bottom: 2px;
        }
        .card-details .detail-label {
            width: 56px; flex-shrink: 0;
            font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .card-details .detail-value {
            font-weight: 600; color: #1e293b;
        }

        /* Student ID bar */
        .card-id-bar {
            background: #1e293b; color: white;
            text-align: center; padding: 4px 12px;
            font-size: 12px; font-weight: 800; letter-spacing: 2px;
        }

        /* Footer band */
        .card-footer {
            background: #f8fafc; border-top: 1px solid #e2e8f0;
            padding: 4px 12px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 7px; color: #64748b;
        }

        /* ── BACK ── */
        .card-back { display: flex; flex-direction: column; height: 100%; }

        .card-back .back-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white; text-align: center;
            padding: 8px 12px 6px;
        }
        .card-back .back-header .title {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
        }

        .card-back .back-body {
            flex: 1; padding: 10px 14px; font-size: 8px; line-height: 1.5;
        }
        .card-back .back-body .section-title {
            font-size: 8px; font-weight: 800; color: #1e293b;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-top: 6px; margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0; padding-bottom: 2px;
        }
        .card-back .back-body .section-title:first-child { margin-top: 0; }
        .card-back .back-body .info-row {
            display: flex; justify-content: space-between; margin-bottom: 1px;
        }
        .card-back .back-body .info-label { color: #64748b; }
        .card-back .back-body .info-value { font-weight: 600; color: #1e293b; }

        .card-back .back-body .terms-text {
            font-size: 7px; color: #64748b; line-height: 1.4; margin-top: 8px;
        }
        .card-back .back-body .terms-text li { margin-bottom: 2px; }

        .card-back .signature-area {
            padding: 6px 14px 8px; text-align: center;
        }
        .card-back .signature-line {
            width: 120px; border-top: 1px solid #475569;
            margin: 0 auto; padding-top: 2px;
            font-size: 7px; font-weight: 700; color: #1e293b; text-transform: uppercase;
        }

        .card-back .back-footer {
            background: #1e293b; color: #94a3b8;
            text-align: center; padding: 4px 12px;
            font-size: 7px; letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    {{-- Print Toolbar --}}
    <div class="toolbar no-print">
        <h2>{{ __('ID Cards') }} &mdash; {{ $enrollments->count() }} {{ __('student(s)') }}</h2>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.history.back()" style="background: #475569;">{{ __('Back') }}</button>
            <button onclick="window.print()">
                <svg style="width: 16px; height: 16px; display: inline; vertical-align: middle; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                {{ __('Print') }}
            </button>
        </div>
    </div>

    {{-- ═══════════ FRONT CARDS ═══════════ --}}
    <div style="text-align: center; padding: 16px 0 4px; font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 1px;" class="no-print">
        {{ __('Front Side') }}
    </div>
    <div class="cards-grid">
        @foreach($enrollments as $enrollment)
        @php
            $student = $enrollment->student;
            $guardian = $student->guardian;
            $classSection = $enrollment->classSection;
            $form = $classSection->form ?? null;
            $stream = $enrollment->stream;
            $session = $enrollment->academicSession;
        @endphp
        <div class="id-card">
            <div class="card-front">
                {{-- Header --}}
                <div class="card-header">
                    @if($settings && $settings->logo)
                        <img src="{{ asset('storage/' . $settings->logo) }}" alt="" class="school-logo">
                    @else
                        <div class="school-logo-placeholder">LC</div>
                    @endif
                    <div class="school-name">{{ $settings->school_name ?? 'School Name' }}</div>
                    <div class="school-location">
                        @if($settings)
                            {{ implode(' | ', array_filter([$settings->po_box, $settings->city, $settings->region])) }}
                        @endif
                    </div>
                    <div class="card-label">{{ __('Student Identity Card') }}</div>
                </div>

                {{-- Body --}}
                <div class="card-body">
                    <div class="card-photo">
                        @if($student->photo)
                            <img src="{{ asset('storage/' . $student->photo) }}" alt="">
                        @else
                            <div class="no-photo">{{ strtoupper(substr($student->first_name, 0, 1) . substr($student->last_name, 0, 1)) }}</div>
                        @endif
                    </div>
                    <div class="card-details">
                        <div class="student-name">{{ $student->last_name }} {{ $student->first_name }} {{ $student->other_names }}</div>
                        <div class="detail-row">
                            <span class="detail-label">{{ __('Class') }}:</span>
                            <span class="detail-value">{{ $classSection->name ?? '—' }}</span>
                        </div>
                        @if($stream)
                        <div class="detail-row">
                            <span class="detail-label">{{ __('Stream') }}:</span>
                            <span class="detail-value">{{ $stream->name }}</span>
                        </div>
                        @endif
                        <div class="detail-row">
                            <span class="detail-label">{{ __('D.O.B') }}:</span>
                            <span class="detail-value">{{ $student->date_of_birth ? $student->date_of_birth->format('d/m/Y') : '—' }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">{{ __('Gender') }}:</span>
                            <span class="detail-value">{{ ucfirst($student->gender ?? '—') }}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">{{ __('Session') }}:</span>
                            <span class="detail-value">{{ $session->name ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                {{-- Student ID Bar --}}
                <div class="card-id-bar">{{ $student->student_id }}</div>

                {{-- Footer --}}
                <div class="card-footer">
                    <span>{{ $settings->motto ?? '' }}</span>
                    <span>{{ $settings->phone ?? '' }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ═══════════ BACK CARDS ═══════════ --}}
    <div style="page-break-before: always;"></div>
    <div style="text-align: center; padding: 16px 0 4px; font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 1px;" class="no-print">
        {{ __('Back Side') }}
    </div>
    <div class="cards-grid">
        @foreach($enrollments as $enrollment)
        @php
            $student = $enrollment->student;
            $guardian = $student->guardian;
            $session = $enrollment->academicSession;
        @endphp
        <div class="id-card">
            <div class="card-back">
                {{-- Back Header --}}
                <div class="back-header">
                    <div class="title">{{ $settings->school_name ?? 'School Name' }}</div>
                </div>

                {{-- Back Body --}}
                <div class="back-body">
                    {{-- Emergency Contact --}}
                    <div class="section-title">{{ __('Emergency Contact') }}</div>
                    @if($guardian)
                    <div class="info-row">
                        <span class="info-label">{{ __('Name') }}:</span>
                        <span class="info-value">{{ $guardian->guardian_name ?? $guardian->father_name ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('Phone') }}:</span>
                        <span class="info-value">{{ $guardian->guardian_phone ?? $guardian->father_phone ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('Relation') }}:</span>
                        <span class="info-value">{{ ucfirst($guardian->guardian_relationship ?? 'Parent') }}</span>
                    </div>
                    @else
                    <div style="color: #94a3b8; font-style: italic;">{{ __('No guardian on file') }}</div>
                    @endif

                    {{-- Student Info --}}
                    <div class="section-title">{{ __('Student Info') }}</div>
                    <div class="info-row">
                        <span class="info-label">{{ __('Blood Group') }}:</span>
                        <span class="info-value">{{ $student->blood_group ?? '—' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('Residence') }}:</span>
                        <span class="info-value">{{ ucfirst(str_replace('_', ' ', $enrollment->residence_type ?? '—')) }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">{{ __('Address') }}:</span>
                        <span class="info-value">{{ $student->home_address ?? $student->town ?? '—' }}</span>
                    </div>

                    {{-- Terms --}}
                    <div class="section-title">{{ __('Terms & Conditions') }}</div>
                    <ul class="terms-text" style="padding-left: 12px;">
                        <li>{{ __('This card is non-transferable.') }}</li>
                        <li>{{ __('Must be carried at all times on campus.') }}</li>
                        <li>{{ __('If found, please return to the school.') }}</li>
                        <li>{{ __('Valid for the') }} {{ $session->name ?? '' }} {{ __('academic session only.') }}</li>
                    </ul>
                </div>

                {{-- Signature --}}
                <div class="signature-area">
                    <div class="signature-line">{{ __('Principal\'s Signature & Stamp') }}</div>
                </div>

                {{-- Back Footer --}}
                <div class="back-footer">
                    @if($settings)
                        {{ implode(' | ', array_filter([$settings->email, $settings->website, $settings->phone])) }}
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</body>
</html>
