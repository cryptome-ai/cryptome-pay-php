<?php

declare(strict_types=1);

namespace CryptomePay\Exception;

use Exception;

/**
 * Base exception for Cryptome Pay SDK.
 *
 * @package CryptomePay\Exception
 */
class CryptomePayException extends Exception
{
    protected ?string $requestId = null;

    /**
     * Get the request ID if available.
     *
     * @return string|null
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Set the request ID.
     *
     * @param string|null $requestId
     *
     * @return self
     */
    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }
}
