{{-- Shared letterhead for every printed document, so they read as one school. --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:6px;">
    <tr>
        <td style="width:70px; vertical-align:middle;">
            @if($school?->logo && file_exists(public_path('storage/'.$school->logo)))
                <img src="{{ public_path('storage/'.$school->logo) }}" style="width:62px; height:62px; object-fit:contain;">
            @endif
        </td>
        <td style="text-align:center; vertical-align:middle;">
            <div style="font-size:16px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                {{ $school?->school_name ?? config('app.name') }}
            </div>
            @if($school?->motto)
                <div style="font-size:9px; font-style:italic; color:#555; margin-top:1px;">{{ $school->motto }}</div>
            @endif
            <div style="font-size:9px; color:#444; margin-top:3px;">
                {{ collect([$school?->address, $school?->city, $school?->region])->filter()->join(', ') }}
                @if($school?->po_box) — {{ __('P.O. Box') }} {{ $school->po_box }} @endif
            </div>
            <div style="font-size:9px; color:#444;">
                {{ collect([$school?->phone, $school?->email])->filter()->join(' · ') }}
            </div>
        </td>
        <td style="width:70px;"></td>
    </tr>
</table>

<div style="border-bottom:2px solid #1f2937; margin-bottom:14px;"></div>
