{{--
    The family's agreement that money beyond what the pupil owes is kept as
    credit (PaymentRecorder::assertCreditAccepted). Shown up front when the
    amount is known to be too much (:amount), or after the server refused
    the payment for it (the accept_credit error).
--}}
@props(['amount' => null])
@php($__creditError = $errors->first('accept_credit'))
@if($__creditError || ($amount !== null && $amount >= 0.01))
<div {{ $attributes->merge(['class' => 'mb-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900']) }} role="alert">
    <p>{{ $__creditError ?: __(':amount FCFA of this is more than the pupil owes, and would be kept as the pupil\'s credit for future fees.', ['amount' => number_format((float) $amount, 0, ',', ' ')]) }}</p>
    <label class="mt-2 flex items-start gap-2 font-medium">
        <input type="checkbox" name="accept_credit" value="1" required class="mt-0.5 h-4 w-4 rounded border-amber-400" @checked(old('accept_credit'))>
        <span>{{ __('Keep the extra as credit: the family agrees') }}</span>
    </label>
</div>
@endif
