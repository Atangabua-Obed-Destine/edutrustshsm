@component('mail::message')
# {{ __('Hello :name,', ['name' => $name]) }}

{{ __('This is a reminder that the following school fees are outstanding at :school.', ['school' => $schoolName]) }}

@component('mail::table')
| {{ __('Student') }} | {{ __('Fee') }} | {{ __('Due') }} | {{ __('Balance') }} |
|:--------------------|:----------------|:----------------|--------------------:|
@foreach($lines as $line)
| {{ $line->student }} | {{ $line->category }} | {{ $line->due_date ?? '—' }} | {{ number_format($line->balance, 0, '.', ' ') }} |
@endforeach
| | | **{{ __('Total') }}** | **{{ number_format($total, 0, '.', ' ') }} {{ $currency }}** |
@endcomponent

@component('mail::button', ['url' => route('parent.login')])
{{ __('Open the parent portal') }}
@endcomponent

{{ __('If you have already paid, please ignore this message — payments can take a day to appear.') }}

{{ __('Thanks,') }}<br>
{{ $schoolName }}
@endcomponent
