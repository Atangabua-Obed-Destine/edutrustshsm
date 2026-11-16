{{-- Styles shared by the single and batch receipt pages. --}}
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

        /* A reversed receipt: red stamp and a band saying so. */
        .is-reversed .watermark { color: rgba(220, 38, 38, 0.16); font-size: 54px; }
        .reversed-band { margin: 6px 16px 0; padding: 6px 10px; border: 2px solid #dc2626; border-radius: 4px; background: #fef2f2; color: #991b1b; font-size: 9.5px; text-align: center; }
        .verify-strip { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 2px 16px 6px; font-size: 7.5px; color: #64748b; text-align: left; }
        .verify-qr svg { display: block; width: 52px; height: 52px; }
        .verify-qr svg .dark, .verify-qr svg path { fill: #0f172a; }
        @media screen { .receipt-copy + .receipt-copy { margin-top: 30px; } }
        .verify-code { font-family: 'Courier New', monospace; font-size: 9px; color: #1e293b; letter-spacing: 1px; }

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
