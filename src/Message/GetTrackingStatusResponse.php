<?php

declare(strict_types=1);

namespace Omniship\Aras\Message;

use Omniship\Common\Enum\ShipmentStatus;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\TrackingResponse;
use Omniship\Common\TrackingEvent;
use Omniship\Common\TrackingInfo;

class GetTrackingStatusResponse extends AbstractResponse implements TrackingResponse
{
    public function isSuccessful(): bool
    {
        return $this->getResponseCode() === 200;
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['Message'])) {
            return null;
        }

        return (string) $this->data['Message'];
    }

    public function getCode(): ?string
    {
        $code = $this->getResponseCode();

        return $code !== null ? (string) $code : null;
    }

    public function getTrackingInfo(): TrackingInfo
    {
        $trackingNumber = '';
        $request = $this->getRequest();
        if ($request instanceof GetTrackingStatusRequest) {
            $trackingNumber = $request->getTrackingNumber() ?? '';
        }

        $order = $this->getOrder();

        if ($order === null) {
            return new TrackingInfo(
                trackingNumber: $trackingNumber,
                status: ShipmentStatus::UNKNOWN,
                events: [],
                carrier: 'Aras Kargo',
            );
        }

        // Order exists — shipment was registered
        $event = new TrackingEvent(
            status: ShipmentStatus::PRE_TRANSIT,
            description: 'Sipariş kaydı oluşturuldu',
            occurredAt: new \DateTimeImmutable(),
            location: $order['ReceiverCityName'] ?? null,
        );

        return new TrackingInfo(
            trackingNumber: $order['IntegrationCode'] ?? $trackingNumber,
            status: ShipmentStatus::PRE_TRANSIT,
            events: [$event],
            carrier: 'Aras Kargo',
        );
    }

    /**
     * @return array<string, string|null>|null
     */
    private function getOrder(): ?array
    {
        if (!is_array($this->data) || !isset($this->data['Order'])) {
            return null;
        }

        if (!is_array($this->data['Order'])) {
            return null;
        }

        /** @var array<string, string|null> */
        return $this->data['Order'];
    }

    private function getResponseCode(): ?int
    {
        if (!is_array($this->data) || !isset($this->data['Code'])) {
            return null;
        }

        return (int) $this->data['Code'];
    }
}
