{{-- The register line on a certificate or record: serial, check code and QR (IssuedDocument). --}}
<table style="width:100%; margin-top:14px; border-top:1px solid #d1d5db; padding-top:6px;" data-issued="{{ $issued->serial }}">
    <tr>
        <td style="width:62px; vertical-align:middle;">
            <img src="{{ \App\Support\QrCode::pngDataUri($issued->verifyUrl(), 3) }}" alt="" style="width:58px; height:58px;">
        </td>
        <td style="vertical-align:middle; font-size:9px; color:#374151; line-height:1.5;">
            <strong>{{ __('Serial No.') }} {{ $issued->serial }}</strong> &middot; {{ __('Issued :date', ['date' => $issued->issued_at->format('d/m/Y')]) }}<br>
            {{ __('Scan the code, or open :url and type', ['url' => route('document.verify')]) }}
            <strong style="font-family: DejaVu Sans Mono, monospace; letter-spacing:1px;">{{ $issued->verifyCodeForPrint() }}</strong>
            {{ __('to check this document with the school.') }}
        </td>
    </tr>
</table>
