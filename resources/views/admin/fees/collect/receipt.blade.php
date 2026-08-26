@php
    $student = $payment->enrollment->student ?? null;
    $enrollment = $payment->enrollment ?? null;
    $session = $enrollment->academicSession ?? null;
    $term = $enrollment->term ?? null;
    $classSection = $enrollment->classSection ?? null;
    $form = $classSection->form ?? null;
    $stream = $enrollment->stream ?? null;
    $guardian = $student->guardian ?? null;
    $receivedBy = $payment->receivedBy ?? null;

    $paymentMethodLabels = [
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'mtn_momo' => 'MTN Mobile Money',
        'orange_money' => 'Orange Money',
        'edutrustpay' => 'EduTrustPay',
    ];
    $methodLabel = $paymentMethodLabels[$payment->payment_method] ?? ucfirst($payment->payment_method);
    $currency = $school->currency ?? 'XAF';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page {
            size: A5 portrait;
            margin: 8mm;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            background: #f1f5f9;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .receipt-container {
            width: 148mm;
            min-height: auto;
            margin: 20px auto;
            background: #fff;
            border: 2px solid #1e293b;
            position: relative;
            overflow: hidden;
        }

        /* Top decorative border bar */
        .top-bar {
            height: 6px;
            background: linear-gradient(90deg, #1e3a5f, #0ea5e9, #1e3a5f);
        }

        /* Header section */
        .receipt-header {
            text-align: center;
            padding: 12px 16px 8px 16px;
            border-bottom: 2px solid #1e293b;
            position: relative;
        }
        .receipt-header .logo-area {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 4px;
        }
        .receipt-header .logo-area img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }
        .receipt-header .school-info {
            text-align: center;
        }
        .receipt-header .country {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #64748b;
            margin-bottom: 1px;
        }
        .receipt-header .school-name {
            font-size: 16px;
            font-weight: 900;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.15;
        }
        .receipt-header .school-details {
            font-size: 8.5px;
            color: #475569;
            margin-top: 2px;
            line-height: 1.35;
        }
        .receipt-header .motto {
            font-size: 8px;
            font-style: italic;
            color: #0ea5e9;
            margin-top: 2px;
            letter-spacing: 0.5px;
        }

        /* Receipt title band */
        .receipt-title-band {
            background: #1e293b;
            color: #fff;
            text-align: center;
            padding: 6px 16px;
            position: relative;
        }
        .receipt-title-band h2 {
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .receipt-title-band .receipt-no {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            background: rgba(255,255,255,0.15);
            padding: 2px 10px;
            border-radius: 4px;
        }

        /* Receipt number & date strip */
        .receipt-meta-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .receipt-meta-strip .meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .receipt-meta-strip .meta-label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .receipt-meta-strip .meta-value {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            font-family: 'Courier New', monospace;
        }

        /* Student info section */
        .student-info {
            padding: 10px 16px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .student-info .info-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 16px;
        }
        .info-row {
            display: flex;
            gap: 4px;
            align-items: baseline;
        }
        .info-row .label {
            font-size: 9px;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
            min-width: 65px;
        }
        .info-row .value {
            font-size: 10px;
            font-weight: 700;
            color: #1e293b;
            border-bottom: 1px dotted #cbd5e1;
            flex: 1;
            padding-bottom: 1px;
        }

        /* Fee allocations table */
        .allocations-section {
            padding: 10px 16px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .allocations-section .section-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .alloc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .alloc-table thead th {
            background: #f1f5f9;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .alloc-table thead th.right { text-align: right; }
        .alloc-table tbody td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            color: #334155;
        }
        .alloc-table tbody td.right {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        .alloc-table tfoot td {
            padding: 6px 8px;
            border: 1px solid #1e293b;
            font-weight: 800;
            font-size: 10.5px;
        }
        .alloc-table tfoot td.right {
            text-align: right;
            font-family: 'Courier New', monospace;
        }

        /* Account summary */
        .account-summary {
            padding: 10px 16px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .account-summary .section-title {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 6px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
        }
        .summary-box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
            text-align: center;
        }
        .summary-box .box-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .summary-box .box-value {
            font-size: 12px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
        }

        /* Payment details strip */
        .payment-details {
            padding: 8px 16px;
            background: #f8fafc;
            border-bottom: 1px dashed #cbd5e1;
        }
        .payment-details .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2px 0;
        }
        .payment-details .detail-label {
            font-size: 9px;
            color: #64748b;
            font-weight: 600;
        }
        .payment-details .detail-value {
            font-size: 9.5px;
            color: #1e293b;
            font-weight: 700;
        }

        /* Amount in words */
        .amount-words {
            padding: 8px 16px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .amount-words .label {
            font-size: 8px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }
        .amount-words .words {
            font-size: 10px;
            font-weight: 700;
            font-style: italic;
            color: #1e293b;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 2px;
            margin-top: 2px;
        }

        /* Footer signatures */
        .receipt-footer {
            padding: 14px 16px 10px 16px;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            gap: 40px;
            margin-top: 6px;
        }
        .signature-block {
            flex: 1;
            text-align: center;
        }
        .signature-block .sig-line {
            border-top: 1px solid #334155;
            margin-top: 28px;
            padding-top: 3px;
        }
        .signature-block .sig-label {
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .signature-block .sig-name {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 1px;
        }

        /* Footer note */
        .receipt-note {
            text-align: center;
            padding: 6px 16px 8px 16px;
            font-size: 7.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }

        /* Bottom bar */
        .bottom-bar {
            height: 6px;
            background: linear-gradient(90deg, #1e3a5f, #0ea5e9, #1e3a5f);
        }

        /* Watermark for duplicate */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 60px;
            font-weight: 900;
            color: rgba(14, 165, 233, 0.06);
            text-transform: uppercase;
            letter-spacing: 10px;
            pointer-events: none;
            white-space: nowrap;
        }

        /* Print toolbar */
        .print-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .print-toolbar .toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .print-toolbar .toolbar-title {
            font-size: 14px;
            font-weight: 700;
        }
        .print-toolbar .toolbar-subtitle {
            font-size: 11px;
            color: #94a3b8;
        }
        .print-toolbar button {
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .15s;
        }
        .btn-print {
            background: #0ea5e9;
            color: #fff;
        }
        .btn-print:hover { background: #0284c7; }
        .btn-close {
            background: #475569;
            color: #fff;
        }
        .btn-close:hover { background: #64748b; }

        /* Screen adjustments */
        @media screen {
            body { padding-top: 60px; }
            .receipt-container { box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-radius: 4px; }
        }

        /* Hide toolbar when inside iframe */
        body.in-iframe .print-toolbar { display: none !important; }
        body.in-iframe { padding-top: 0 !important; }

        @media print {
            .print-toolbar { display: none !important; }
            body {
                background: #fff;
                padding: 0;
            }
            .receipt-container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
            .receipt-copy + .receipt-copy {
                page-break-before: always;
            }
        }
    </style>
    <script>
        // Detect if loaded inside an iframe and add class to body
        if (window.self !== window.top) {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('in-iframe');
            });
        }
    </script>
</head>
<body>

{{-- Print Toolbar --}}
<div class="print-toolbar">
    <div class="toolbar-left">
        <div>
            <div class="toolbar-title">Fee Receipt — {{ $payment->receipt_number }}</div>
            <div class="toolbar-subtitle">{{ $student->full_name ?? 'Student' }} &bull; {{ $classSection->name ?? '' }}</div>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print Receipt
        </button>
        <button class="btn-close" onclick="window.close()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            Close
        </button>
    </div>
</div>

{{-- ===== ORIGINAL COPY ===== --}}
<div class="receipt-container receipt-copy">
    <div class="watermark">ORIGINAL</div>
    <div class="top-bar"></div>

    {{-- School Header --}}
    <div class="receipt-header">
        <div class="country">Republic of Cameroon &mdash; R&eacute;publique du Cameroun</div>
        <div class="logo-area">
            @if($school && $school->logo)
                <img src="{{ asset('storage/' . $school->logo) }}" alt="Logo">
            @endif
            <div class="school-info">
                <div class="school-name">{{ $school->school_name ?? 'School Name' }}</div>
                <div class="school-details">
                    @if($school)
                        @if($school->po_box)P.O. Box {{ $school->po_box }} &bull; @endif
                        {{ $school->city ?? '' }}{{ $school->region ? ', ' . $school->region : '' }}
                        @if($school->phone)<br>Tel: {{ $school->phone }}@endif
                        @if($school->email) &bull; {{ $school->email }}@endif
                    @endif
                </div>
                @if($school && $school->motto)
                    <div class="motto">&ldquo;{{ $school->motto }}&rdquo;</div>
                @endif
            </div>
            @if($school && $school->logo)
                <img src="{{ asset('storage/' . $school->logo) }}" alt="Logo" style="opacity: 0;">
            @endif
        </div>
    </div>

    {{-- Receipt Title --}}
    <div class="receipt-title-band">
        <h2>Fee Payment Receipt</h2>
        <span class="receipt-no">{{ $payment->receipt_number }}</span>
    </div>

    {{-- Receipt No + Date --}}
    <div class="receipt-meta-strip">
        <div class="meta-item">
            <span class="meta-label">Receipt No:</span>
            <span class="meta-value">{{ $payment->receipt_number }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Date:</span>
            <span class="meta-value">{{ $payment->payment_date->format('d M Y') }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Session:</span>
            <span class="meta-value">{{ $session->name ?? '—' }}</span>
        </div>
    </div>

    {{-- Student Information --}}
    <div class="student-info">
        <div class="info-title">Student Information</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="label">Student ID:</span>
                <span class="value">{{ $student->student_id ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Full Name:</span>
                <span class="value">{{ $student->full_name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Class:</span>
                <span class="value">{{ $classSection->name ?? '—' }}{{ $stream ? ' — ' . $stream->name : '' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Term:</span>
                <span class="value">{{ $term->name ?? '—' }}</span>
            </div>
            @if($guardian)
            <div class="info-row">
                <span class="label">Parent/Gdn:</span>
                <span class="value">{{ $guardian->guardian_name ?: ($guardian->father_name ?: $guardian->mother_name) }}</span>
            </div>
            <div class="info-row">
                <span class="label">Phone:</span>
                <span class="value">{{ $guardian->guardian_phone ?: ($guardian->father_phone ?: $guardian->mother_phone) }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Payment Allocations --}}
    <div class="allocations-section">
        <div class="section-title">Payment Breakdown</div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Fee Category</th>
                    <th class="right">Amount Paid ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->allocations as $idx => $alloc)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>{{ $alloc->studentFee->feeCategory->name ?? '—' }}</td>
                    <td class="right">{{ number_format($alloc->amount) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9;">
                    <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 9.5px;">Total Received</td>
                    <td class="right" style="font-size: 12px; color: #15803d;">{{ number_format($payment->amount) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Payment Plan Instalment Status (if any fee has an active/completed plan) --}}
    @php
        $planAllocations = $payment->allocations->filter(fn($a) => $a->studentFee->paymentPlan && in_array($a->studentFee->paymentPlan->status, ['active', 'completed']));
    @endphp
    @if($planAllocations->isNotEmpty())
    <div class="allocations-section">
        <div class="section-title">Payment Plan Instalment Status</div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th>Fee Category</th>
                    <th class="right">Plan Total ({{ $currency }})</th>
                    <th style="text-align: center;">Instalments</th>
                    <th style="text-align: center;">Progress</th>
                </tr>
            </thead>
            <tbody>
                @foreach($planAllocations as $pa)
                @php
                    $pp = $pa->studentFee->paymentPlan;
                    $ppPaid = $pp->installments->where('status', 'paid')->count();
                @endphp
                <tr>
                    <td>{{ $pa->studentFee->feeCategory->name ?? '—' }}</td>
                    <td class="right">{{ number_format($pp->total_amount) }}</td>
                    <td style="text-align: center; font-weight: 600;">{{ $ppPaid }}/{{ $pp->number_of_installments }}</td>
                    <td style="text-align: center;">
                        <span style="display: inline-block; padding: 1px 8px; border-radius: 9999px; font-size: 8px; font-weight: 700; background: {{ $pp->status === 'completed' ? '#dcfce7' : '#eff6ff' }}; color: {{ $pp->status === 'completed' ? '#15803d' : '#2563eb' }}; border: 1px solid {{ $pp->status === 'completed' ? '#bbf7d0' : '#bfdbfe' }};">{{ $pp->progress }}%{{ $pp->status === 'completed' ? ' ✔' : '' }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Account Summary --}}
    <div class="account-summary">
        <div class="section-title">Account Summary (as of this payment)</div>
        <div class="summary-grid">
            <div class="summary-box" style="border-color: #bfdbfe;">
                <div class="box-label" style="color: #2563eb;">Total Fees</div>
                <div class="box-value" style="color: #1e40af;">{{ number_format($totalFees) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
            <div class="summary-box" style="border-color: #bbf7d0; background: #f0fdf4;">
                <div class="box-label" style="color: #16a34a;">Paid to Date</div>
                <div class="box-value" style="color: #15803d;">{{ number_format($totalPaidToDate) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
            <div class="summary-box" style="border-color: {{ $overallBalance > 0 ? '#fecaca' : '#bbf7d0' }}; background: {{ $overallBalance > 0 ? '#fef2f2' : '#f0fdf4' }};">
                <div class="box-label" style="color: {{ $overallBalance > 0 ? '#dc2626' : '#16a34a' }};">{{ $overallBalance > 0 ? 'Balance Due' : 'Cleared' }}</div>
                <div class="box-value" style="color: {{ $overallBalance > 0 ? '#dc2626' : '#15803d' }};">{{ number_format(abs($overallBalance)) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
        </div>
    </div>

    {{-- Payment Method Details --}}
    <div class="payment-details">
        <div class="detail-row">
            <span class="detail-label">Payment Method:</span>
            <span class="detail-value">{{ $methodLabel }}</span>
        </div>
        @if($payment->payer_name)
        <div class="detail-row">
            <span class="detail-label">Paid By:</span>
            <span class="detail-value">{{ $payment->payer_name }}{{ $payment->payer_phone ? ' (' . $payment->payer_phone . ')' : '' }}</span>
        </div>
        @endif
        @if($payment->transaction_ref)
        <div class="detail-row">
            <span class="detail-label">Transaction Ref:</span>
            <span class="detail-value" style="font-family: 'Courier New', monospace;">{{ $payment->transaction_ref }}</span>
        </div>
        @endif
        @if($payment->notes)
        <div class="detail-row">
            <span class="detail-label">Notes:</span>
            <span class="detail-value" style="font-weight: 500; font-style: italic;">{{ $payment->notes }}</span>
        </div>
        @endif
    </div>

    {{-- Amount in Words --}}
    <div class="amount-words">
        <span class="label">Amount in figures:</span>
        <span class="words">{{ number_format($payment->amount) }} {{ $currency }}</span>
    </div>

    {{-- Signatures --}}
    <div class="receipt-footer">
        <div class="signature-row">
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">Received By (Cashier)</div>
                    <div class="sig-name">{{ $receivedBy->name ?? '—' }}</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">Parent/Guardian Signature</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">Bursar / Principal</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Note --}}
    <div class="receipt-note">
        This is a computer-generated receipt. &bull; Please keep this receipt for your records. &bull; Fees paid are non-refundable unless otherwise stated.<br>
        @if($school && $school->website){{ $school->website }} &bull; @endif
        Printed on {{ now()->format('d M Y, h:i A') }}
    </div>

    <div class="bottom-bar"></div>
</div>

{{-- ===== DUPLICATE COPY ===== --}}
<div class="receipt-container receipt-copy" style="margin-top: 30px;">
    <div class="watermark">DUPLICATE</div>
    <div class="top-bar"></div>

    <div class="receipt-header">
        <div class="country">Republic of Cameroon &mdash; R&eacute;publique du Cameroun</div>
        <div class="logo-area">
            @if($school && $school->logo)
                <img src="{{ asset('storage/' . $school->logo) }}" alt="Logo">
            @endif
            <div class="school-info">
                <div class="school-name">{{ $school->school_name ?? 'School Name' }}</div>
                <div class="school-details">
                    @if($school)
                        @if($school->po_box)P.O. Box {{ $school->po_box }} &bull; @endif
                        {{ $school->city ?? '' }}{{ $school->region ? ', ' . $school->region : '' }}
                        @if($school->phone)<br>Tel: {{ $school->phone }}@endif
                        @if($school->email) &bull; {{ $school->email }}@endif
                    @endif
                </div>
                @if($school && $school->motto)
                    <div class="motto">&ldquo;{{ $school->motto }}&rdquo;</div>
                @endif
            </div>
            @if($school && $school->logo)
                <img src="{{ asset('storage/' . $school->logo) }}" alt="Logo" style="opacity: 0;">
            @endif
        </div>
    </div>

    <div class="receipt-title-band">
        <h2>Fee Payment Receipt</h2>
        <span class="receipt-no">{{ $payment->receipt_number }}</span>
    </div>

    <div class="receipt-meta-strip">
        <div class="meta-item">
            <span class="meta-label">Receipt No:</span>
            <span class="meta-value">{{ $payment->receipt_number }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Date:</span>
            <span class="meta-value">{{ $payment->payment_date->format('d M Y') }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">Session:</span>
            <span class="meta-value">{{ $session->name ?? '—' }}</span>
        </div>
    </div>

    <div class="student-info">
        <div class="info-title">Student Information</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="label">Student ID:</span>
                <span class="value">{{ $student->student_id ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Full Name:</span>
                <span class="value">{{ $student->full_name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Class:</span>
                <span class="value">{{ $classSection->name ?? '—' }}{{ $stream ? ' — ' . $stream->name : '' }}</span>
            </div>
            <div class="info-row">
                <span class="label">Term:</span>
                <span class="value">{{ $term->name ?? '—' }}</span>
            </div>
            @if($guardian)
            <div class="info-row">
                <span class="label">Parent/Gdn:</span>
                <span class="value">{{ $guardian->guardian_name ?: ($guardian->father_name ?: $guardian->mother_name) }}</span>
            </div>
            <div class="info-row">
                <span class="label">Phone:</span>
                <span class="value">{{ $guardian->guardian_phone ?: ($guardian->father_phone ?: $guardian->mother_phone) }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="allocations-section">
        <div class="section-title">Payment Breakdown</div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Fee Category</th>
                    <th class="right">Amount Paid ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->allocations as $idx => $alloc)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>{{ $alloc->studentFee->feeCategory->name ?? '—' }}</td>
                    <td class="right">{{ number_format($alloc->amount) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9;">
                    <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 9.5px;">Total Received</td>
                    <td class="right" style="font-size: 12px; color: #15803d;">{{ number_format($payment->amount) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- Payment Plan Instalment Status (DUPLICATE) --}}
    @if($planAllocations->isNotEmpty())
    <div class="allocations-section">
        <div class="section-title">Payment Plan Instalment Status</div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th>Fee Category</th>
                    <th class="right">Plan Total ({{ $currency }})</th>
                    <th style="text-align: center;">Instalments</th>
                    <th style="text-align: center;">Progress</th>
                </tr>
            </thead>
            <tbody>
                @foreach($planAllocations as $pa)
                @php
                    $pp = $pa->studentFee->paymentPlan;
                    $ppPaid = $pp->installments->where('status', 'paid')->count();
                @endphp
                <tr>
                    <td>{{ $pa->studentFee->feeCategory->name ?? '—' }}</td>
                    <td class="right">{{ number_format($pp->total_amount) }}</td>
                    <td style="text-align: center; font-weight: 600;">{{ $ppPaid }}/{{ $pp->number_of_installments }}</td>
                    <td style="text-align: center;">
                        <span style="display: inline-block; padding: 1px 8px; border-radius: 9999px; font-size: 8px; font-weight: 700; background: {{ $pp->status === 'completed' ? '#dcfce7' : '#eff6ff' }}; color: {{ $pp->status === 'completed' ? '#15803d' : '#2563eb' }}; border: 1px solid {{ $pp->status === 'completed' ? '#bbf7d0' : '#bfdbfe' }};">{{ $pp->progress }}%{{ $pp->status === 'completed' ? ' ✔' : '' }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="account-summary">
        <div class="section-title">Account Summary (as of this payment)</div>
        <div class="summary-grid">
            <div class="summary-box" style="border-color: #bfdbfe;">
                <div class="box-label" style="color: #2563eb;">Total Fees</div>
                <div class="box-value" style="color: #1e40af;">{{ number_format($totalFees) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
            <div class="summary-box" style="border-color: #bbf7d0; background: #f0fdf4;">
                <div class="box-label" style="color: #16a34a;">Paid to Date</div>
                <div class="box-value" style="color: #15803d;">{{ number_format($totalPaidToDate) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
            <div class="summary-box" style="border-color: {{ $overallBalance > 0 ? '#fecaca' : '#bbf7d0' }}; background: {{ $overallBalance > 0 ? '#fef2f2' : '#f0fdf4' }};">
                <div class="box-label" style="color: {{ $overallBalance > 0 ? '#dc2626' : '#16a34a' }};">{{ $overallBalance > 0 ? 'Balance Due' : 'Cleared' }}</div>
                <div class="box-value" style="color: {{ $overallBalance > 0 ? '#dc2626' : '#15803d' }};">{{ number_format(abs($overallBalance)) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
        </div>
    </div>

    <div class="payment-details">
        <div class="detail-row">
            <span class="detail-label">Payment Method:</span>
            <span class="detail-value">{{ $methodLabel }}</span>
        </div>
        @if($payment->payer_name)
        <div class="detail-row">
            <span class="detail-label">Paid By:</span>
            <span class="detail-value">{{ $payment->payer_name }}{{ $payment->payer_phone ? ' (' . $payment->payer_phone . ')' : '' }}</span>
        </div>
        @endif
        @if($payment->transaction_ref)
        <div class="detail-row">
            <span class="detail-label">Transaction Ref:</span>
            <span class="detail-value" style="font-family: 'Courier New', monospace;">{{ $payment->transaction_ref }}</span>
        </div>
        @endif
        @if($payment->notes)
        <div class="detail-row">
            <span class="detail-label">Notes:</span>
            <span class="detail-value" style="font-weight: 500; font-style: italic;">{{ $payment->notes }}</span>
        </div>
        @endif
    </div>

    <div class="amount-words">
        <span class="label">Amount in figures:</span>
        <span class="words">{{ number_format($payment->amount) }} {{ $currency }}</span>
    </div>

    <div class="receipt-footer">
        <div class="signature-row">
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">Received By (Cashier)</div>
                    <div class="sig-name">{{ $receivedBy->name ?? '—' }}</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">Parent/Guardian Signature</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">Bursar / Principal</div>
                </div>
            </div>
        </div>
    </div>

    <div class="receipt-note">
        This is a computer-generated receipt. &bull; Please keep this receipt for your records. &bull; Fees paid are non-refundable unless otherwise stated.<br>
        @if($school && $school->website){{ $school->website }} &bull; @endif
        Printed on {{ now()->format('d M Y, h:i A') }} &bull; <strong>SCHOOL COPY</strong>
    </div>

    <div class="bottom-bar"></div>
</div>

</body>
</html>
