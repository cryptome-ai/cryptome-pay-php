<?php

declare(strict_types=1);

namespace CryptomePay\Exception;

/**
 * Exception for API errors.
 *
 * @package CryptomePay\Exception
 */
class ApiException extends CryptomePayException
{
    private int $statusCode;

    /**
     * Create a new ApiException.
     *
     * @param string      $message    Error message
     * @param int         $statusCode HTTP status code
     * @param string|null $requestId  Request ID
     */
    public function __construct(string $message, int $statusCode = 0, ?string $requestId = null)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->requestId = $requestId;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
