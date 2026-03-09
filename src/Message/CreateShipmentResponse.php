<?php

declare(strict_types=1);

namespace Omniship\Aras\Message;

use Omniship\Common\Label;
use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\ShipmentResponse;

class CreateShipmentResponse extends AbstractResponse implements ShipmentResponse
{
    public function isSuccessful(): bool
    {
        return $this->getResultCode() === '0';
    }

    public function getMessage(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['ResultMessage'])) {
            return null;
        }

        return (string) $this->data['ResultMessage'];
    }

    public function getCode(): ?string
    {
        return $this->getResultCode();
    }

    public function getShipmentId(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['InvoiceKey'])) {
            return null;
        }

        return (string) $this->data['InvoiceKey'];
    }

    public function getTrackingNumber(): ?string
    {
        // For Aras, the integration code (customer reference) serves as the tracking number
        $request = $this->getRequest();

        if ($request instanceof CreateShipmentRequest) {
            return $request->getIntegrationCode();
        }

        return null;
    }

    public function getBarcode(): ?string
    {
        // InvoiceKey serves as the barcode in Aras
        return $this->getShipmentId();
    }

    public function getLabel(): ?Label
    {
        return null;
    }

    public function getTotalCharge(): ?float
    {
        return null;
    }

    public function getCurrency(): ?string
    {
        return null;
    }

    private function getResultCode(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['ResultCode'])) {
            return null;
        }

        return (string) $this->data['ResultCode'];
    }
}
