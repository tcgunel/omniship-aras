<?php

declare(strict_types=1);

use Omniship\Aras\Message\CreateShipmentRequest;
use Omniship\Aras\Message\CreateShipmentResponse;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

function createArasShipmentResponseWith(array $data): CreateShipmentResponse
{
    $request = new CreateShipmentRequest(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $request->initialize([
        'username' => 'test',
        'password' => 'test',
        'integrationCode' => 'ORD-001',
    ]);

    return new CreateShipmentResponse($request, $data);
}

it('parses successful response', function () {
    $response = createArasShipmentResponseWith([
        'ResultCode' => '0',
        'ResultMessage' => 'Basarili',
        'InvoiceKey' => '98765',
        'OrgReceiverCustId' => '11111',
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->getShipmentId())->toBe('98765')
        ->and($response->getTrackingNumber())->toBe('ORD-001')
        ->and($response->getBarcode())->toBe('98765')
        ->and($response->getMessage())->toBe('Basarili')
        ->and($response->getCode())->toBe('0');
});

it('parses error response', function () {
    $response = createArasShipmentResponseWith([
        'ResultCode' => '1',
        'ResultMessage' => 'Kullanici adi veya sifre hatali',
    ]);

    expect($response->isSuccessful())->toBeFalse()
        ->and($response->getMessage())->toBe('Kullanici adi veya sifre hatali')
        ->and($response->getShipmentId())->toBeNull()
        ->and($response->getCode())->toBe('1');
});

it('returns null for missing fields in error response', function () {
    $response = createArasShipmentResponseWith([
        'ResultCode' => '99',
        'ResultMessage' => 'Unknown error',
    ]);

    expect($response->getShipmentId())->toBeNull()
        ->and($response->getBarcode())->toBeNull()
        ->and($response->getLabel())->toBeNull()
        ->and($response->getTotalCharge())->toBeNull()
        ->and($response->getCurrency())->toBeNull();
});

it('returns raw data', function () {
    $data = ['ResultCode' => '0', 'ResultMessage' => 'OK', 'InvoiceKey' => '123'];
    $response = createArasShipmentResponseWith($data);

    expect($response->getData())->toBe($data);
});

it('treats only ResultCode 0 as success', function () {
    $success = createArasShipmentResponseWith(['ResultCode' => '0', 'ResultMessage' => 'OK']);
    $fail1 = createArasShipmentResponseWith(['ResultCode' => '1', 'ResultMessage' => 'Fail']);
    $fail2 = createArasShipmentResponseWith(['ResultCode' => '-1', 'ResultMessage' => 'Error']);

    expect($success->isSuccessful())->toBeTrue()
        ->and($fail1->isSuccessful())->toBeFalse()
        ->and($fail2->isSuccessful())->toBeFalse();
});
