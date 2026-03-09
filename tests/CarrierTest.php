<?php

declare(strict_types=1);

use Omniship\Aras\Carrier;
use Omniship\Aras\Message\CancelShipmentRequest;
use Omniship\Aras\Message\CreateShipmentRequest;
use Omniship\Aras\Message\GetTrackingStatusRequest;

use function Omniship\Aras\Tests\createMockHttpClient;
use function Omniship\Aras\Tests\createMockRequestFactory;
use function Omniship\Aras\Tests\createMockStreamFactory;

beforeEach(function () {
    $this->carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $this->carrier->initialize([
        'username' => 'testuser',
        'password' => 'testpass',
        'testMode' => true,
    ]);
});

it('has the correct name', function () {
    expect($this->carrier->getName())->toBe('Aras Kargo')
        ->and($this->carrier->getShortName())->toBe('Aras');
});

it('has correct default parameters', function () {
    $carrier = new Carrier(
        createMockHttpClient(),
        createMockRequestFactory(),
        createMockStreamFactory(),
    );
    $carrier->initialize();

    expect($carrier->getUsername())->toBe('')
        ->and($carrier->getPassword())->toBe('')
        ->and($carrier->getTestMode())->toBeFalse()
        ->and($carrier->getSenderAccountAddressId())->toBe('');
});

it('initializes with custom parameters', function () {
    expect($this->carrier->getUsername())->toBe('testuser')
        ->and($this->carrier->getPassword())->toBe('testpass')
        ->and($this->carrier->getTestMode())->toBeTrue();
});

it('returns test base URL in test mode', function () {
    expect($this->carrier->getBaseUrl())->toContain('customerservicestest.araskargo.com.tr');
});

it('returns production base URL in production mode', function () {
    $this->carrier->setTestMode(false);
    expect($this->carrier->getBaseUrl())->toContain('customerservices.araskargo.com.tr')
        ->and($this->carrier->getBaseUrl())->not->toContain('test');
});

it('supports createShipment method', function () {
    expect($this->carrier->supports('createShipment'))->toBeTrue();
});

it('supports getTrackingStatus method', function () {
    expect($this->carrier->supports('getTrackingStatus'))->toBeTrue();
});

it('supports cancelShipment method', function () {
    expect($this->carrier->supports('cancelShipment'))->toBeTrue();
});

it('creates a CreateShipmentRequest', function () {
    $request = $this->carrier->createShipment([
        'integrationCode' => 'TEST123',
    ]);

    expect($request)->toBeInstanceOf(CreateShipmentRequest::class);
});

it('creates a GetTrackingStatusRequest', function () {
    $request = $this->carrier->getTrackingStatus([
        'trackingNumber' => 'TEST123',
    ]);

    expect($request)->toBeInstanceOf(GetTrackingStatusRequest::class);
});

it('creates a CancelShipmentRequest', function () {
    $request = $this->carrier->cancelShipment([
        'integrationCode' => 'TEST123',
    ]);

    expect($request)->toBeInstanceOf(CancelShipmentRequest::class);
});

it('sets and gets senderAccountAddressId', function () {
    $this->carrier->setSenderAccountAddressId('ADDR-001');

    expect($this->carrier->getSenderAccountAddressId())->toBe('ADDR-001');
});
