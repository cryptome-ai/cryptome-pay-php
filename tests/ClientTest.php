<?php

declare(strict_types=1);

namespace CryptomePay\Tests;

use CryptomePay\Client;
use CryptomePay\ChainType;
use CryptomePay\PaymentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Client class.
 */
class ClientTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client(
            'sk_test_key',
            'test_secret'
        );
    }

    public function testInit(): void
    {
        $this->assertEquals('sk_test_key', $this->client->getApiKey());
        $this->assertEquals(Client::PRODUCTION_URL, $this->client->getBaseUrl());
    }

    public function testCustomBaseUrl(): void
    {
        $client = new Client(
            'key',
            'secret',
            'https://custom.example.com/api/v1/'
        );
        $this->assertEquals('https://custom.example.com/api/v1', $client->getBaseUrl());
    }

    public function testUseSandbox(): void
    {
        $result = $this->client->useSandbox();
        $this->assertEquals(Client::SANDBOX_URL, $this->client->getBaseUrl());
        $this->assertSame($this->client, $result);
    }

    public function testUseProduction(): void
    {
        $this->client->useSandbox();
        $result = $this->client->useProduction();
        $this->assertEquals(Client::PRODUCTION_URL, $this->client->getBaseUrl());
        $this->assertSame($this->client, $result);
    }

    public function testGenerateSignature(): void
    {
        $params = [
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
            'notify_url' => 'https://example.com/webhook',
        ];

        $signature1 = $this->client->generateSignature($params);
        $signature2 = $this->client->generateSignature($params);

        $this->assertEquals(32, strlen($signature1));
        $this->assertEquals($signature1, $signature2);
    }

    public function testSignatureOrderIndependent(): void
    {
        $params1 = [
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
            'notify_url' => 'https://example.com',
        ];

        $params2 = [
            'notify_url' => 'https://example.com',
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
        ];

        $this->assertEquals(
            $this->client->generateSignature($params1),
            $this->client->generateSignature($params2)
        );
    }

    public function testSignatureExcludesEmpty(): void
    {
        $params1 = [
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
        ];

        $params2 = [
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
            'chain_type' => '',
        ];

        $this->assertEquals(
            $this->client->generateSignature($params1),
            $this->client->generateSignature($params2)
        );
    }

    public function testVerifyValidSignature(): void
    {
        $params = [
            'trade_id' => 'CP123',
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
            'actual_amount' => '15.6250',
            'token' => '0xabc',
            'chain_type' => 'BSC',
            'block_transaction_id' => '0x123',
            'status' => '2',
        ];

        $validSignature = $this->client->generateSignature($params);

        $payload = array_merge($params, ['signature' => $validSignature]);

        $this->assertTrue($this->client->verifyWebhookSignature($payload));
    }

    public function testVerifyInvalidSignature(): void
    {
        $payload = [
            'trade_id' => 'CP123',
            'order_id' => 'ORDER_001',
            'amount' => '100.00',
            'signature' => 'invalid_signature_here',
        ];

        $this->assertFalse($this->client->verifyWebhookSignature($payload));
    }

    public function testVerifyMissingSignature(): void
    {
        $payload = [
            'trade_id' => 'CP123',
            'order_id' => 'ORDER_001',
        ];

        $this->assertFalse($this->client->verifyWebhookSignature($payload));
    }
}

/**
 * Tests for ChainType class.
 */
class ChainTypeTest extends TestCase
{
    public function testChainTypes(): void
    {
        $this->assertEquals('TRC20', ChainType::TRC20);
        $this->assertEquals('BSC', ChainType::BSC);
        $this->assertEquals('POLYGON', ChainType::POLYGON);
        $this->assertEquals('ETH', ChainType::ETH);
        $this->assertEquals('ARBITRUM', ChainType::ARBITRUM);
    }

    public function testAllChainTypes(): void
    {
        $all = ChainType::all();
        $this->assertCount(5, $all);
        $this->assertContains('TRC20', $all);
        $this->assertContains('BSC', $all);
    }

    public function testIsValid(): void
    {
        $this->assertTrue(ChainType::isValid('BSC'));
        $this->assertTrue(ChainType::isValid('TRC20'));
        $this->assertFalse(ChainType::isValid('INVALID'));
    }
}

/**
 * Tests for PaymentStatus class.
 */
class PaymentStatusTest extends TestCase
{
    public function testPaymentStatus(): void
    {
        $this->assertEquals(1, PaymentStatus::PENDING);
        $this->assertEquals(2, PaymentStatus::PAID);
        $this->assertEquals(3, PaymentStatus::EXPIRED);
    }

    public function testGetName(): void
    {
        $this->assertEquals('Pending', PaymentStatus::getName(1));
        $this->assertEquals('Paid', PaymentStatus::getName(2));
        $this->assertEquals('Expired', PaymentStatus::getName(3));
        $this->assertEquals('Unknown', PaymentStatus::getName(99));
    }

    public function testStatusChecks(): void
    {
        $this->assertTrue(PaymentStatus::isPending(1));
        $this->assertFalse(PaymentStatus::isPending(2));

        $this->assertTrue(PaymentStatus::isPaid(2));
        $this->assertFalse(PaymentStatus::isPaid(1));

        $this->assertTrue(PaymentStatus::isExpired(3));
        $this->assertFalse(PaymentStatus::isExpired(1));
    }
}
