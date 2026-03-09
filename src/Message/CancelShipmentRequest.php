<?php

declare(strict_types=1);

namespace Omniship\Aras\Message;

use Omniship\Common\Message\ResponseInterface;

class CancelShipmentRequest extends AbstractArasRequest
{
    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $this->validate('username', 'password', 'integrationCode');

        return [
            'Username' => $this->getUsername() ?? '',
            'Password' => $this->getPassword() ?? '',
            'IntegrationCode' => $this->getIntegrationCode() ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(array $data): ResponseInterface
    {
        $soapBody = $this->buildCancelDispatchXml($data);
        $body = $this->sendSoapRequest('CancelDispatch', $soapBody);

        $parsed = $this->parseCancelDispatchResponse($body);

        return $this->response = new CancelShipmentResponse($this, $parsed);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildCancelDispatchXml(array $data): string
    {
        return '<CancelDispatch xmlns="http://tempuri.org/">'
            . '<userName>' . $this->xmlEscape((string) $data['Username']) . '</userName>'
            . '<password>' . $this->xmlEscape((string) $data['Password']) . '</password>'
            . '<integrationCode>' . $this->xmlEscape((string) $data['IntegrationCode']) . '</integrationCode>'
            . '</CancelDispatch>';
    }

    /**
     * Parse the CancelDispatchResponse XML into an associative array.
     *
     * @return array<string, string|null>
     */
    private function parseCancelDispatchResponse(\SimpleXMLElement $body): array
    {
        $body->registerXPathNamespace('tns', 'http://tempuri.org/');

        $resultNodes = $body->xpath('.//tns:CancelDispatchResult');

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
            'CargoKey' => isset($result->CargoKey) ? (string) $result->CargoKey : null,
        ];
    }
}
