<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Staff Card Verification') }}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 16px; }
        .card { max-width: 420px; margin: 40px auto; background: white; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); overflow: hidden; }
        .head { background: #134e4a; color: white; padding: 16px; text-align: center; }
        .head h1 { font-size: 15px; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .body { padding: 20px; text-align: center; }
        .photo { width: 96px; height: 116px; border-radius: 8px; object-fit: cover; border: 2px solid #e2e8f0; }
        .name { font-size: 18px; font-weight: 800; margin: 12px 0 4px; }
        .meta { font-size: 13px; color: #64748b; }
        .status { display: inline-block; margin-top: 14px; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 700; }
        .ok { background: #dcfce7; color: #166534; }
        .bad { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
<div class="card">
    <div class="head"><h1>{{ $settings->school_name ?? config('app.name') }}</h1></div>
    <div class="body">
        @if($member)
            @if($member->profile_photo)
            <img src="{{ asset('storage/' . $member->profile_photo) }}" alt="" class="photo">
            @endif
            <div class="name">{{ $member->first_name }} {{ $member->last_name }}</div>
            <div class="meta">{{ $member->staff_id }} · {{ $member->designation?->title ?? __('Staff') }}</div>
            <div class="status {{ $valid ? 'ok' : 'bad' }}">
                {{ $valid ? __('This is a current member of staff.') : __('This card is no longer valid.') }}
            </div>
        @else
            <div class="status bad">{{ __('This card could not be verified.') }}</div>
        @endif
    </div>
</div>
</body>
</html>
