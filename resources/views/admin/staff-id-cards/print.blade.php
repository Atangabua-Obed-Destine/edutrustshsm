<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Staff ID Cards') }}</title>
    <style>
        @page { size: A4; margin: 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; font-size: 11px; color: #1a1a1a; background: #e5e7eb; }
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .cards-grid { padding: 0; gap: 6mm; }
            .id-card { break-inside: avoid; box-shadow: none; }
        }
        .toolbar { position: sticky; top: 0; z-index: 50; background: #1e293b; color: white; padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; }
        .toolbar h2 { font-size: 16px; font-weight: 600; }
        .toolbar button { padding: 8px 20px; background: #2563eb; color: white; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; max-width: 800px; margin: 20px auto; padding: 20px; }
        .id-card { width: 100%; aspect-ratio: 8.56 / 10.8; border-radius: 10px; overflow: hidden; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.15); display: flex; flex-direction: column; page-break-inside: avoid; }
        .card-header { background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); color: white; padding: 8px 12px 6px; text-align: center; }
        .school-logo { width: 36px; height: 36px; border-radius: 50%; object-fit: contain; background: white; padding: 2px; }
        .school-name { font-size: 11px; font-weight: 800; letter-spacing: 0.5px; text-transform: uppercase; margin-top: 2px; line-height: 1.2; }
        .school-location { font-size: 7px; color: #ccfbf1; margin-top: 1px; }
        .card-label { display: inline-block; margin-top: 4px; background: #fbbf24; color: #134e4a; font-size: 7px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; padding: 2px 10px; border-radius: 3px; }
        .card-body { flex: 1; padding: 10px 12px 8px; display: flex; gap: 10px; }
        .card-photo { width: 72px; height: 88px; flex-shrink: 0; border-radius: 6px; border: 2px solid #e2e8f0; overflow: hidden; background: #f1f5f9; }
        .card-photo img { width: 100%; height: 100%; object-fit: cover; }
        .no-photo { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 800; color: #94a3b8; }
        .card-details { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .staff-name { font-size: 12px; font-weight: 800; color: #1e293b; text-transform: uppercase; line-height: 1.2; margin-bottom: 6px; }
        .detail-row { display: flex; font-size: 8px; margin-bottom: 2px; }
        .detail-label { width: 62px; flex-shrink: 0; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .detail-value { font-weight: 600; color: #1e293b; }
        .card-id-bar { background: #134e4a; color: white; text-align: center; padding: 4px 12px; font-size: 12px; font-weight: 800; letter-spacing: 2px; }
        .card-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 4px 12px; font-size: 6.5px; color: #64748b; text-align: center; word-break: break-all; }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <h2>{{ __('Staff ID Cards') }} &mdash; {{ $staff->count() }}</h2>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.history.back()" style="background: #475569;">{{ __('Back') }}</button>
            <button onclick="window.print()">{{ __('Print') }}</button>
        </div>
    </div>

    <div class="cards-grid">
        @foreach($staff as $member)
        <div class="id-card">
            <div class="card-header">
                @if($settings?->logo)
                    <img src="{{ asset('storage/' . $settings->logo) }}" alt="" class="school-logo">
                @endif
                <div class="school-name">{{ $settings->school_name ?? config('app.name') }}</div>
                <div class="school-location">{{ implode(' | ', array_filter([$settings?->po_box, $settings?->city, $settings?->region])) }}</div>
                <div class="card-label">{{ __('Staff Identity Card') }}</div>
            </div>

            <div class="card-body">
                <div class="card-photo">
                    @if($member->profile_photo)
                        <img src="{{ asset('storage/' . $member->profile_photo) }}" alt="">
                    @else
                        <div class="no-photo">{{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}</div>
                    @endif
                </div>
                <div class="card-details">
                    <div class="staff-name">{{ $member->last_name }} {{ $member->first_name }}</div>
                    <div class="detail-row"><span class="detail-label">{{ __('Designation') }}:</span><span class="detail-value">{{ $member->designation?->title ?? '—' }}</span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Department') }}:</span><span class="detail-value">{{ $member->department?->name ?? '—' }}</span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Joined') }}:</span><span class="detail-value">{{ $member->joining_date?->format('d/m/Y') ?? '—' }}</span></div>
                    <div class="detail-row"><span class="detail-label">{{ __('Valid until') }}:</span><span class="detail-value">{{ $member->id_card_validity ?: '—' }}</span></div>
                </div>
            </div>

            <div class="card-id-bar">{{ $member->staff_id }}</div>
            <div class="card-footer">{{ __('Verify this card') }}: {{ route('staff-card.verify', $member->id_card_token) }}</div>
        </div>
        @endforeach
    </div>
</body>
</html>
