<?php

declare(strict_types=1);

namespace CryptomePay;

use CryptomePay\Exception\CryptomePayException;
use CryptomePay\Exception\ApiException;
use CryptomePay\Exception\NetworkException;

/**
 * Cryptome Pay API Client
 *
 * @package CryptomePay
 */
class Client
{
    public const VERSION = '1.0.0';

    public const PRODUCTION_URL = 'https://api.cryptomepay.com/api/v1';
    public const SANDBOX_URL = 'https://sandbox.cryptomepay.com/api/v1';

    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private int $timeout;

    /**
     * Create a new Client instance.
     *
     * @param string $apiKey    Your API key
     * @param string $apiSecret Your API secret
     * @param string $baseUrl   Base URL for API (optional)
     * @param int    $timeout   Request timeout in seconds (default: 30)
     */
    public function __construct(
        string $apiKey,
        string $apiSecret,
        string $baseUrl = self::PRODUCTION_URL,
        int $timeout = 30
    ) {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
    }

    /**
     * Switch to sandbox environment.
     *
     * @return self
     */
    public function useSandbox(): self
    {
        $this->baseUrl = self::SANDBOX_URL;
        return $this;
    }

    /**
     * Switch to production environment.
     *
     * @return self
     */
    public function useProduction(): self
    {
        $this->baseUrl = self::PRODUCTION_URL;
        return $this;
    }

    /**
     * Create a new payment order.
     *
     * @param string      $orderId     Your unique order ID
     * @param float       $amount      Payment amount in USD
     * @param string      $notifyUrl   Webhook notification URL
     * @param string|null $redirectUrl Redirect URL after payment (optional)
     * @param string      $chainType   Blockchain network (default: TRC20)
     *
     * @return array API response
     * @throws CryptomePayException
     */
    public function createPayment(
        string $orderId,
        float $amount,
        string $notifyUrl,
        ?string $redirectUrl = null,
        string $chainType = ChainType::TRC20
    ): array {
        $params = [
            'order_id' => $orderId,
            'amount' => number_format($amount, 2, '.', ''),
            'notify_url' => $notifyUrl,
            'chain_type' => $chainType,
        ];

        if ($redirectUrl !== null) {
            $params['redirect_url'] = $redirectUrl;
        }

        $params['signature'] = $this->generateSignature($params);

        return $this->request('POST', '/order/create-transaction', $params);
    }

    /**
     * Query payment by trade ID.
     *
     * @param string $tradeId The trade ID from createPayment response
     *
     * @return array API response
     * @throws CryptomePayException
     */
    public function queryPaymentByTradeId(string $tradeId): array
    {
        return $this->request('GET', '/order/query', ['trade_id' => $tradeId]);
    }

    /**
     * Query payment by order ID.
     *
     * @param string $orderId Your order ID
     *
     * @return array API response
     * @throws CryptomePayException
     */
    public function queryPaymentByOrderId(string $orderId): array
    {
        return $this->request('GET', '/order/query', ['order_id' => $orderId]);
    }

    /**
     * List orders with optional filters.
     *
     * @param int         $page      Page number (default: 1)
     * @param int         $pageSize  Items per page (default: 20)
     * @param int|null    $status    Filter by status (optional)
     * @param string|null $chainType Filter by chain type (optional)
     * @param string|null $startDate Filter by start date YYYY-MM-DD (optional)
     * @param string|null $endDate   Filter by end date YYYY-MM-DD (optional)
     *
     * @return array API response
     * @throws CryptomePayException
     */
    public function listOrders(
        int $page = 1,
        int $pageSize = 20,
        ?int $status = null,
        ?string $chainType = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $params = [
            'page' => $page,
            'page_size' => $pageSize,
        ];

        if ($status !== null) {
            $params['status'] = $status;
        }
        if ($chainType !== null) {
            $params['chain_type'] = $chainType;
        }
        if ($startDate !== null) {
            $params['start_date'] = $startDate;
        }
        if ($endDate !== null) {
            $params['end_date'] = $endDate;
        }

        return $this->request('GET', '/merchant/orders', $params);
    }

    /**
     * Get merchant information.
     *
     * @return array API response
     * @throws CryptomePayException
     */
    public function getMerchantInfo(): array
    {
        return $this->request('GET', '/merchant/info');
    }

    /**
     * Verify webhook signature.
     *
     * @param array $payload The webhook payload
     *
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(array $payload): bool
    {
        if (!isset($payload['signature'])) {
            return false;
        }

        $receivedSignature = $payload['signature'];
        $expectedSignature = $this->generateSignature($payload);

        return hash_equals($expectedSignature, $receivedSignature);
    }

    /**
     * Generate MD5 signature for parameters.
     *
     * @param array $params Parameters to sign
     *
     * @return string MD5 signature
     */
    public function generateSignature(array $params): string
    {
        // Remove signature from params if present
        unset($params['signature']);

        // Filter out empty values
        $filtered = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });

        // Sort by key
        ksort($filtered);

        // Build query string
        $queryString = http_build_query($filtered);

        // Append secret and generate MD5
        return md5($queryString . $this->apiSecret);
    }

    /**
     * Make HTTP request to API.
     *
     * @param string     $method   HTTP method (GET, POST, PUT)
     * @param string     $endpoint API endpoint
     * @param array|null $data     Request data
     *
     * @return array Response data
     * @throws CryptomePayException
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: CryptomePay-PHP/' . self::VERSION,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'GET') {
            if ($data !== null) {
                $url .= '?' . http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_URL, $url);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        if ($errno !== 0) {
            throw new NetworkException("cURL error: {$error}", $errno);
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Invalid JSON response', $httpCode);
        }

        return $result;
    }

    /**
     * Get the current base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the API key.
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}
