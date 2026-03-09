<?php

declare(strict_types=1);

namespace Omniship\Aras\Message;

use Omniship\Common\Message\ResponseInterface;

class GetTrackingStatusRequest extends AbstractArasRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('trackingNumber');

        return [
            'TrackingNumber' => $this->getTrackingNumber() ?? '',
            'LanguageCode' => 'tr',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $response = $this->sendHttpRequest(
            method: 'POST',
            url: $this->getTrackingUrl(),
            headers: [
                'Content-Type' => 'application/json',
            ],
            body: json_encode($data, JSON_THROW_ON_ERROR),
        );

        $body = (string) $response->getBody();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return $this->response = new GetTrackingStatusResponse($this, $decoded);
    }
}
