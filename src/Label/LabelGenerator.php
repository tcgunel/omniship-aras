<?php

declare(strict_types=1);

namespace Omniship\Aras\Label;

use Omniship\Common\Address;
use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Package;

class LabelGenerator
{
    private ?string $customTemplate = null;

    public function setTemplate(string $html): static
    {
        $this->customTemplate = $html;

        return $this;
    }

    /**
     * Generate HTML labels for all pieces.
     *
     * @param LabelData[] $labels
     * @return string Complete HTML document with all labels
     */
    public function generate(array $labels): string
    {
        $template = $this->customTemplate ?? $this->getDefaultTemplate();
        $rendered = [];

        foreach ($labels as $label) {
            $rendered[] = $this->renderLabel($template, $label);
        }

        return $this->wrapInDocument($rendered);
    }

    /**
     * Build LabelData array from shipment parameters.
     *
     * @param array<string, mixed> $params Shipment parameters
     * @return LabelData[]
     */
    public static function fromShipmentData(array $params): array
    {
        $shipFrom = $params['shipFrom'] ?? null;
        $shipTo = $params['shipTo'] ?? null;
        $packages = $params['packages'] ?? [];
        $integrationCode = $params['integrationCode'] ?? '';
        $barcodes = $params['barcodes'] ?? [];
        $paymentType = $params['paymentType'] ?? null;
        $isCod = (bool) ($params['cashOnDelivery'] ?? false);
        $codAmount = (float) ($params['codAmount'] ?? 0.0);
        $codCurrency = $params['codCurrency'] ?? 'TL';
        $date = $params['date'] ?? date('d.m.Y');
        // Printed on the waybill but never part of the shipment request, so it
        // is passed in rather than derived.
        $customerNo = (string) ($params['customerNo'] ?? '');
        $orderNumber = (string) ($params['invoiceNumber'] ?? '');

        $senderName = '';
        if ($shipFrom instanceof Address) {
            $senderName = $shipFrom->company ?? $shipFrom->name ?? '';
        }

        $receiverName = '';
        $receiverPhone = '';
        $receiverAddress = '';
        if ($shipTo instanceof Address) {
            $receiverName = $shipTo->name ?? '';
            $receiverPhone = $shipTo->phone ?? '';
            $receiverAddress = self::buildAddressLine($shipTo);
        }

        $paymentTypeText = self::resolvePaymentTypeText($paymentType);

        // Calculate total pieces (use barcode count if it exceeds package quantity)
        $totalPieces = 0;
        if ($packages === []) {
            $totalPieces = 1;
        } else {
            /** @var Package $package */
            foreach ($packages as $package) {
                $totalPieces += $package->quantity;
            }
        }

        if (count($barcodes) > $totalPieces) {
            $totalPieces = count($barcodes);
        }

        // Same arithmetic CreateShipmentRequest uses for the Weight and
        // VolumetricWeight it declares, so the label cannot disagree with the
        // waybill. getDesi() falls back to the L×W×H derivation when the
        // caller supplied no explicit desi.
        $totalWeight = 0.0;
        $totalDesi = 0.0;
        $productName = '';

        // Expanded to one entry per physical parcel, because a label describes
        // the box it is stuck to: "Paket Kg." is that parcel's weight, not the
        // shipment's. A caller shipping three 10 kg sacks sends three packages
        // and each label reads 10 Kg., while the waybill totals 30.
        $pieceFigures = [];

        /** @var Package $package */
        foreach ($packages as $package) {
            $totalWeight += $package->weight * $package->quantity;
            $totalDesi += ($package->getDesi() ?? 0.0) * $package->quantity;

            if ($productName === '' && ($package->description ?? '') !== '') {
                $productName = (string) $package->description;
            }

            for ($copy = 0; $copy < $package->quantity; $copy++) {
                $pieceFigures[] = [
                    'weight' => $package->weight,
                    'desi' => $package->getDesi() ?? 0.0,
                    'description' => (string) ($package->description ?? ''),
                ];
            }
        }

        $labels = [];

        for ($pieceIndex = 0; $pieceIndex < $totalPieces; $pieceIndex++) {
            $barcode = $barcodes[$pieceIndex] ?? self::generateBarcode($integrationCode, $pieceIndex + 1);
            $labels[] = new LabelData(
                date: $date,
                senderName: $senderName,
                receiverName: $receiverName,
                receiverPhone: $receiverPhone,
                receiverAddress: $receiverAddress,
                paymentTypeText: $paymentTypeText,
                isCod: $isCod,
                codAmount: $codAmount,
                codCurrency: $codCurrency,
                integrationCode: $integrationCode,
                barcodeNumber: $barcode,
                pieceNumber: $pieceIndex + 1,
                totalPieces: $totalPieces,
                // Falls back to the shipment totals when the caller described
                // fewer packages than there are pieces — barcodes can raise the
                // piece count beyond the parcel list.
                weight: $pieceFigures[$pieceIndex]['weight'] ?? $totalWeight,
                desi: $pieceFigures[$pieceIndex]['desi'] ?? $totalDesi,
                totalWeight: $totalWeight,
                totalDesi: $totalDesi,
                productName: ($pieceFigures[$pieceIndex]['description'] ?? '') ?: $productName,
                customerNo: $customerNo,
                orderNumber: $orderNumber,
            );
        }

        return $labels;
    }

