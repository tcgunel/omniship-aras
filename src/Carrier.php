<?php

declare(strict_types=1);

namespace Omniship\Aras;

use Omniship\Aras\Label\LabelData;
use Omniship\Aras\Label\LabelGenerator;
use Omniship\Common\AbstractHttpCarrier;
use Omniship\Common\Auth\UsernamePasswordTrait;
use Omniship\Common\Message\RequestInterface;
use Omniship\Aras\Message\CancelShipmentRequest;
use Omniship\Aras\Message\CreateShipmentRequest;
use Omniship\Aras\Message\GetTrackingStatusRequest;

class Carrier extends AbstractHttpCarrier
{
    use UsernamePasswordTrait;

    private const BASE_URL_TEST = 'https://customerservicestest.araskargo.com.tr/arascargoservice/arascargoservice.asmx';
    private const BASE_URL_PRODUCTION = 'https://customerws.araskargo.com.tr/arascargoservice.asmx';

    public function getName(): string
    {
        return 'Aras Kargo';
    }

    public function getShortName(): string
    {
        return 'Aras';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return [
            'username' => '',
            'password' => '',
            'testMode' => false,
            'senderAccountAddressId' => '',
        ];
    }

    public function getSenderAccountAddressId(): string
    {
        return (string) $this->getParameter('senderAccountAddressId');
    }

    public function setSenderAccountAddressId(?string $senderAccountAddressId): static
    {
        return $this->setParameter('senderAccountAddressId', $senderAccountAddressId ?? '');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CreateShipmentRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getTrackingStatus(array $options = []): RequestInterface
    {
        return $this->createRequest(GetTrackingStatusRequest::class, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function cancelShipment(array $options = []): RequestInterface
    {
        return $this->createRequest(CancelShipmentRequest::class, $options);
    }

    /**
     * Generate shipping labels HTML for the given shipment data.
     *
     * @param array<string, mixed> $shipmentData Same parameters as createShipment()
     * @param string|null $customTemplate Optional custom HTML template
     * @return string Complete HTML document with one label per piece
     */
    public function generateLabels(array $shipmentData, ?string $customTemplate = null): string
    {
        $labels = LabelGenerator::fromShipmentData($shipmentData);
        $generator = new LabelGenerator();

        if ($customTemplate !== null) {
            $generator->setTemplate($customTemplate);
        }

        return $generator->generate($labels);
    }

    /**
     * Build LabelData objects from shipment parameters (for custom rendering).
     *
     * @param array<string, mixed> $shipmentData
     * @return LabelData[]
     */
    public function getLabelData(array $shipmentData): array
    {
        return LabelGenerator::fromShipmentData($shipmentData);
    }

    public function getBaseUrl(): string
    {
        return $this->getTestMode() ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
    }
}
