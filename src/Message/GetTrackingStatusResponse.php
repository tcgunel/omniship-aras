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

        $responses = $this->getResponses();

        if ($responses === []) {
            return new TrackingInfo(
                trackingNumber: $trackingNumber,
                status: ShipmentStatus::UNKNOWN,
                events: [],
                carrier: 'Aras Kargo',
            );
        }

        $events = $this->parseEvents($responses);
        $status = $events !== [] ? $events[count($events) - 1]->status : ShipmentStatus::UNKNOWN;

        return new TrackingInfo(
            trackingNumber: $trackingNumber,
            status: $status,
            events: $events,
            carrier: 'Aras Kargo',
        );
    }

    /**
     * Map a description string to a ShipmentStatus using keyword matching.
     */
    public static function mapStatusFromDescription(string $description): ShipmentStatus
    {
        $lower = mb_strtolower($description, 'UTF-8');

        // DELIVERED - check first since it's most specific
        if (str_contains($lower, 'teslim edildi') || str_contains($lower, 'teslim edilmistir')) {
            return ShipmentStatus::DELIVERED;
        }

        // PICKED_UP
        if (str_contains($lower, 'teslim alindi') || str_contains($lower, 'teslim alinmistir')
            || str_contains($lower, 'teslim alındı') || str_contains($lower, 'teslim alınmıştır')) {
            return ShipmentStatus::PICKED_UP;
        }

        // OUT_FOR_DELIVERY
        if (str_contains($lower, 'teslimat subesinde') || str_contains($lower, 'teslimat şubesinde')
            || str_contains($lower, 'teslimat subemize') || str_contains($lower, 'teslimat şubemize')) {
            return ShipmentStatus::OUT_FOR_DELIVERY;
        }

        // PRE_TRANSIT
        if (str_contains($lower, 'çıkış şubesinde') || str_contains($lower, 'cikis subesinde')
            || str_contains($lower, 'kabul')) {
            return ShipmentStatus::PRE_TRANSIT;
        }

        // IN_TRANSIT
        if (str_contains($lower, 'yolda') || str_contains($lower, 'transfer')
            || str_contains($lower, 'gonderiliyor') || str_contains($lower, 'gönderi̇li̇yor')
            || str_contains($lower, 'gönderiliyor')
            || str_contains($lower, 'aracimiza yuklenmistir') || str_contains($lower, 'aracımıza yüklenmiştir')) {
            return ShipmentStatus::IN_TRANSIT;
        }

        // RETURNED
        if (str_contains($lower, 'iade')) {
            return ShipmentStatus::RETURNED;
        }

        // CANCELLED
        if (str_contains($lower, 'iptal')) {
            return ShipmentStatus::CANCELLED;
        }

        return ShipmentStatus::UNKNOWN;
    }

    /**
     * @param array<int, array<string, mixed>> $responses
     * @return TrackingEvent[]
     */
    private function parseEvents(array $responses): array
    {
        $events = [];

        foreach ($responses as $item) {
            $description = '';
            if (isset($item['Description']) && is_string($item['Description'])) {
                $description = $item['Description'];
            }

            $status = self::mapStatusFromDescription($description);

            $dateTime = new \DateTimeImmutable();
            if (isset($item['TransactionDate']) && is_string($item['TransactionDate']) && $item['TransactionDate'] !== '') {
                try {
                    $dateTime = new \DateTimeImmutable($item['TransactionDate']);
                } catch (\Exception) {
                    // Keep default
                }
            }

            $location = null;
            if (isset($item['UnitName']) && is_string($item['UnitName']) && $item['UnitName'] !== '') {
                $location = $item['UnitName'];
            }

            $events[] = new TrackingEvent(
                status: $status,
                description: $description,
                occurredAt: $dateTime,
                location: $location,
            );
        }

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getResponses(): array
    {
        if (!is_array($this->data) || !isset($this->data['Responses'])) {
            return [];
        }

        if (!is_array($this->data['Responses'])) {
            return [];
        }

        /** @var array<int, array<string, mixed>> */
        return $this->data['Responses'];
    }

    private function getResponseCode(): ?int
    {
        if (!is_array($this->data) || !isset($this->data['Code'])) {
            return null;
        }

        return (int) $this->data['Code'];
    }
}
