<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Receipt check') }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 16px; }
        .card { max-width: 460px; margin: 40px auto; background: white; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .head { background: #134e4a; color: white; padding: 16px; text-align: center; }
        .head h1 { font-size: 15px; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .head p { font-size: 12px; margin: 4px 0 0; opacity: .8; }
        .body { padding: 20px; }
        .status { display: block; margin-bottom: 16px; padding: 10px 14px; border-radius: 10px; font-size: 14px; font-weight: 700; text-align: center; }
        .ok { background: #dcfce7; color: #166534; }
        .bad { background: #fee2e2; color: #991b1b; }
        .warn { background: #fef3c7; color: #92400e; }
        dl { margin: 0; font-size: 13px; }
        dl div { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; border-bottom: 1px solid #f1f5f9; }
        dt { color: #64748b; }
        dd { margin: 0; font-weight: 600; text-align: right; }
        form { display: flex; gap: 8px; margin-top: 16px; }
        input { flex: 1; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 15px; font-family: 'Courier New', monospace; text-transform: uppercase; letter-spacing: 1px; }
        button { padding: 9px 16px; border: 0; border-radius: 8px; background: #134e4a; color: white; font-weight: 600; cursor: pointer; }
        .hint { font-size: 12px; color: #64748b; margin: 0; }
    </style>
</head>
<body>
<main class="card">
    <div class="head">
        <h1>{{ $settings->school_name ?? config('app.name') }}</h1>
        <p>{{ __('Receipt check') }}</p>
    </div>
    <div class="body">
        @if($payment)
            @if($payment->isReversed())
                <p class="status bad" role="status">{{ __('This receipt was cancelled by the school on :date. It no longer counts as payment.', ['date' => $payment->reversed_at?->format('d/m/Y') ?? '—']) }}</p>
            @elseif($payment->verification_status === 'verified')
                <p class="status ok" role="status">{{ __('Genuine receipt, recorded by the school.') }}</p>
            @else
                <p class="status warn" role="status">{{ __('This receipt has not been confirmed by the school.') }}</p>
            @endif

            <dl>
                <div><dt>{{ __('Receipt No') }}</dt><dd>{{ $payment->receipt_number }}</dd></div>
                <div><dt>{{ __('Date') }}</dt><dd>{{ $payment->payment_date?->format('d/m/Y') }}</dd></div>
                <div><dt>{{ __('Amount') }}</dt><dd>{{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $settings->currency ?? 'FCFA' }}</dd></div>
                <div><dt>{{ __('Pupil') }}</dt><dd>{{ $payment->enrollment?->student?->full_name ?? '—' }}</dd></div>
                <div><dt>{{ __('Class') }}</dt><dd>{{ $payment->enrollment?->classSection?->name ?? '—' }}</dd></div>
                <div><dt>{{ __('Academic Year') }}</dt><dd>{{ $payment->enrollment?->academicSession?->name ?? '—' }}</dd></div>
            </dl>
            <p class="hint" style="margin-top: 14px;">{{ __('Check that these details match the paper receipt. If they do not, the paper has been altered.') }}</p>
        @else
            @if($asked)
                <p class="status bad" role="status">{{ __('No receipt has this code. Check the code and try again, or ask the school.') }}</p>
            @else
                <p class="hint">{{ __('Type the check code printed at the bottom of the receipt.') }}</p>
            @endif

            <form method="GET" action="{{ route('receipt.verify') }}">
                <label for="code" style="position:absolute;left:-9999px">{{ __('Check code') }}</label>
                <input id="code" name="code" value="{{ $code ?? '' }}" placeholder="XXXX-XXXX-XXXX" autocomplete="off" autofocus required maxlength="20">
                <button type="submit">{{ __('Check') }}</button>
            </form>
        @endif
    </div>
</main>
</body>
</html>
