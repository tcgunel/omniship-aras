<?php

declare(strict_types=1);

namespace Omniship\Aras\Message;

use Omniship\Common\Message\AbstractResponse;
use Omniship\Common\Message\CancelResponse;

class CancelShipmentResponse extends AbstractResponse implements CancelResponse
{
    public function isSuccessful(): bool
    {
        return $this->getResultCode() === '0';
    }

    public function isCancelled(): bool
    {
        return $this->isSuccessful();
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

    private function getResultCode(): ?string
    {
        if (!is_array($this->data) || !isset($this->data['ResultCode'])) {
            return null;
        }

        return (string) $this->data['ResultCode'];
    }
}
