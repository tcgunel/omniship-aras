<?php

declare(strict_types=1);

use Omniship\Aras\Message\GetTrackingStatusRequest;
use Omniship\Aras\Message\GetTrackingStatusResponse;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createGetOrderWithIntegrationCodeSuccessXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body>
    <GetOrderWithIntegrationCodeResponse xmlns="http://tempuri.org/">
      <GetOrderWithIntegrationCodeResult>
        <Order>
          <TradingWaybillNumber>TRACK-001</TradingWaybillNumber>
          <InvoiceNumber>TRACK-001</InvoiceNumber>
          <ReceiverName>Test Receiver</ReceiverName>
          <ReceiverAddress>Test Address</ReceiverAddress>
          <ReceiverPhone1>5551234567</ReceiverPhone1>
          <ReceiverCityName>ISTANBUL</ReceiverCityName>
          <ReceiverTownName>KADIKOY</ReceiverTownName>
          <IntegrationCode>TRACK-001</IntegrationCode>
          <PieceCount>1</PieceCount>
          <PayorTypeCode>1</PayorTypeCode>
          <IsWorldWide>0</IsWorldWide>
          <IsCod>0</IsCod>
        </Order>
      </GetOrderWithIntegrationCodeResult>
    </GetOrderWithIntegrationCodeResponse>
  </soap:Body>
</soap:Envelope>';
}

beforeEach(function () {
    $this->request = new GetTrackingStatusRequest(
        createMockHttpClient(createGetOrderWithIntegrationCodeSuccessXml()),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $this->request->initialize([
        'username' => 'testuser',
        'password' => 'testpass',
        'testMode' => true,
        'trackingNumber' => 'TRACK-001',
    ]);
});

it('builds correct request data', function () {
    $data = $this->request->getData();

    expect($data)->toHaveKey('Username', 'testuser')
        ->and($data)->toHaveKey('Password', 'testpass')
        ->and($data)->toHaveKey('IntegrationCode', 'TRACK-001');
});

it('throws when trackingNumber is missing', function () {
    $request = new GetTrackingStatusRequest(
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

it('sends and returns GetTrackingStatusResponse', function () {
    $response = $this->request->send();

    expect($response)->toBeInstanceOf(GetTrackingStatusResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});

it('validates username and password are required', function () {
    $request = new GetTrackingStatusRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'trackingNumber' => 'TRACK-001',
    ]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);
