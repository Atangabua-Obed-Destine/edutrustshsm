<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Encoder;
use chillerlan\QRCode\QROptions;

/**
 * A QR code drawn as inline SVG, for printed papers that link back to the
 * school (a receipt's check page). Inline, so it prints with the page and
 * needs no request of its own; vector, so it stays sharp on any printer.
 */
class QrCode
{
    public static function svg(string $text): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            // Medium correction survives a crease or a smudge on paper.
            'eccLevel' => EccLevel::M,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
            'connectPaths' => true,
            'quietzoneSize' => 1,
        ]);

        return (new Encoder($options))->render($text);
    }

    /**
     * The same code as a PNG data URI, for PDFs: dompdf draws images, not
     * inline SVG.
     */
    public static function pngDataUri(string $text, int $scale = 4): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'outputBase64' => true,
            'scale' => $scale,
            'quietzoneSize' => 1,
        ]);

        return (new Encoder($options))->render($text);
    }
}
