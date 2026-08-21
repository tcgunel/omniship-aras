<?php

declare(strict_types=1);

use Omniship\Aras\Label\Barcode;
use Omniship\Aras\Label\LabelGenerator;
use Omniship\Common\Address;
use Omniship\Common\Package;

function shipmentParams(array $overrides = []): array
{
    return array_merge([
        'shipFrom' => new Address(name: 'MayCev'),
        'shipTo' => new Address(
            name: 'SERHAT CANTÜRK',
            street1: 'FEYZULLAH MAH. BUĞRACAN SOK 8/7',
            city: 'İSTANBUL',
            district: 'MALTEPE',
            phone: '051022*****',
        ),
        'packages' => [new Package(weight: 3.0, desi: 5.0)],
        'integrationCode' => '2607203877',
        'date' => '29.07.2026',
        'customerNo' => '2229954551104',
        'invoiceNumber' => 'abc123',
    ], $overrides);
}

it('carries the declared weight and desi onto every label', function () {
    $labels = LabelGenerator::fromShipmentData(shipmentParams());

    expect($labels[0]->weight)->toBe(3.0)
        ->and($labels[0]->desi)->toBe(5.0);
});

it('sums weight and desi across packages and quantities', function () {
    $labels = LabelGenerator::fromShipmentData(shipmentParams([
        'packages' => [
            new Package(weight: 2.0, desi: 4.0, quantity: 3),
            new Package(weight: 1.5, desi: 0.5),
        ],
    ]));

    // Matches CreateShipmentRequest's Weight / VolumetricWeight arithmetic.
    expect($labels[0]->totalWeight)->toBe(7.5)
        ->and($labels[0]->totalDesi)->toBe(12.5);
});

/**
 * A label describes the box it is stuck to. Printing the shipment total in
 * "Paket Kg." would tell the courier a 10 kg sack weighs 30.
 */
it('gives each piece its own weight and description', function () {
    $labels = LabelGenerator::fromShipmentData(shipmentParams([
        'packages' => [
            new Package(weight: 10.0, description: 'Chandler 10 Kg.', quantity: 2),
            new Package(weight: 5.0, description: 'Fernor 5 Kg.'),
        ],
    ]));

    expect($labels)->toHaveCount(3)
        ->and($labels[0]->weight)->toBe(10.0)
        ->and($labels[1]->weight)->toBe(10.0)
        ->and($labels[2]->weight)->toBe(5.0)
        ->and($labels[2]->productName)->toBe('Fernor 5 Kg.')
        ->and($labels[2]->totalWeight)->toBe(25.0)
        ->and($labels[2]->pieceNumber)->toBe(3)
        ->and($labels[2]->totalPieces)->toBe(3);
});

/**
 * Barcodes can raise the piece count past the parcel list; those extra labels
 * have no figures of their own and fall back to the shipment totals.
 */
it('falls back to shipment totals for pieces beyond the parcel list', function () {
    $labels = LabelGenerator::fromShipmentData(shipmentParams([
        'packages' => [new Package(weight: 6.0)],
        'barcodes' => ['A01', 'A02'],
    ]));

    expect($labels)->toHaveCount(2)
        ->and($labels[0]->weight)->toBe(6.0)
        ->and($labels[1]->weight)->toBe(6.0);
});

it('derives desi from dimensions when none was supplied', function () {
    $labels = LabelGenerator::fromShipmentData(shipmentParams([
        'packages' => [new Package(weight: 1.0, length: 30, width: 20, height: 10)],
    ]));

    expect($labels[0]->desi)->toBe(2.0); // 30*20*10/3000
});

it('renders weight without trailing zeros', function () {
    $html = (new LabelGenerator())->setTemplate('[{{weight}}|{{desi}}]')
        ->generate(LabelGenerator::fromShipmentData(shipmentParams()));

    expect($html)->toContain('[3|5]');
});

it('renders both barcodes as inline svg, not webfont text', function () {
    $html = (new LabelGenerator())->generate(LabelGenerator::fromShipmentData(shipmentParams()));

    expect($html)->toContain('<svg')
        ->and(substr_count($html, '<svg'))->toBe(2);
});

it('produces a scalable barcode with no intrinsic pixel size', function () {
    $svg = Barcode::svg('2607203877');

    // Physical width is fixed by the template in millimetres; the SVG must
    // stretch to it rather than impose its own pixel width.
    expect($svg)->toContain('preserveAspectRatio="none"')
        ->and($svg)->toContain('viewBox=')
        ->and($svg)->toContain('width="100%"')
        ->and($svg)->not->toContain('<?xml');
});

/**
 * Aras' courier app could not read labels whose bars sat against the template's
 * 2mm padding with the cell border immediately beyond it. Camera scanners need
 * the full ten modules Code 128 specifies; carrying it in the viewBox means it
 * scales with the bars and survives templates merchants have edited.
 */
