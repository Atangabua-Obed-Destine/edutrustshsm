{{--
    One printed copy of a fee receipt. $copy is "original" or "duplicate"
    (the school copy). Included by receipt.blade.php (one receipt) and
    receipts.blade.php (several ticked on the payments list).
--}}
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
    $reversed = $payment->isReversed();
@endphp
<div class="receipt-container receipt-copy{{ $reversed ? ' is-reversed' : '' }}">
    <div class="watermark">{{ $reversed ? __('REVERSED') : ($copy === 'duplicate' ? __('DUPLICATE') : __('ORIGINAL')) }}</div>
    <div class="top-bar"></div>

    {{-- School Header --}}
    <div class="receipt-header">
        <div class="country">Republic of Cameroon &mdash; R&eacute;publique du Cameroun</div>
        <div class="logo-area">
            @if($school && $school->logo)
                <img src="{{ asset('storage/' . $school->logo) }}" alt="Logo">
            @endif
            <div class="school-info">
                <div class="school-name">{{ $school->school_name ?? '' }}</div>
                <div class="school-details">
                    @if($school)
                        @if($school->po_box){{ __('P.O. Box') }} {{ $school->po_box }} &bull; @endif
                        {{ $school->city ?? '' }}{{ $school->region ? ', ' . $school->region : '' }}
                        @if($school->phone)<br>{{ __('Tel') }}: {{ $school->phone }}@endif
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
        <h2>{{ __('Fee Payment Receipt') }}</h2>
        <span class="receipt-no">{{ $payment->receipt_number }}</span>
    </div>

    {{-- A reversed receipt is kept and can be reprinted, but must never pass
         for money received. --}}
    @if($reversed)
    <div class="reversed-band" role="note">
        <strong>{{ __('REVERSED on :date', ['date' => $payment->reversed_at?->format('d/m/Y') ?? '—']) }}</strong>
        &mdash; {{ __('this receipt no longer counts as payment.') }}
        @if($payment->reversal_reason)<br>{{ __('Reason') }}: {{ $payment->reversal_reason }}@endif
        @if($payment->reversedBy)<br>{{ __('Reversed by') }}: {{ $payment->reversedBy->full_name }}@endif
    </div>
    @endif

    {{-- Receipt No + Date --}}
    <div class="receipt-meta-strip">
        <div class="meta-item">
            <span class="meta-label">{{ __('Receipt No') }}:</span>
            <span class="meta-value">{{ $payment->receipt_number }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">{{ __('Date') }}:</span>
            <span class="meta-value">{{ $payment->payment_date->translatedFormat('d M Y') }}</span>
        </div>
        <div class="meta-item">
            <span class="meta-label">{{ __('Academic Year') }}:</span>
            <span class="meta-value">{{ $session->name ?? '—' }}</span>
        </div>
    </div>

    {{-- Student Information --}}
    <div class="student-info">
        <div class="info-title">{{ __('Student Information') }}</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="label">{{ __('Matricule') }}:</span>
                <span class="value">{{ $student->student_id ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">{{ __('Full Name') }}:</span>
                <span class="value">{{ $student->full_name ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="label">{{ __('Class') }}:</span>
                <span class="value">{{ $classSection->name ?? '—' }}{{ $stream ? ' — ' . $stream->name : '' }}</span>
            </div>
            <div class="info-row">
                <span class="label">{{ __('Term') }}:</span>
                <span class="value">{{ $term->name ?? '—' }}</span>
            </div>
            @if($guardian)
            <div class="info-row">
                <span class="label">{{ __('Parent / Guardian') }}:</span>
                <span class="value">{{ $guardian->guardian_name ?: ($guardian->father_name ?: $guardian->mother_name) }}</span>
            </div>
            <div class="info-row">
                <span class="label">{{ __('Phone') }}:</span>
                <span class="value">{{ $guardian->guardian_phone ?: ($guardian->father_phone ?: $guardian->mother_phone) }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Payment Allocations --}}
    <div class="allocations-section">
        <div class="section-title">{{ __('Payment Breakdown') }}</div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>{{ __('Fee Category') }}</th>
                    <th class="right">{{ __('Amount Paid') }} ({{ $currency }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->allocations as $idx => $alloc)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td>{{ $alloc->studentFee->feeCategory->name ?? '—' }}@if($alloc->studentFee?->enrollment && (int) $alloc->studentFee->enrollment->academic_session_id !== (int) $payment->enrollment?->academic_session_id) ({{ __('Arrears :year', ['year' => $alloc->studentFee->enrollment->academicSession?->name]) }})@endif</td>
                    <td class="right">{{ number_format($alloc->amount) }}</td>
                </tr>
                @endforeach
                @if(($heldAsCredit ?? 0) > 0)
                <tr>
                    <td style="text-align: center;">{{ $payment->allocations->count() + 1 }}</td>
                    <td>{{ __('Held as credit (advance on future fees)') }}</td>
                    <td class="right">{{ number_format($heldAsCredit) }}</td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr style="background: #f1f5f9;">
                    <td colspan="2" style="text-align: right; text-transform: uppercase; font-size: 9.5px;">{{ __('Total Received') }}</td>
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
        <div class="section-title">{{ __('Payment Plan Instalment Status') }}</div>
        <table class="alloc-table">
            <thead>
                <tr>
                    <th>{{ __('Fee Category') }}</th>
                    <th class="right">{{ __('Plan Total') }} ({{ $currency }})</th>
                    <th style="text-align: center;">{{ __('Instalments') }}</th>
                    <th style="text-align: center;">{{ __('Progress') }}</th>
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
        <div class="section-title">{{ __('Account Summary (as of this payment)') }}</div>
        <div class="summary-grid">
            <div class="summary-box" style="border-color: #bfdbfe;">
                <div class="box-label" style="color: #2563eb;">{{ __('Total Fees') }}</div>
                <div class="box-value" style="color: #1e40af;">{{ number_format($totalFees) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
            <div class="summary-box" style="border-color: #bbf7d0; background: #f0fdf4;">
                <div class="box-label" style="color: #16a34a;">{{ __('Paid on fees') }}</div>
                <div class="box-value" style="color: #15803d;">{{ number_format($totalPaidToDate) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
            <div class="summary-box" style="border-color: {{ $overallBalance > 0 ? '#fecaca' : '#bbf7d0' }}; background: {{ $overallBalance > 0 ? '#fef2f2' : '#f0fdf4' }};">
                <div class="box-label" style="color: {{ $overallBalance > 0 ? '#dc2626' : '#16a34a' }};">{{ $overallBalance > 0 ? __('Balance Due') : __('Fully paid') }}</div>
                <div class="box-value" style="color: {{ $overallBalance > 0 ? '#dc2626' : '#15803d' }};">{{ number_format(max(0, $overallBalance)) }}</div>
                <div style="font-size: 7px; color: #94a3b8;">{{ $currency }}</div>
            </div>
        </div>
        @if(($arrearsOwed ?? 0) > 0)
        <div style="margin-top: 4px; font-size: 9px; color: #dc2626; font-weight: 600;">{{ __('Arrears from earlier years still owed') }}: {{ number_format($arrearsOwed) }} {{ $currency }}</div>
        @endif
    </div>

    {{-- Payment Method Details --}}
    <div class="payment-details">
        <div class="detail-row">
            <span class="detail-label">{{ __('Payment Method') }}:</span>
            <span class="detail-value">{{ $methodLabel }}</span>
        </div>
        @if($payment->payer_name)
        <div class="detail-row">
            <span class="detail-label">{{ __('Paid By') }}:</span>
            <span class="detail-value">{{ $payment->payer_name }}{{ $payment->payer_phone ? ' (' . $payment->payer_phone . ')' : '' }}</span>
        </div>
        @endif
        @if($payment->transaction_ref)
        <div class="detail-row">
            <span class="detail-label">{{ __('Transaction Ref') }}:</span>
            <span class="detail-value" style="font-family: 'Courier New', monospace;">{{ $payment->transaction_ref }}</span>
        </div>
        @endif
        @if($payment->notes)
        <div class="detail-row">
            <span class="detail-label">{{ __('Notes') }}:</span>
            <span class="detail-value" style="font-weight: 500; font-style: italic;">{{ $payment->notes }}</span>
        </div>
        @endif
    </div>

    {{-- Amount in Words --}}
    <div class="amount-words">
        <span class="label">{{ __('Amount in figures') }}:</span>
        <span class="words">{{ number_format($payment->amount) }} {{ $currency }}</span>
    </div>

    {{-- Signatures --}}
    <div class="receipt-footer">
        <div class="signature-row">
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">{{ __('Received By (Cashier)') }}</div>
                    <div class="sig-name">{{ $receivedBy?->full_name ?: '—' }}</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">{{ __('Parent/Guardian Signature') }}</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div class="sig-label">{{ ($form->school_level ?? null) === 'nursery_primary' ? __('Bursar / Head Teacher') : __('Bursar / Principal') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer Note --}}
    <div class="receipt-note">
        {{ __('This is a computer-generated receipt.') }} &bull; {{ __('Please keep this receipt for your records.') }} &bull; {{ __('Fees paid are non-refundable unless otherwise stated.') }}<br>
        @if($school && $school->website){{ $school->website }} &bull; @endif
        {{ __('Printed on') }} {{ now()->translatedFormat('d M Y, H:i') }}@if($copy === 'duplicate') &bull; <strong>{{ __('SCHOOL COPY') }}</strong>@endif
    </div>

    {{-- Anyone holding the paper can check it against the school's record. --}}
    <div class="verify-strip">
        <span class="verify-qr" aria-hidden="true">{!! \App\Support\QrCode::svg($payment->verifyUrl()) !!}</span>
        <span>
            {{ __('Scan the code, or open :url and type', ['url' => route('receipt.verify')]) }}<br>
            <strong class="verify-code">{{ $payment->verifyCodeForPrint() }}</strong>
            <span class="verify-hint">{{ __('to check this receipt with the school.') }}</span>
        </span>
    </div>

    <div class="bottom-bar"></div>
</div>