    /**
     * Keep enough of the number to identify the recipient, hide the rest.
     *
     * Six leading characters is what Aras' own waybill shows (051022*****).
     * Numbers too short to mask are left alone rather than blanked, since a
     * label with no phone at all is worse than one with a short one.
     */
    private static function maskPhone(string $phone): string
    {
        $visible = 6;

        if (mb_strlen($phone) <= $visible) {
            return $phone;
        }

        return mb_substr($phone, 0, $visible) . str_repeat('*', mb_strlen($phone) - $visible);
    }

    /**
     * Turkish decimal notation, trailing zeros trimmed: a 3 kg parcel should
     * print "3 Kg." on the label, not "3,00 Kg.".
     */
    private static function formatNumber(float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');

        return str_contains($formatted, ',')
            ? rtrim(rtrim($formatted, '0'), ',')
            : $formatted;
    }

    private function renderLabel(string $template, LabelData $label): string
    {
        $codLine = '';
        if ($label->isCod) {
            $codLine = 'Tahsilatlı Kargo : Evet&nbsp;&nbsp;&nbsp;&nbsp;Tahsilat Tutarı : '
                . number_format($label->codAmount, 2, ',', '.')
                . ' ' . htmlspecialchars($label->codCurrency);
        }

        $replacements = [
            // MUST precede {{integrationCode}} and {{barcodeNumber}}:
            // str_replace() walks the array in order, and {{integrationCode}}
            // is a prefix of {{integrationCodeSvg}} — substituting it first
            // would rewrite the middle of the longer placeholder and leave
            // "Svg}}" behind as literal text on the label.
            '{{integrationCodeSvg}}' => Barcode::svg($label->integrationCode),
            '{{barcodeSvg}}' => Barcode::svg($label->barcodeNumber),

            '{{carrierName}}' => htmlspecialchars($label->carrierName),
            '{{date}}' => htmlspecialchars($label->date),
            '{{senderName}}' => htmlspecialchars($label->senderName),
            '{{receiverName}}' => htmlspecialchars($label->receiverName),
            // Masked by default. The label travels through many hands, while
            // the courier already has the full number: it is sent to Aras as
            // ReceiverPhone1 and never comes from here. Templates that genuinely
            // need the whole number can use {{receiverPhoneFull}}.
            '{{receiverPhoneFull}}' => htmlspecialchars($label->receiverPhone),
            '{{receiverPhone}}' => htmlspecialchars(self::maskPhone($label->receiverPhone)),
            '{{receiverAddress}}' => htmlspecialchars($label->receiverAddress),
            '{{paymentTypeText}}' => htmlspecialchars($label->paymentTypeText),
            '{{codLine}}' => $codLine,
            '{{codDisplay}}' => $label->isCod ? 'table-row' : 'none',
            '{{paymentDisplay}}' => !$label->isCod ? 'table-row' : 'none',
            '{{integrationCode}}' => htmlspecialchars($label->integrationCode),
            '{{barcodeNumber}}' => htmlspecialchars($label->barcodeNumber),
            '{{pieceNumber}}' => (string) $label->pieceNumber,
            '{{totalPieces}}' => (string) $label->totalPieces,
            '{{weight}}' => self::formatNumber($label->weight),
            '{{desi}}' => self::formatNumber($label->desi),
            // A shipment declared purely by weight has no desi and vice versa.
            // Printing the missing one as "0" is worse than saying nothing: it
            // reads as a measured zero rather than as "not applicable".
            '{{totalWeight}}' => self::formatNumber($label->totalWeight),
            '{{totalDesi}}' => self::formatNumber($label->totalDesi),
            '{{weightDisplay}}' => $label->weight > 0 ? 'inline' : 'none',
            '{{desiDisplay}}' => $label->desi > 0 ? 'inline' : 'none',
            '{{productName}}' => htmlspecialchars($label->productName),
            '{{customerNo}}' => htmlspecialchars($label->customerNo),
            '{{customerNoDisplay}}' => $label->customerNo !== '' ? 'block' : 'none',
            '{{orderNumber}}' => htmlspecialchars($label->orderNumber),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * @param string[] $renderedLabels
     */
    private function wrapInDocument(array $renderedLabels): string
    {
        $labelsHtml = implode("\n", $renderedLabels);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Kargo Etiketi</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128+Text&display=swap" rel="stylesheet">
            <style>
                /* Pin the sheet to the label stock. Without this the printer
                   uses its default paper and the design is scaled to whatever
                   fits — which is how barcodes end up too small to scan. */
                @page { size: 130mm 100mm; margin: 0; }

                @media print {
                    .label { page-break-after: always; }
                    .label:last-child { page-break-after: avoid; }
                    body { margin: 0; }
                }
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; }

                /* Vector barcode. The container fixes the physical width; the
                   SVG stretches to fill it, so bar widths are known in mm
                   instead of depending on a font rendering at some point size. */
                .bc { display: block; height: 22mm; }
                .bc svg { display: block; width: 100%; height: 100%; }

                /* Retained for templates written against the old font-based
                   barcode. New templates should use the Svg placeholders
                   instead: this renders as plain digits, and therefore does
                   not scan, whenever the remote webfont fails to load.
                   NB: this block is emitted after placeholder substitution,
                   so it must never contain placeholder syntax itself. */
                .barcode {
                    font-family: 'Libre Barcode 128 Text', cursive;
                    font-size: 48px;
                    line-height: 1;
                }
            </style>
        </head>
        <body>
        {$labelsHtml}
        </body>
        </html>
        HTML;
    }

    /**
     * The stock Aras label: 130x100mm landscape, laid out like the carrier's
     * own waybill.
     *
     * Barcodes are the {{...Svg}} placeholders rather than the old font-based
     * .barcode markup, which shops reported as unscannable. The phone is the
     * masked {{receiverPhone}}; weight and desi hide themselves when the
     * shipment was declared by only one of the two.
     */
    private function getDefaultTemplate(): string
    {
        return <<<'HTML'
        <div class="label" style="width:130mm;height:100mm;box-sizing:border-box;padding:2mm;font-family:Arial,Helvetica,sans-serif;color:#000;">
            <table style="width:100%;height:100%;border:2px solid #000;border-collapse:collapse;">
                <tr>
                    <td style="padding:1.5mm 2mm;font-size:18pt;font-weight:bold;width:26%;">{{senderName}}</td>
                    <td style="padding:1.5mm 2mm;font-size:10pt;font-weight:bold;width:30%;">
                        <span style="display:{{customerNoDisplay}};">Müşteri No :<br>{{customerNo}}</span>
                    </td>
                    <td style="padding:1.5mm 2mm;font-size:10pt;font-weight:bold;width:30%;">Tarih : <span style="font-weight:normal;">{{date}}</span></td>
                    <td style="padding:1.5mm 2mm;text-align:center;width:14%;">
                        <span style="display:inline-block;border:1.5px solid #000;padding:0.5mm 2.5mm;font-size:16pt;font-weight:bold;">{{pieceNumber}}/{{totalPieces}}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="4" style="padding:1.5mm 2mm;border-top:2px solid #000;vertical-align:top;">
                        <div style="font-size:12pt;font-weight:bold;margin-bottom:1mm;">Alıcı Bilgileri</div>
                        <div style="font-size:10pt;margin-bottom:1mm;"><b>Ad / Unvan:</b> {{receiverName}}</div>
                        <div style="font-size:10pt;margin-bottom:1mm;"><b>Adres:</b> {{receiverAddress}}</div>
                        <table style="width:100%;border-collapse:collapse;">
                            <tr>
                                <td style="border:0;padding:0;font-size:10pt;"><b>Telefon No:</b> {{receiverPhone}}</td>
                                <td style="border:0;padding:0;text-align:right;font-size:8pt;"><b>Ürün Çeşidi:</b> {{productName}}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr style="display:{{paymentDisplay}};">
                    <td colspan="4" style="padding:1mm 2mm;border-top:2px solid #000;font-size:9pt;font-weight:bold;">Kargo Ödeme Tipi : {{paymentTypeText}}</td>
                </tr>
                <tr style="display:{{codDisplay}};">
                    <td colspan="4" style="padding:1mm 2mm;border-top:2px solid #000;font-size:9pt;font-weight:bold;">{{codLine}}</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding:2mm;border-top:2px solid #000;border-right:2px solid #000;text-align:center;vertical-align:top;">
                        <div style="font-size:12pt;font-weight:bold;">Entegrasyon No</div>
                        <div style="font-size:10pt;margin-bottom:1mm;">{{integrationCode}}</div>
                        <div class="bc">{{integrationCodeSvg}}</div>
                    </td>
                    <td colspan="2" style="padding:2mm;border-top:2px solid #000;text-align:center;vertical-align:top;">
                        <table style="width:100%;border-collapse:collapse;">
                            <tr>
                                <td style="border:0;padding:0;text-align:center;">
                                    <div style="font-size:8pt;font-weight:bold;">Paket Barkod No</div>
                                    <div style="font-size:8pt;">{{barcodeNumber}}</div>
                                </td>
                                <td style="border:0;padding:0;text-align:right;width:34%;vertical-align:top;">
                                    <div style="display:{{weightDisplay}};"><b style="font-size:8pt;">Paket Kg.</b><br><span style="font-size:8pt;">{{weight}} Kg.</span></div>
                                    <div style="display:{{desiDisplay}};"><b style="font-size:8pt;">Desi</b><br><span style="font-size:8pt;">{{desi}}</span></div>
                                </td>
                            </tr>
                        </table>
                        <div class="bc" style="margin-top:1mm;">{{barcodeSvg}}</div>
                    </td>
                </tr>
            </table>
        </div>
        HTML;
    }

    private static function buildAddressLine(Address $address): string
    {
        $parts = array_filter([
            $address->street1,
            $address->street2,
            $address->district,
            $address->city,
        ]);

        return implode(' ', $parts);
    }

    private static function generateBarcode(string $integrationCode, int $pieceNumber): string
    {
        if ($integrationCode === '') {
            return '';
        }

        return $integrationCode . str_pad((string) $pieceNumber, 2, '0', STR_PAD_LEFT);
    }

    private static function resolvePaymentTypeText(?PaymentType $paymentType): string
    {
        if ($paymentType === null) {
            return 'Ücreti Gönderici Öder';
        }

        return match ($paymentType) {
            PaymentType::SENDER => 'Ücreti Gönderici Öder',
            PaymentType::RECEIVER => 'Ücreti Alıcı Öder',
            PaymentType::THIRD_PARTY => 'Ücreti 3. Şahıs Öder',
        };
    }
}
