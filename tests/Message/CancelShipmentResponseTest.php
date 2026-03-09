<?php

declare(strict_types=1);

use Omniship\Aras\Message\CancelShipmentResponse;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createArasCancelResponseWith(array $data): CancelShipmentResponse
{
    $request = new \Omniship\Aras\Message\CancelShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );

    return new CancelShipmentResponse($request, $data);
}

it('parses successful cancel response', function () {
    $response = createArasCancelResponseWith([
        'ResultCode' => '0',
        'ResultMessage' => 'Basarili',
        'CargoKey' => 'CK-001',
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->isCancelled())->toBeTrue()
        ->and($response->getMessage())->toBe('Basarili')
        ->and($response->getCode())->toBe('0');
});

it('parses failed cancel response', function () {
    $response = createArasCancelResponseWith([
        'ResultCode' => '1',
        'ResultMessage' => 'Gonderi zaten teslim edilmis',
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->isCancelled())->toBeFalse()
        ->and($response->getMessage())->toBe('Gonderi zaten teslim edilmis');
});

it('treats only ResultCode 0 as success', function () {
    $success = createArasCancelResponseWith(['ResultCode' => '0', 'ResultMessage' => 'OK']);
    $fail = createArasCancelResponseWith(['ResultCode' => '1', 'ResultMessage' => 'Fail']);

    expect($success->isSuccessful())->toBeTrue()
        ->and($success->isCancelled())->toBeTrue()
        ->and($fail->isSuccessful())->toBeFalse()
        ->and($fail->isCancelled())->toBeFalse();
});

it('returns raw data', function () {
    $data = ['ResultCode' => '0', 'ResultMessage' => 'OK'];
    $response = createArasCancelResponseWith($data);

    expect($response->getData())->toBe($data);
});