it('carries a ten-module quiet zone on both sides of the symbol', function () {
    $svg = Barcode::svg('2607203877');

    expect($svg)->toMatch('/viewBox="-20 0 [\d.]+ [\d.]+"/');

    preg_match('/viewBox="(-?[\d.]+) 0 ([\d.]+) /', $svg, $box);

    // The generator lays the bars out from x=0, so the widened viewport must
    // add the margin at both ends, not just the left.
    $bars = (float) $box[2] - (2 * 20);

    expect((float) $box[1])->toBe(-20.0)
        ->and($bars)->toBeGreaterThan(0.0);
});

it('returns an empty barcode rather than throwing on an empty value', function () {
    expect(Barcode::svg(''))->toBe('');
});

/**
 * {{integrationCode}} is a prefix of {{integrationCodeSvg}}. str_replace()
 * walks its array in order, so substituting the short key first would rewrite
 * the middle of the long one and print a stray "Svg}}" on the label.
 */
it('does not let the shorter placeholder eat the svg one', function () {
    $html = (new LabelGenerator())
        ->setTemplate('[{{integrationCodeSvg}}][{{integrationCode}}]')
        ->generate(LabelGenerator::fromShipmentData(shipmentParams()));

    expect($html)->toContain('<svg')
        ->and($html)->not->toContain('Svg}}')
        ->and($html)->toContain('[2607203877]');
});

/**
 * The two numbers have to agree. A label wider than the sheet is shrunk to fit
 * by the browser, which narrows the bars below what the design asked for --
 * the exact failure the @page pinning exists to prevent.
 */
it('pins the printed sheet to the 160x100mm label stock, matching the label', function () {
    $html = (new LabelGenerator())->generate(LabelGenerator::fromShipmentData(shipmentParams()));

    expect($html)->toContain('@page { size: 160mm 100mm; margin: 0; }')
        ->and($html)->toContain('class="label" style="width:160mm;height:100mm;');
});

/**
 * The label passes through couriers, sorting hubs and neighbours. Aras already
 * has the real number from ReceiverPhone1, so nothing is lost by masking it.
 */
it('masks the recipient phone by default', function () {
    $html = (new LabelGenerator())->setTemplate('[{{receiverPhone}}][{{receiverPhoneFull}}]')
        ->generate(LabelGenerator::fromShipmentData(shipmentParams([
            'shipTo' => new Address(name: 'X', street1: 'Y', phone: '05399873888'),
        ])));

    expect($html)->toContain('[053998*****]')
        ->and($html)->toContain('[05399873888]');
});

it('leaves a phone too short to mask alone', function () {
    $html = (new LabelGenerator())->setTemplate('[{{receiverPhone}}]')
        ->generate(LabelGenerator::fromShipmentData(shipmentParams([
            'shipTo' => new Address(name: 'X', street1: 'Y', phone: '0539'),
        ])));

    expect($html)->toContain('[0539]');
});

/**
 * A shipment declared purely by weight has no desi. Printing "0" reads as a
 * measured zero rather than as "not applicable".
 */
it('hides desi when the shipment was declared by weight alone', function () {
    $html = (new LabelGenerator())->generate(LabelGenerator::fromShipmentData(shipmentParams([
        'packages' => [new Package(weight: 5.0)],
    ])));

    expect($html)->toContain('display:none;')
        ->and($html)->toContain('5 Kg.');
});

it('shows the customer number and product name from the shipment params', function () {
    $html = (new LabelGenerator())->generate(LabelGenerator::fromShipmentData(shipmentParams([
        'packages' => [new Package(weight: 3.0, desi: 5.0, description: 'Ceviz İçi 1 KG')],
    ])));

    expect($html)->toContain('2229954551104')
        ->and($html)->toContain('Ceviz İçi 1 KG')
        ->and($html)->toContain('Müşteri No');
});

it('hides the customer number row when the shop has not set one', function () {
    $html = (new LabelGenerator())->setTemplate('<i style="display:{{customerNoDisplay}}">{{customerNo}}</i>')
        ->generate(LabelGenerator::fromShipmentData(shipmentParams(['customerNo' => ''])));

    expect($html)->toContain('display:none');
});

it('keeps the default template driven by placeholders', function () {
    $html = (new LabelGenerator())->generate(LabelGenerator::fromShipmentData(shipmentParams()));

    expect($html)->toContain('MayCev')
        ->and($html)->toContain('SERHAT CANTÜRK')
        ->and($html)->toContain('2607203877')
        ->and($html)->toContain('29.07.2026')
        ->and($html)->not->toContain('{{');
});

it('still renders templates written against the old font barcode', function () {
    $html = (new LabelGenerator())
        ->setTemplate('<div class="barcode">{{barcodeNumber}}</div>')
        ->generate(LabelGenerator::fromShipmentData(shipmentParams()));

    expect($html)->toContain('class="barcode"')
        ->and($html)->toContain('Libre Barcode 128 Text');
});
