{{-- Several receipts ticked on the payments list, printed in one go: each with its school copy, one copy per page. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans_choice(':count receipt|:count receipts', $receipts->count(), ['count' => $receipts->count()]) }}</title>
    @include('admin.fees.collect._receipt-head')
</head>
<body>

<div class="print-toolbar">
    <div class="toolbar-left">
        <div>
            <div class="toolbar-title">{{ trans_choice(':count receipt|:count receipts', $receipts->count(), ['count' => $receipts->count()]) }}</div>
            <div class="toolbar-subtitle">{{ $receipts->map(fn ($r) => $r['payment']->receipt_number)->implode(', ') }}</div>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button class="btn-print" onclick="window.print()">{{ __('Print all') }}</button>
        <button class="btn-close" onclick="window.close()">{{ __('Close') }}</button>
    </div>
</div>

@foreach($receipts as $receipt)
    @include('admin.fees.collect._receipt-copy', $receipt + ['copy' => 'original'])
    @include('admin.fees.collect._receipt-copy', $receipt + ['copy' => 'duplicate'])
@endforeach

</body>
</html>
