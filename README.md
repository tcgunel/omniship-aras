# Omniship Aras Kargo

Aras Kargo carrier driver for the [Omniship](https://github.com/tcgunel/omniship) shipping library.

## Installation

```bash
composer require tcgunel/omniship-aras
```

## Usage

### Initialize

```php
use Omniship\Omniship;

$carrier = Omniship::create('Aras');
$carrier->initialize([
    'username' => 'your-username',
    'password' => 'your-password',
    'testMode' => true, // false for production
    'senderAccountAddressId' => '', // optional
]);
```

### Create Shipment

```php
use Omniship\Common\Address;
use Omniship\Common\Package;
use Omniship\Common\Enum\PaymentType;

$response = $carrier->createShipment([
    'shipTo' => new Address(
        name: 'Ahmet Yilmaz',
        street1: 'Ataturk Cad. No:1',
        city: 'Istanbul',
        district: 'Kadikoy',
        phone: '05551234567',
    ),
    'packages' => [
        new Package(
            weight: 1.5,
            length: 30,
            width: 20,
            height: 15,
            description: 'Elektronik urun',
        ),
    ],
    'integrationCode' => 'ORD-001',       // required - your order reference
    'invoiceNumber' => 'INV-001',          // optional
    'tradingWaybillNumber' => 'TWB-001',   // required - irsaliye no
    'barcodes' => ['BARCODE-001'],         // one barcode per piece
    'paymentType' => PaymentType::SENDER,  // or PaymentType::RECEIVER
    'cashOnDelivery' => false,
    'codAmount' => 0.0,
])->send();

if ($response->isSuccessful()) {
    echo $response->getShipmentId();    // InvoiceKey
    echo $response->getBarcode();       // same as InvoiceKey
    echo $response->getTrackingNumber(); // integrationCode
} else {
    echo $response->getMessage();       // error description
    echo $response->getCode();          // Aras result code
}
```

#### Integration Code & Barcodes

Per Aras Kargo requirements:
- `integrationCode` must be **unique, minimum 6 characters, numeric only**
- `barcodes` are auto-generated from `integrationCode` + 2-digit piece suffix if not provided
- Single piece: `integrationCode=12345678` → barcode `1234567801`
- Multi piece: `integrationCode=12345678` → barcodes `1234567801`, `1234567802`

You can also provide explicit barcodes (one per piece):

```php
'barcodes' => ['1234567801', '1234567802'],
```

If a package has `quantity: 3`, you need 3 barcodes for that package's pieces.

### Generate Labels

Aras Kargo does not provide labels — they must be designed by the integrator. The built-in label generator creates print-ready HTML labels with barcodes (via Google Fonts Libre Barcode 128).

```php
// Generate labels using default template
$html = $carrier->generateLabels([
    'shipFrom' => $shipFrom,
    'shipTo' => $shipTo,
    'packages' => $packages,
    'integrationCode' => '12345678',
    'paymentType' => PaymentType::SENDER,
    'cashOnDelivery' => false,
]);

// Output or save the HTML
file_put_contents('labels.html', $html);
```

Each package piece gets its own label with:
- Carrier name and date
- Sender name
- Receiver name, phone, address
- Payment type or COD info
- Integration code with barcode
- Piece barcode with barcode
- Piece number (e.g. `Paket: 1/3`)

#### Custom Label Template

You can provide your own HTML template with placeholders:

```php
$customTemplate = '<div class="my-label">
    <h1>{{carrierName}}</h1>
    <p>{{receiverName}} - {{receiverPhone}}</p>
    <p>{{receiverAddress}}</p>
    <p>Entegrasyon: {{integrationCode}}</p>
    <p>Barkod: {{barcodeNumber}}</p>
    <p>Paket: {{pieceNumber}} / {{totalPieces}}</p>
</div>';

$html = $carrier->generateLabels($shipmentData, $customTemplate);
```

Available placeholders: `{{carrierName}}`, `{{date}}`, `{{senderName}}`, `{{receiverName}}`, `{{receiverPhone}}`, `{{receiverAddress}}`, `{{paymentTypeText}}`, `{{codLine}}`, `{{codDisplay}}`, `{{paymentDisplay}}`, `{{integrationCode}}`, `{{barcodeNumber}}`, `{{pieceNumber}}`, `{{totalPieces}}`.

#### Raw Label Data

For full control over rendering, get `LabelData` objects directly:

```php
$labels = $carrier->getLabelData($shipmentData);

foreach ($labels as $label) {
    echo $label->integrationCode;
    echo $label->barcodeNumber;
    echo "{$label->pieceNumber}/{$label->totalPieces}";
}

### Track Shipment

Tracking uses the `GetOrderWithIntegrationCode` SOAP method to look up shipment by integration code.

```php
$response = $carrier->getTrackingStatus([
    'trackingNumber' => 'ORD-001', // your integration code
])->send();

if ($response->isSuccessful()) {
    $info = $response->getTrackingInfo();
    echo $info->trackingNumber;
    echo $info->status->value;  // PRE_TRANSIT, IN_TRANSIT, DELIVERED, etc.
    echo $info->carrier;        // "Aras Kargo"

    foreach ($info->events as $event) {
        echo $event->description;
        echo $event->occurredAt->format('Y-m-d H:i');
        echo $event->location;
    }
}
```

### Cancel Shipment

```php
$response = $carrier->cancelShipment([
    'integrationCode' => 'ORD-001',
])->send();

if ($response->isSuccessful()) {
    echo 'Shipment cancelled';
} else {
    echo $response->getMessage();
}
```

## API Endpoints

| Environment | URL |
|-------------|-----|
| Test | `https://customerservicestest.araskargo.com.tr/arascargoservice/arascargoservice.asmx` |
| Production | `https://customerservices.araskargo.com.tr/arascargoservice/arascargoservice.asmx` |

## SOAP Methods Used

| Operation | SOAP Action | Purpose |
|-----------|-------------|---------|
| `SetOrder` | Create shipment | Registers a new cargo order |
| `GetOrderWithIntegrationCode` | Track shipment | Looks up order by integration code |
| `CancelDispatch` | Cancel shipment | Cancels a pending shipment |

## Error Codes

| Code | Description |
|------|-------------|
| 0 | Success |
| 935 | ReceiverPhone1 must be numeric |
| 937 | Integration code is required |
| 938 | Receiver address is required |
| 939 | Receiver name is required |
| 940 | City name is required |
| 941 | District name is required |
| 1000 | Invalid username or password |
| 1006 | Sender dispatch address not found (invalid SenderAccountAddressId) |
| 70022 | Barcode info missing in piece details |
| 70027 | Barcode already used |
| 70030 | Piece barcodes must be unique |

## Testing

```bash
vendor/bin/pest
```

## License

MIT
