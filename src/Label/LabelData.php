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
    ) {}
}
