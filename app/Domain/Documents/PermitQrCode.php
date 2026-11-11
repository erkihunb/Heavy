<?php

namespace App\Domain\Documents;

use App\Domain\Permits\VerificationTokenService;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR code printed on a permit (spec §7): generated on the server as SVG,
 * never by a third-party service, with error correction level Q so a creased
 * or dirty paper copy still scans. It encodes only the verification address.
 */
final class PermitQrCode
{
    /**
     * Inline SVG markup (no XML declaration) for the token's verification address.
     */
    public static function svg(string $token, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        $svg = $writer->writeString(
            VerificationTokenService::url($token),
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            ErrorCorrectionLevel::Q(),
        );

        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
