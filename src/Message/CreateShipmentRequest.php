<?php

declare(strict_types=1);

namespace Omniship\Aras\Message;

use Omniship\Common\Address;
use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Message\ResponseInterface;
use Omniship\Common\Package;

class CreateShipmentRequest extends AbstractArasRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'integrationCode', 'shipTo');

        $shipTo = $this->getShipTo();
        assert($shipTo instanceof Address);

        $packages = $this->getPackages() ?? [];
        $pieceCount = $this->calculatePieceCount($packages);
        $totalWeight = $this->calculateTotalWeight($packages);
        $totalVolumetricWeight = $this->calculateTotalVolumetricWeight($packages);

        $paymentType = $this->getPaymentType();
        $payorTypeCode = ($paymentType === PaymentType::RECEIVER) ? '2' : '1';

        $data = [
            'Username' => $this->getUsername() ?? '',
            'Password' => $this->getPassword() ?? '',
            'TradingWaybillNumber' => $this->getTradingWaybillNumber() ?? '',
            'InvoiceNumber' => $this->getInvoiceNumber() ?? '',
            'ReceiverName' => $shipTo->name ?? '',
            'ReceiverAddress' => $this->buildReceiverAddress($shipTo),
            'ReceiverPhone1' => $shipTo->phone ?? '',
            'ReceiverCityName' => $shipTo->city ?? '',
            'ReceiverTownName' => $shipTo->district ?? '',
            'VolumetricWeight' => $this->formatNumber($totalVolumetricWeight),
            'Weight' => $this->formatNumber($totalWeight),
            'PieceCount' => $pieceCount,
            'IntegrationCode' => $this->getIntegrationCode() ?? '',
            'Description' => $this->getShipmentDescription(),
            'PayorTypeCode' => $payorTypeCode,
            'IsWorldWide' => '0',
            'IsCod' => $this->getCashOnDelivery() ? '1' : '0',
            'CodAmount' => $this->getCashOnDelivery() ? $this->formatNumber($this->getCodAmount() ?? 0.0) : '0',
            'CodCollectionType' => $this->getCashOnDelivery() ? '0' : '0',
            'CodBillingType' => '0',
            'PieceDetails' => $this->buildPieceDetails($packages),
            'SenderAccountAddressId' => $this->getSenderAccountAddressId() ?? '',
        ];

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $soapBody = $this->buildSetOrderXml($data);
        $body = $this->sendSoapRequest('SetOrder', $soapBody);

        $parsed = $this->parseSetOrderResponse($body);

        return $this->response = new CreateShipmentResponse($this, $parsed);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildSetOrderXml(array $data): string
    {
        $pieceDetailsXml = '';
        /** @var array<int, array<string, string>> $pieceDetails */
        $pieceDetails = $data['PieceDetails'];
        foreach ($pieceDetails as $piece) {
            $pieceDetailsXml .= '<PieceDetail>'
                . '<BarcodeNumber>' . $this->xmlEscape($piece['BarcodeNumber'] ?? '') . '</BarcodeNumber>'
                . '<VolumetricWeight>' . $this->xmlEscape($piece['VolumetricWeight']) . '</VolumetricWeight>'
                . '<Weight>' . $this->xmlEscape($piece['Weight']) . '</Weight>'
                . '<Description>' . $this->xmlEscape($piece['Description'] ?? '') . '</Description>'
                . '</PieceDetail>';
        }

        $username = $this->xmlEscape($data['Username']);
        $password = $this->xmlEscape($data['Password']);

        return '<SetOrder xmlns="http://tempuri.org/">'
            . '<orderInfo>'
            . '<Order>'
            . '<UserName>' . $username . '</UserName>'
            . '<Password>' . $password . '</Password>'
            . '<TradingWaybillNumber>' . $this->xmlEscape($data['TradingWaybillNumber']) . '</TradingWaybillNumber>'
            . '<InvoiceNumber>' . $this->xmlEscape($data['InvoiceNumber']) . '</InvoiceNumber>'
            . '<ReceiverName>' . $this->xmlEscape($data['ReceiverName']) . '</ReceiverName>'
            . '<ReceiverAddress>' . $this->xmlEscape($data['ReceiverAddress']) . '</ReceiverAddress>'
            . '<ReceiverPhone1>' . $this->xmlEscape($data['ReceiverPhone1']) . '</ReceiverPhone1>'
            . '<ReceiverCityName>' . $this->xmlEscape($data['ReceiverCityName']) . '</ReceiverCityName>'
            . '<ReceiverTownName>' . $this->xmlEscape($data['ReceiverTownName']) . '</ReceiverTownName>'
            . '<VolumetricWeight>' . $this->xmlEscape($data['VolumetricWeight']) . '</VolumetricWeight>'
            . '<Weight>' . $this->xmlEscape($data['Weight']) . '</Weight>'
            . '<PieceCount>' . $data['PieceCount'] . '</PieceCount>'
            . '<IntegrationCode>' . $this->xmlEscape($data['IntegrationCode']) . '</IntegrationCode>'
            . '<Description>' . $this->xmlEscape($data['Description']) . '</Description>'
            . '<PayorTypeCode>' . $this->xmlEscape($data['PayorTypeCode']) . '</PayorTypeCode>'
            . '<IsWorldWide>' . $this->xmlEscape($data['IsWorldWide']) . '</IsWorldWide>'
            . '<IsCod>' . $this->xmlEscape($data['IsCod']) . '</IsCod>'
            . '<CodAmount>' . $this->xmlEscape($data['CodAmount']) . '</CodAmount>'
            . '<CodCollectionType>' . $this->xmlEscape($data['CodCollectionType']) . '</CodCollectionType>'
            . '<CodBillingType>' . $this->xmlEscape($data['CodBillingType']) . '</CodBillingType>'
            . '<PieceDetails>' . $pieceDetailsXml . '</PieceDetails>'
            . '<SenderAccountAddressId>' . $this->xmlEscape($data['SenderAccountAddressId']) . '</SenderAccountAddressId>'
            . '</Order>'
            . '</orderInfo>'
            . '<userName>' . $username . '</userName>'
            . '<password>' . $password . '</password>'
            . '</SetOrder>';
    }

    /**
     * Parse the SetOrderResponse XML into an associative array.
     *
     * @return array<string, string|null>
     */
    private function parseSetOrderResponse(\SimpleXMLElement $body): array
    {
        $body->registerXPathNamespace('tns', 'http://tempuri.org/');

        $resultNodes = $body->xpath('.//tns:OrderResultInfo');

        if ($resultNodes === false || !isset($resultNodes[0])) {
            return [
                'ResultCode' => '-1',
                'ResultMessage' => 'Unable to parse response',
            ];
        }

        $result = $resultNodes[0];

        return [
            'ResultCode' => (string) ($result->ResultCode ?? '-1'),
            'ResultMessage' => (string) ($result->ResultMessage ?? ''),
            'InvoiceKey' => isset($result->InvoiceKey) ? (string) $result->InvoiceKey : null,
            'OrgReceiverCustId' => isset($result->OrgReceiverCustId) ? (string) $result->OrgReceiverCustId : null,
        ];
    }

    private function buildReceiverAddress(Address $address): string
    {
        $parts = array_filter([
            $address->street1,
            $address->street2,
            $address->district,
            $address->city,
        ]);

        return implode(' ', $parts);
    }

    /**
     * @param Package[] $packages
     * @return array<int, array<string, string>>
     */
    private function buildPieceDetails(array $packages): array
    {
        $integrationCode = $this->getIntegrationCode() ?? '';
        $index = 1;

        if ($packages === []) {
            return [[
                'BarcodeNumber' => $integrationCode . '-' . $index,
                'VolumetricWeight' => '0',
                'Weight' => '0',
                'Description' => '',
            ]];
        }

        $details = [];

        foreach ($packages as $package) {
            for ($i = 0; $i < $package->quantity; $i++) {
                $desi = $package->getDesi() ?? 0.0;
                $details[] = [
                    'BarcodeNumber' => $integrationCode . '-' . $index,
                    'VolumetricWeight' => $this->formatNumber($desi),
                    'Weight' => $this->formatNumber($package->weight),
                    'Description' => $package->description ?? '',
                ];
                $index++;
            }
        }

        return $details;
    }

    /**
     * @param Package[] $packages
     */
    private function calculatePieceCount(array $packages): int
    {
        if ($packages === []) {
            return 1;
        }

        $count = 0;

        foreach ($packages as $package) {
            $count += $package->quantity;
        }

        return $count;
    }

    /**
     * @param Package[] $packages
     */
    private function calculateTotalWeight(array $packages): float
    {
        $total = 0.0;

        foreach ($packages as $package) {
            $total += $package->weight * $package->quantity;
        }

        return $total;
    }

    /**
     * @param Package[] $packages
     */
    private function calculateTotalVolumetricWeight(array $packages): float
    {
        $total = 0.0;

        foreach ($packages as $package) {
            $desi = $package->getDesi() ?? 0.0;
            $total += $desi * $package->quantity;
        }

        return $total;
    }

    private function getShipmentDescription(): string
    {
        $packages = $this->getPackages() ?? [];

        foreach ($packages as $package) {
            if ($package->description !== null && $package->description !== '') {
                return $package->description;
            }
        }

        return '';
    }

    private function formatNumber(float $value): string
    {
        // Remove trailing zeros but keep at least integer format
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted;
    }
}
