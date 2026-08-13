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
     * Inline SVG sized by CSS rather than by intrinsic pixels.
     *
     * width/height are dropped and preserveAspectRatio disabled so the
     * template can pin the barcode to an exact millimetre width — the only
     * thing that actually determines whether a scanner can read it. Bars keep
     * their relative widths, so stretching stays valid Code 128.
     *
     * @return string Inline <svg>, or an empty string when $value cannot be encoded.
     */
    public static function svg(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $svg = (new BarcodeGeneratorSVG())->getBarcode($value, BarcodeGenerator::TYPE_CODE_128, 2, 60);
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
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . self::viewBoxOf($svg) . '" preserveAspectRatio="none" width="100%" height="100%">',
            $svg,
            1,
        );
    }

    /**
     * Recover the intrinsic dimensions so the replacement tag keeps a viewBox
     * to scale against.
     */
    private static function viewBoxOf(string $svg): string
    {
        if (preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $svg, $m) === 1) {
            return $m[1] . ' ' . $m[2];
        }

        preg_match('/\bwidth="([\d.]+)"/', $svg, $w);
        preg_match('/\bheight="([\d.]+)"/', $svg, $h);

        return ($w[1] ?? '100') . ' ' . ($h[1] ?? '60');
    }
}
