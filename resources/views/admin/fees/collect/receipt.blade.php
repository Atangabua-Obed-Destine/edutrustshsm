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

    $methodLabel = \App\Models\Payment::methodLabel($payment->payment_method);
    $currency = $school->currency ?? 'XAF';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Receipt') }} {{ $payment->receipt_number }}</title>
    @include('admin.fees.collect._receipt-head')
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
            <div class="toolbar-title">{{ __('Fee Receipt') }} — {{ $payment->receipt_number }}@if($payment->isReversed()) <span style="color:#fecaca">({{ __('REVERSED') }})</span>@endif</div>
            <div class="toolbar-subtitle">{{ $student->full_name ?? __('Pupil') }} &bull; {{ $classSection->name ?? '' }}</div>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn-print" onclick="window.print()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            {{ __('Print Receipt') }}
        </button>
        <button class="btn-close" onclick="window.close()">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ __('Close') }}
        </button>
    </div>
</div>


@include('admin.fees.collect._receipt-copy', ['copy' => 'original'])

@include('admin.fees.collect._receipt-copy', ['copy' => 'duplicate'])

</body>
</html>
