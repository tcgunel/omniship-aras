<?php

declare(strict_types=1);

use Omniship\Aras\Message\CancelShipmentRequest;
use Omniship\Aras\Message\CancelShipmentResponse;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createCancelDispatchSuccessXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body>
    <CancelDispatchResponse xmlns="http://tempuri.org/">
      <CancelDispatchResult>
        <ResultCode>0</ResultCode>
        <ResultMessage>Basarili</ResultMessage>
        <CargoKey>CK-001</CargoKey>
      </CancelDispatchResult>
    </CancelDispatchResponse>
  </soap:Body>
</soap:Envelope>';
}

beforeEach(function () {
    $this->request = new CancelShipmentRequest(
        createMockHttpClient(createCancelDispatchSuccessXml()),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $this->request->initialize([
        'username' => 'testuser',
        'password' => 'testpass',
        'testMode' => true,
        'integrationCode' => 'ORD-001',
    ]);
});

it('builds correct request data', function () {
    $data = $this->request->getData();

    expect($data)->toHaveKey('Username', 'testuser')
        ->and($data)->toHaveKey('Password', 'testpass')
        ->and($data)->toHaveKey('IntegrationCode', 'ORD-001');
});

it('throws when integrationCode is missing', function () {
    $request = new CancelShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'testuser',
        'password' => 'testpass',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

it('sends and returns CancelShipmentResponse', function () {
    $response = $this->request->send();

    expect($response)->toBeInstanceOf(CancelShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});
