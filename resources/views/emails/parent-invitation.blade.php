@component('mail::message')
# {{ __('Hello :name,', ['name' => $name]) }}

{{ __(':school has set up a parent portal account for you. From it you can follow your children\'s fees, attendance, timetable and report cards.', ['school' => $schoolName]) }}

@component('mail::button', ['url' => $link])
{{ __('Set up my account') }}
@endcomponent

@if($expiresAt)
{{ __('This link expires on :date.', ['date' => $expiresAt->format('d/m/Y \a\t H:i')]) }}
@endif

{{ __('If you did not expect this email, you can safely ignore it.') }}

{{ __('Thanks,') }}<br>
{{ $schoolName }}

@component('mail::subcopy')
{{ __('If the button above does not work, copy and paste this address into your browser:') }}
<span class="break-all">{{ $link }}</span>
@endcomponent
@endcomponent
