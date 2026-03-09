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

#### Barcodes

The `barcodes` parameter accepts an array of barcode strings, one per piece. Barcodes are matched to pieces by index. If you have 2 packages with quantity 1 each, provide 2 barcodes:

```php
'barcodes' => ['BARCODE-001', 'BARCODE-002'],
```

If a package has `quantity: 3`, you need 3 barcodes for that package's pieces.

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
| 70022 | Barcode info missing in piece details |
| 70027 | Barcode already used |
| 70030 | Piece barcodes must be unique |

## Testing

```bash
vendor/bin/pest
```

## License

MIT
