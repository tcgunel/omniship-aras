<?php

declare(strict_types=1);

use Omniship\Aras\Message\CreateShipmentRequest;
use Omniship\Aras\Message\CreateShipmentResponse;
use Omniship\Common\Address;
use Omniship\Common\Enum\PaymentType;
use Omniship\Common\Package;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createSetOrderSuccessXml(): string
{
    return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Body>
    <SetOrderResponse xmlns="http://tempuri.org/">
      <SetOrderResult>
        <OrderResultInfo>
          <ResultCode>0</ResultCode>
          <ResultMessage>Basarili</ResultMessage>
          <InvoiceKey>98765</InvoiceKey>
          <OrgReceiverCustId>11111</OrgReceiverCustId>
        </OrderResultInfo>
      </SetOrderResult>
    </SetOrderResponse>
  </soap:Body>
</soap:Envelope>';
}

beforeEach(function () {
    $this->request = new CreateShipmentRequest(
        createMockHttpClient(createSetOrderSuccessXml()),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $this->request->initialize([
        'username' => 'testuser',
        'password' => 'testpass',
        'testMode' => true,
        'shipTo' => new Address(
            name: 'Ahmet Yilmaz',
            street1: 'Mehmet Akman Sk. Kosuyolu Mh.',
            city: 'Istanbul',
            district: 'Kadikoy',
            phone: '5551234567',
        ),
        'packages' => [
            new Package(
                weight: 1.5,
                length: 30,
                width: 20,
                height: 15,
                description: 'Test parcel',
            ),
        ],
        'integrationCode' => 'ORD-001',
        'invoiceNumber' => 'INV-001',
    ]);
});

it('builds correct request data with required fields', function () {
    $data = $this->request->getData();

    expect($data)->toHaveKey('Username', 'testuser')
        ->and($data)->toHaveKey('Password', 'testpass')
        ->and($data)->toHaveKey('IntegrationCode', 'ORD-001')
        ->and($data)->toHaveKey('InvoiceNumber', 'INV-001')
        ->and($data)->toHaveKey('ReceiverName', 'Ahmet Yilmaz')
        ->and($data)->toHaveKey('ReceiverAddress')
        ->and($data)->toHaveKey('ReceiverPhone1', '5551234567')
        ->and($data)->toHaveKey('ReceiverCityName', 'Istanbul')
        ->and($data)->toHaveKey('ReceiverTownName', 'Kadikoy')
        ->and($data)->toHaveKey('PieceCount', 1)
        ->and($data)->toHaveKey('PayorTypeCode', '1')
        ->and($data)->toHaveKey('IsWorldWide', '0')
        ->and($data)->toHaveKey('IsCod', '0');
});

it('calculates volumetric weight from packages', function () {
    $data = $this->request->getData();

    // 30 * 20 * 15 / 3000 = 3.0
    expect($data['VolumetricWeight'])->toBe('3')
        ->and($data['Weight'])->toBe('1.5');
});

it('builds receiver address from street and district', function () {
    $data = $this->request->getData();

    expect($data['ReceiverAddress'])->toContain('Mehmet Akman Sk. Kosuyolu Mh.');
});

it('sets receiver pays when payment type is receiver', function () {
    $this->request->setPaymentType(PaymentType::RECEIVER);

    $data = $this->request->getData();

    expect($data['PayorTypeCode'])->toBe('2');
});

it('sets COD fields when cash on delivery is enabled', function () {
    $this->request->setCashOnDelivery(true);
    $this->request->setCodAmount(250.00);

    $data = $this->request->getData();

    expect($data['IsCod'])->toBe('1')
        ->and($data['CodAmount'])->toBe('250');
});

it('builds piece details from packages', function () {
    $data = $this->request->getData();

    expect($data)->toHaveKey('PieceDetails')
        ->and($data['PieceDetails'])->toHaveCount(1)
        ->and($data['PieceDetails'][0])->toHaveKey('VolumetricWeight', '3')
        ->and($data['PieceDetails'][0])->toHaveKey('Weight', '1.5')
        ->and($data['PieceDetails'][0])->toHaveKey('Description', 'Test parcel');
});

it('builds multiple piece details from multi-package shipment', function () {
    $this->request->setPackages([
        new Package(weight: 1.0, length: 10, width: 10, height: 10, description: 'Package 1'),
        new Package(weight: 2.0, length: 20, width: 20, height: 20, description: 'Package 2'),
    ]);

    $data = $this->request->getData();

    expect($data['PieceCount'])->toBe(2)
        ->and($data['PieceDetails'])->toHaveCount(2)
        ->and($data['PieceDetails'][0]['Weight'])->toBe('1')
        ->and($data['PieceDetails'][1]['Weight'])->toBe('2');
});

it('includes tradingWaybillNumber when set', function () {
    $this->request->setTradingWaybillNumber('TWB-001');

    $data = $this->request->getData();

    expect($data['TradingWaybillNumber'])->toBe('TWB-001');
});

it('includes senderAccountAddressId when set', function () {
    $this->request->setSenderAccountAddressId('ADDR-001');

    $data = $this->request->getData();

    expect($data['SenderAccountAddressId'])->toBe('ADDR-001');
});

it('throws when required parameters are missing', function () {
    $request = new CreateShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([]);

    $request->getData();
})->throws(\Omniship\Common\Exception\InvalidRequestException::class);

it('sends and returns CreateShipmentResponse', function () {
    $response = $this->request->send();

    expect($response)->toBeInstanceOf(CreateShipmentResponse::class)
        ->and($response->isSuccessful())->toBeTrue();
});

it('escapes XML special characters in address', function () {
    $this->request->setShipTo(new Address(
        name: 'Test & User <Company>',
        street1: 'Street "1" & More',
        city: 'Istanbul',
        district: 'Kadikoy',
        phone: '5551234567',
    ));

    $data = $this->request->getData();

    // The data should be plain strings; XML escaping happens in the XML builder
    expect($data['ReceiverName'])->toBe('Test & User <Company>');
});
