<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $house->name }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 11px; margin: 12px 0 3px; color: #1f3a5f; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        thead th { background: #f1f5f9; font-size: 8px; text-transform: uppercase; color: #475569; }
        .tick { width: 22px; border: 1px solid #94a3b8; }
        .room { page-break-inside: avoid; }
        .sign { margin-top: 28px; width: 100%; }
        .sign td { width: 50%; text-align: center; font-size: 9px; color: #64748b; padding-top: 22px; border: 0; border-top: 1px solid #cbd5e1; }
        .foot { margin-top: 14px; font-size: 7px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
@include('admin.documents._letterhead')

<h1>{{ $house->name }}</h1>
<p class="muted">
    {{ ['boys' => __('Boys'), 'girls' => __('Girls'), 'mixed' => __('Mixed')][$house->gender] ?? '' }}
    · {{ __(':used of :beds beds taken', ['used' => $house->rooms->sum(fn ($r) => $r->occupants->count()), 'beds' => $house->rooms->where('is_active', true)->sum('beds')]) }}
    @if($house->houseMaster) · {{ __('House master: :name', ['name' => $house->houseMaster->first_name.' '.$house->houseMaster->last_name]) }} @endif
    · {{ __('Printed :when', ['when' => now()->format('d/m/Y')]) }}
</p>

@foreach($house->rooms->where('is_active', true) as $room)
<div class="room">
    <h2>{{ __('Room :room', ['room' => $room->name]) }} <span class="muted">· {{ __(':used of :beds beds', ['used' => $room->occupants->count(), 'beds' => $room->beds]) }}</span></h2>
    <table>
        <thead><tr><th style="width: 40px;">{{ __('Bed') }}</th><th>{{ __('Name') }}</th><th>{{ __('Matricule') }}</th><th>{{ __('Class') }}</th><th class="tick">{{ __('Present') }}</th></tr></thead>
        <tbody>
            @forelse($room->occupants->sortBy('bed', SORT_NATURAL) as $a)
            <tr><td>{{ $a->bed ?? '—' }}</td><td>{{ $a->student?->full_name }}</td><td>{{ $a->student?->student_id }}</td><td>{{ $boarders[$a->student_id]->class ?? '' }}</td><td class="tick"></td></tr>
            @empty
            <tr><td colspan="5" class="muted">{{ __('Empty.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endforeach

<table class="sign"><tr><td>{{ __('House master or mistress') }}</td><td>{{ __('Principal') }}</td></tr></table>
<p class="foot">{{ $school?->school_name }} · EduTrust, {{ __('powered by') }} I-NNOVA</p>
</body>
</html>
