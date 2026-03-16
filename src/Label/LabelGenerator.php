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
            );
        }

        return $labels;
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
            '{{carrierName}}' => htmlspecialchars($label->carrierName),
            '{{date}}' => htmlspecialchars($label->date),
            '{{senderName}}' => htmlspecialchars($label->senderName),
            '{{receiverName}}' => htmlspecialchars($label->receiverName),
            '{{receiverPhone}}' => htmlspecialchars($label->receiverPhone),
            '{{receiverAddress}}' => htmlspecialchars($label->receiverAddress),
            '{{paymentTypeText}}' => htmlspecialchars($label->paymentTypeText),
            '{{codLine}}' => $codLine,
            '{{codDisplay}}' => $label->isCod ? 'table-row' : 'none',
            '{{paymentDisplay}}' => !$label->isCod ? 'table-row' : 'none',
            '{{integrationCode}}' => htmlspecialchars($label->integrationCode),
            '{{barcodeNumber}}' => htmlspecialchars($label->barcodeNumber),
            '{{pieceNumber}}' => (string) $label->pieceNumber,
            '{{totalPieces}}' => (string) $label->totalPieces,
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
                @media print {
                    .label { page-break-after: always; }
                    .label:last-child { page-break-after: avoid; }
                    body { margin: 0; }
                }
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
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

    private function getDefaultTemplate(): string
    {
        return <<<'HTML'
        <div class="label" style="width: 100mm; border: 1px solid #000; font-family: Arial, sans-serif; font-size: 12px; margin: 10px auto; padding: 0;">
            <!-- Header -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding: 8px; font-weight: bold; font-size: 13px;">Kargo Firması : {{carrierName}}</td>
                    <td style="padding: 8px; text-align: right; font-weight: bold; font-size: 13px;">{{date}}</td>
                </tr>
            </table>

            <!-- Sender -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding: 6px 8px;">
                        <div style="font-weight: bold; margin-bottom: 4px;">Gönderici Bilgileri</div>
                        <div><strong>İsim</strong> : {{senderName}}</div>
                    </td>
                </tr>
            </table>

            <!-- Receiver -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding: 6px 8px;">
                        <div style="font-weight: bold; margin-bottom: 4px;">Alıcı Bilgileri</div>
                        <div><strong>İsim</strong> : {{receiverName}}</div>
                        <div><strong>Telefon</strong> : {{receiverPhone}}</div>
                        <div><strong>Adres</strong> : {{receiverAddress}}</div>
                    </td>
                </tr>
            </table>

            <!-- Payment Type (non-COD) -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #000; display: {{paymentDisplay}};">
                    <td style="padding: 6px 8px; font-weight: bold;">Kargo Ödeme Tipi : {{paymentTypeText}}</td>
                </tr>
            </table>

            <!-- COD Info -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #000; display: {{codDisplay}};">
                    <td style="padding: 6px 8px; font-weight: bold;">{{codLine}}</td>
                </tr>
            </table>

            <!-- Integration Code -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #000;">
                    <td style="padding: 8px; text-align: center;">
                        <div style="font-weight: bold; margin-bottom: 2px;">Entegrasyon No : {{integrationCode}}</div>
                        <div class="barcode">{{integrationCode}}</div>
                    </td>
                </tr>
            </table>

            <!-- Piece Barcode -->
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px; text-align: center;">
                        <div style="font-weight: bold; margin-bottom: 2px;">Paket Barkod No : {{barcodeNumber}}</div>
                        <div class="barcode">{{barcodeNumber}}</div>
                    </td>
                    <td style="padding: 8px; text-align: right; vertical-align: bottom; font-weight: bold;">
                        Paket : {{pieceNumber}} / {{totalPieces}}
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
