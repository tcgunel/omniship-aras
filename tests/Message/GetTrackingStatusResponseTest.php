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
        'trackingNumber' => 'TRACK-001',
    ]);

    return new GetTrackingStatusResponse($request, $data);
}

it('parses delivered shipment response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T14:30:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Teslim Edildi',
            ],
        ],
    ]);

    expect($response->isSuccessful())->toBeTrue();

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('TRACK-001')
        ->and($info->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->carrier)->toBe('Aras Kargo')
        ->and($info->events)->toHaveCount(1)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->events[0]->location)->toBe('ISTANBUL')
        ->and($info->events[0]->description)->toBe('Teslim Edildi');
});

it('parses in-transit response with transfer keyword', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T10:00:00',
                'UnitName' => 'ANKARA',
                'ShipmentLineTransType' => '1',
                'Description' => 'Transfer merkezine gonderiliyor',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::IN_TRANSIT);
});

it('parses pre-transit response with kabul keyword', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-14T10:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Cikis Subesinde kabul islemi yapildi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::PRE_TRANSIT);
});

it('parses picked up response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-14T11:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Kargo teslim alindi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::PICKED_UP);
});

it('parses out for delivery response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T08:30:00',
                'UnitName' => 'KADIKOY',
                'ShipmentLineTransType' => '1',
                'Description' => 'Teslimat Subesinde dagitima cikarildi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::OUT_FOR_DELIVERY);
});

it('parses returned response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-16T10:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Gonderici talebine gore iade islemi yapildi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::RETURNED);
});

it('parses cancelled response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T09:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Gonderi iptal edildi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::CANCELLED);
});

it('parses loaded onto vehicle response as in transit', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T06:00:00',
                'UnitName' => 'ANKARA',
                'ShipmentLineTransType' => '1',
                'Description' => 'Kargo aracimiza yuklenmistir',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::IN_TRANSIT);
});

it('returns unknown status for unrecognized description', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T10:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Some unknown status message',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::UNKNOWN);
});

it('handles multiple tracking events and uses last as current status', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-14T10:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Cikis Subesinde kabul islemi yapildi',
            ],
            [
                'TransactionDate' => '2024-01-14T14:00:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Transfer merkezine gonderiliyor',
            ],
            [
                'TransactionDate' => '2024-01-15T14:30:00',
                'UnitName' => 'ANKARA',
                'ShipmentLineTransType' => '1',
                'Description' => 'Teslim edildi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->events)->toHaveCount(3)
        ->and($info->status)->toBe(ShipmentStatus::DELIVERED)
        ->and($info->events[0]->status)->toBe(ShipmentStatus::PRE_TRANSIT)
        ->and($info->events[1]->status)->toBe(ShipmentStatus::IN_TRANSIT)
        ->and($info->events[2]->status)->toBe(ShipmentStatus::DELIVERED);
});

it('handles empty responses list', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->trackingNumber)->toBe('TRACK-001')
        ->and($info->status)->toBe(ShipmentStatus::UNKNOWN)
        ->and($info->events)->toBe([]);
});

it('parses error response', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 400,
        'Message' => 'Kargo bulunamadi',
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Kargo bulunamadi');
});

it('parses event date correctly', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T14:30:00',
                'UnitName' => 'ISTANBUL',
                'ShipmentLineTransType' => '1',
                'Description' => 'Teslim edildi',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->events[0]->occurredAt->format('Y-m-d H:i:s'))->toBe('2024-01-15 14:30:00');
});

it('maps teslimat subesine keyword to out for delivery', function () {
    $response = createArasTrackingResponseWith([
        'Code' => 200,
        'Message' => 'Success',
        'Responses' => [
            [
                'TransactionDate' => '2024-01-15T08:00:00',
                'UnitName' => 'BEYOGLU',
                'ShipmentLineTransType' => '1',
                'Description' => 'Kargo teslimat subemize ulasti',
            ],
        ],
    ]);

    $info = $response->getTrackingInfo();

    expect($info->status)->toBe(ShipmentStatus::OUT_FOR_DELIVERY);
});
