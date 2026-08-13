<?php

declare(strict_types=1);

namespace Omniship\Aras\Label;

readonly class LabelData
{
    public function __construct(
        public string $carrierName = 'Aras Kargo',
        public string $date = '',
        public string $senderName = '',
        public string $receiverName = '',
        public string $receiverPhone = '',
        public string $receiverAddress = '',
        public string $paymentTypeText = '',
        public bool $isCod = false,
        public float $codAmount = 0.0,
        public string $codCurrency = 'TL',
        public string $integrationCode = '',
        public string $barcodeNumber = '',
        public int $pieceNumber = 1,
        public int $totalPieces = 1,
        /**
         * Totals for the whole shipment, matching the Weight and
         * VolumetricWeight declared to Aras — not a per-piece split, which the
         * package data does not describe.
         */
        public float $weight = 0.0,
        public float $desi = 0.0,
        /** What is in the parcel, taken from the package description. */
        public string $productName = '',
        /** The shop's account number with the carrier, printed on the waybill. */
        public string $customerNo = '',
        public string $orderNumber = '',
    ) {}
}
