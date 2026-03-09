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
        $this->validate('username', 'password', 'trackingNumber');

        return [
            'Username' => $this->getUsername() ?? '',
            'Password' => $this->getPassword() ?? '',
            'IntegrationCode' => $this->getTrackingNumber() ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $soapBody = $this->buildGetOrderWithIntegrationCodeXml($data);
        $body = $this->sendSoapRequest('GetOrderWithIntegrationCode', $soapBody);

        $parsed = $this->parseResponse($body);

        return $this->response = new GetTrackingStatusResponse($this, $parsed);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildGetOrderWithIntegrationCodeXml(array $data): string
    {
        return '<GetOrderWithIntegrationCode xmlns="http://tempuri.org/">'
            . '<userName>' . $this->xmlEscape($data['Username']) . '</userName>'
            . '<password>' . $this->xmlEscape($data['Password']) . '</password>'
            . '<integrationCode>' . $this->xmlEscape($data['IntegrationCode']) . '</integrationCode>'
            . '</GetOrderWithIntegrationCode>';
    }

    /**
     * Parse the GetOrderWithIntegrationCodeResponse SOAP body.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(\SimpleXMLElement $body): array
    {
        $body->registerXPathNamespace('tns', 'http://tempuri.org/');

        // Check for SOAP fault
        $faults = $body->xpath('.//faultstring');
        if ($faults !== false && isset($faults[0])) {
            return [
                'Code' => 500,
                'Message' => (string) $faults[0],
                'Order' => null,
            ];
        }

        $resultNodes = $body->xpath('.//tns:GetOrderWithIntegrationCodeResult');

        if ($resultNodes === false || !isset($resultNodes[0])) {
            return [
                'Code' => 404,
                'Message' => 'No order found',
                'Order' => null,
            ];
        }

        $result = $resultNodes[0];
        $result->registerXPathNamespace('tns', 'http://tempuri.org/');

        $orderNodes = $result->xpath('.//tns:Order');
        if ($orderNodes === false || !isset($orderNodes[0])) {
            // Try without namespace
            $orderNodes = $result->children();
            if ($orderNodes->count() === 0) {
                return [
                    'Code' => 404,
                    'Message' => 'No order found',
                    'Order' => null,
                ];
            }
            $order = $orderNodes[0];
        } else {
            $order = $orderNodes[0];
        }

        return [
            'Code' => 200,
            'Message' => 'OK',
            'Order' => $this->orderToArray($order),
        ];
    }

    /**
     * Convert a SimpleXMLElement Order node into an associative array.
     *
     * @return array<string, string|null>
     */
    private function orderToArray(\SimpleXMLElement $order): array
    {
        $data = [];
        foreach ($order->children() as $child) {
            $name = $child->getName();
            if ($name === 'PieceDetails') {
                continue;
            }
            $data[$name] = (string) $child;
        }

        return $data;
    }
}
