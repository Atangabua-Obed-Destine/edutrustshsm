{{--
    The steps before the decision (AdmissionApplication::STEPS): each signed
    off, waived with a reason, or sent back with a reason. Accepting needs all
    three. The history underneath keeps every sign-off, even those sent back.
--}}
@php
    $__standing = $application->stepStanding();
    $__next = $application->nextStep();
    $__open = ! in_array($application->status, ['accepted', 'enrolled', 'rejected'], true);
    $__canAct = $__open && auth()->user()->can('admission.accept');
@endphp
<div style="background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px;" data-admission-steps>
    <h3 style="font-size: 0.88rem; font-weight: 700; color: #0f172a; margin-bottom: 4px;">{{ __('Admission steps') }}</h3>
    <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 14px;">{{ __('Each step is signed off, or marked not required with a reason, before the application can be accepted.') }}</p>

    @foreach(['step', 'outcome', 'note', 'score'] as $__field)
        @error($__field)
        <p role="alert" style="margin-bottom: 10px; padding: 8px 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; font-size: 0.8rem; color: #b91c1c;">{{ $message }}</p>
        @enderror
    @endforeach

    <ol style="list-style: none; margin: 0; padding: 0;">
        @foreach(\App\Models\AdmissionApplication::stepLabels() as $step => $label)
        @php $__row = $__standing[$step]; @endphp
        <li style="display: flex; flex-wrap: wrap; align-items: flex-start; gap: 12px; padding: 10px 0; border-top: 1px solid #f1f5f9;" data-step="{{ $step }}">
            <span style="flex: none; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700;
                         {{ $__row ? 'background: #dcfce7; color: #166534;' : ($step === $__next ? 'background: #dbeafe; color: #1d4ed8;' : 'background: #f1f5f9; color: #94a3b8;') }}">
                {{ $__row ? '✓' : $loop->iteration }}
            </span>
            <div style="flex: 1; min-width: 200px;">
                <div style="font-size: 0.85rem; font-weight: 600; color: #0f172a;">{{ $label }}</div>
                @if($__row)
                <div style="font-size: 0.75rem; color: #64748b;">
                    {{ $__row->outcomeLabel() }} · {{ $__row->decidedBy?->full_name ?? '—' }} · {{ $__row->decided_at->format('d/m/Y H:i') }}
                    @if($__row->score !== null) · {{ __('Score') }}: <strong>{{ rtrim(rtrim(number_format((float) $__row->score, 2, '.', ''), '0'), '.') }}</strong>@endif
                    @if($__row->note)<br><em>{{ $__row->note }}</em>@endif
                </div>
                @endif
            </div>

            @if($__canAct && $step === $__next)
            <form method="POST" action="{{ route('admin.admissions.applications.step', $application) }}" style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                @csrf
                <input type="hidden" name="step" value="{{ $step }}">
                @if($step === 'assessment')
                <input type="number" name="score" min="0" max="100" step="0.25" placeholder="{{ __('Score') }}" aria-label="{{ __('Score') }}" style="width: 80px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.8rem;">
                @endif
                <input type="text" name="note" maxlength="1000" placeholder="{{ __('Note (needed to mark not required)') }}" aria-label="{{ __('Note') }}" style="width: 240px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.8rem;">
                <button type="submit" name="outcome" value="done" style="padding: 6px 12px; background: #16a34a; color: #fff; border: none; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer;">{{ __('Done') }}</button>
                <button type="submit" name="outcome" value="waived" style="padding: 6px 12px; background: #fff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.78rem; font-weight: 600; cursor: pointer;">{{ __('Not required') }}</button>
            </form>
            @elseif($__canAct && $__row)
            <form method="POST" action="{{ route('admin.admissions.applications.step', $application) }}" style="display: flex; gap: 6px; align-items: center;">
                @csrf
                <input type="hidden" name="step" value="{{ $step }}">
                <input type="hidden" name="outcome" value="returned">
                <input type="text" name="note" required maxlength="1000" placeholder="{{ __('Why send it back?') }}" aria-label="{{ __('Why send it back?') }}" style="width: 200px; padding: 6px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.78rem;">
                <button type="submit" style="padding: 6px 10px; background: #fff; color: #b45309; border: 1px solid #fcd34d; border-radius: 6px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">{{ __('Send back') }}</button>
            </form>
            @endif
        </li>
        @endforeach
    </ol>

    @if($application->admissionSteps->isNotEmpty())
    <details style="margin-top: 10px;">
        <summary style="font-size: 0.75rem; color: #64748b; cursor: pointer;">{{ __('History') }} ({{ $application->admissionSteps->count() }})</summary>
        <ul style="margin: 8px 0 0; padding-left: 16px; font-size: 0.75rem; color: #475569;">
            @foreach($application->admissionSteps->reverse() as $row)
            <li>{{ $row->decided_at->format('d/m/Y H:i') }} · {{ \App\Models\AdmissionApplication::stepLabels()[$row->step] ?? $row->step }}: <strong>{{ $row->outcomeLabel() }}</strong> · {{ $row->decidedBy?->full_name ?? '—' }}@if($row->note) — {{ $row->note }}@endif</li>
            @endforeach
        </ul>
    </details>
    @endif
</div>
