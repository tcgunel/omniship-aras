<?php

declare(strict_types=1);

use Omniship\Aras\Message\GetTrackingStatusResponse;
use Omniship\Common\Enum\ShipmentStatus;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createArasTrackingResponseWith(array $data): GetTrackingStatusResponse
{
    $request = new \Omniship\Aras\Message\GetTrackingStatusRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'testuser',
        'password' => 'testpass',
        'trackingNumber' => 'TRACK-001',
    ]);

    return new GetTrackingStatusResponse($request, $data);
}

it('parses successful order lookup response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'OK',
        'Order' => [
            'TradingWaybillNumber' => 'TRACK-001',
            'InvoiceNumber' => 'TRACK-001',
            'ReceiverName' => 'Test Receiver',
            'ReceiverCityName' => 'ISTANBUL',
            'ReceiverTownName' => 'KADIKOY',
            'IntegrationCode' => 'TRACK-001',
            'PieceCount' => '1',
        ],
    ]);

    expect($response->isSuccessful())->toBeTrue();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('TRACK-001')
        ->and($info->status)->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and($info->carrier)->toBe('Aras Kargo')
        ->and($info->events)->toHaveCount(1)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and($info->events[0]->location)->toBe('ISTANBUL');
});

it('returns unknown status when order is not found', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'OK',
        'Order' => null,
    ]);

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('TRACK-001')
        ->and($info->status)->toBe(ShipmentStatus::UNKNOWN)
        ->and($info->events)->toBe([]);
});

it('parses error response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 404,
        'Message' => 'No order found',
        'Order' => null,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('No order found');
});

it('parses SOAP fault response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 500,
        'Message' => 'Kullanıcı Adı yada Şifreniz yanlış',
        'Order' => null,
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Kullanıcı Adı yada Şifreniz yanlış');
});

it('returns code as string', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'OK',
        'Order' => [
            'IntegrationCode' => 'TRACK-001',
            'ReceiverCityName' => 'ANKARA',
        ],
    ]);

    expect($response->getCode())->toBe('200');
});

it('returns raw data via getData', function () {
    $data = [
        'Code' => 200,
        'Message' => 'OK',
        'Order' => [
            'IntegrationCode' => 'TRACK-001',
        ],
    ];

    $response = createArasTrackingResponseWith($data);

    expect($response->getData())->toBe($data);
});

it('uses integration code from order as tracking number', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'OK',
        'Order' => [
            'IntegrationCode' => 'ORD-999',
            'ReceiverCityName' => 'IZMIR',
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('ORD-999');
});
