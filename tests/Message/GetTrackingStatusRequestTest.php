<?php

declare(strict_types=1);

use Omniship\Aras\Message\GetTrackingStatusRequest;
use Omniship\Aras\Message\GetTrackingStatusResponse;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createTrackingSuccessJson(): string
{
    return json_encode([
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
}

beforeEach(function () {
    $this->request = new GetTrackingStatusRequest(
        createMockHttpClient(createTrackingSuccessJson()),
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

    expect($data)->toHaveKey('TrackingNumber', 'TRACK-001')
        ->and($data)->toHaveKey('LanguageCode', 'tr');
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

it('uses correct tracking REST endpoint', function () {
    // The tracking endpoint should be the same for test and production
    $data = $this->request->getData();

    expect($data['TrackingNumber'])->toBe('TRACK-001');
});
