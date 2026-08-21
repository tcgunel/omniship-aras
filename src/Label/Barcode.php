<?php

declare(strict_types=1);

namespace Omniship\Aras\Label;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Throwable;

/**
 * Renders Code 128 barcodes as inline SVG for label templates.
 *
 * Labels used to draw their barcode as text in the remote "Libre Barcode 128
 * Text" webfont. That is not reliably scannable: the bar widths are whatever
 * the font renders at the chosen point size, the glyphs carry no quiet zone,
 * and if the font has not loaded the label prints as plain digits. Under
 * Chrome's --kiosk-printing there is no preview, so a shop only discovers the
 * problem when the courier cannot scan the parcel.
 *
 * SVG removes every one of those failure modes: the bars are geometry, they
 * scale losslessly to whatever physical width the template allots, and nothing
 * has to be fetched over the network to print.
 */
final class Barcode
{
    /**
     * Bar width multiplier handed to the generator. Every horizontal distance
     * below is expressed in these units, so the quiet zone stays correct if it
     * ever changes.
     */
    private const MODULE_WIDTH = 2;

    /**
     * Code 128 requires a clear margin of at least ten modules either side of
     * the symbol. Without it a scanner cannot find where the code begins.
     */
    private const QUIET_ZONE_MODULES = 10;

    /**
     * Inline SVG sized by CSS rather than by intrinsic pixels.
     *
     * width/height are dropped and preserveAspectRatio disabled so the
     * template can pin the barcode to an exact millimetre width. Bars keep
     * their relative widths, so stretching stays valid Code 128.
     *
     * The quiet zone is part of the graphic rather than the template's
     * padding. Aras' courier app could not read labels whose bars had only the
     * template's 2mm padding beside them — around a third of what the symbol's
     * own bar width demanded, with the label's black cell border immediately
     * beyond that. Camera scanners are far stricter about this than the
     * handheld guns, which is why the same label scanned at the depot and not
     * in the app. Carrying it in the viewBox means every template gets it,
     * including ones merchants have edited themselves, and it cannot drift out
     * of proportion: widening the label widens the bars and the margin
     * together.
     *
     * The trade is a slightly narrower bar for the same allotted width. That
     * is the right way round — the bars had width to spare and no margin.
     *
     * @return string Inline <svg>, or an empty string when $value cannot be encoded.
     */
    public static function svg(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $svg = (new BarcodeGeneratorSVG())->getBarcode($value, BarcodeGenerator::TYPE_CODE_128, self::MODULE_WIDTH, 60);
        } catch (Throwable) {
            return '';
        }

        // Strip the XML prolog/DOCTYPE: browsers render those as text when the
        // SVG is embedded in an HTML body.
        $start = strpos($svg, '<svg');

        if ($start === false) {
            return '';
        }

        $svg = substr($svg, $start);

        return (string) preg_replace(
            '/<svg\b[^>]*>/',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . self::viewBoxOf($svg) . '" preserveAspectRatio="none" width="100%" height="100%">',
            $svg,
            1,
        );
    }

    /**
     * The intrinsic dimensions, widened by a quiet zone on each side.
     *
     * The origin moves left rather than the bars moving right: the generator
     * has already placed them from x=0, and shifting the viewport is the one
     * change that needs no edit to the bars themselves.
     */
    private static function viewBoxOf(string $svg): string
    {
        [$width, $height] = self::intrinsicSizeOf($svg);

        $quietZone = self::QUIET_ZONE_MODULES * self::MODULE_WIDTH;

        return (-$quietZone) . ' 0 ' . ($width + (2 * $quietZone)) . ' ' . $height;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function intrinsicSizeOf(string $svg): array
    {
        if (preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $m) === 1) {
            return [(float) $m[1], (float) $m[2]];
        }

        preg_match('/\bwidth="([\d.]+)"/', $svg, $w);
        preg_match('/\bheight="([\d.]+)"/', $svg, $h);

        return [(float) ($w[1] ?? 100), (float) ($h[1] ?? 60)];
    }
}
