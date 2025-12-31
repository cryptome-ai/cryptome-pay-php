# Cryptome Pay PHP SDK

> Official PHP SDK for Cryptome Pay - Multi-chain cryptocurrency payment gateway

[![Packagist Version](https://img.shields.io/packagist/v/cryptome-ai/cryptome-pay-php)](https://packagist.org/packages/cryptome-ai/cryptome-pay-php)
[![PHP Version](https://img.shields.io/packagist/php-v/cryptome-ai/cryptome-pay-php)](https://packagist.org/packages/cryptome-ai/cryptome-pay-php)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

## Installation

```bash
composer require cryptome-ai/cryptome-pay-php
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use CryptomePay\Client;

$client = new Client(
    'sk_live_your_api_key',
    'your_api_secret'
);

// Create a payment
$payment = $client->createPayment(
    orderId: 'ORDER_001',
    amount: 100.00,
    notifyUrl: 'https://your-site.com/webhook',
    chainType: 'BSC'
);

echo "Payment URL: " . $payment['data']['payment_url'] . "\n";
echo "Amount: " . $payment['data']['actual_amount'] . " USDT\n";
```

## Features

- **Multi-chain support**: TRC20, BSC, Polygon, Ethereum, Arbitrum
- **Non-custodial**: Payments go directly to your wallet
- **PHP 7.4+**: Compatible with modern PHP versions
- **Type safe**: Full type declarations
- **Laravel ready**: Easy integration with Laravel

## Usage

### Create Payment

```php
<?php

use CryptomePay\Client;
use CryptomePay\ChainType;

$client = new Client(
    'sk_live_xxx',
    'your_secret'
);

$payment = $client->createPayment(
    orderId: 'ORDER_' . time(),
    amount: 100.00,
    notifyUrl: 'https://example.com/webhook',
    redirectUrl: 'https://example.com/success',  // Optional
    chainType: ChainType::BSC
);

if ($payment['status_code'] === 200) {
    $data = $payment['data'];
    echo "Trade ID: " . $data['trade_id'] . "\n";
    echo "Pay " . $data['actual_amount'] . " USDT to " . $data['token'] . "\n";
    echo "Payment URL: " . $data['payment_url'] . "\n";
}
```

### Query Payment

```php
// By trade_id
$result = $client->queryPaymentByTradeId('CP202312271648380592');

// By order_id
$result = $client->queryPaymentByOrderId('ORDER_001');

if ($result['status_code'] === 200) {
    $order = $result['data'];
    echo "Status: " . PaymentStatus::getName($order['status']) . "\n";
}
```

### List Orders

```php
use CryptomePay\PaymentStatus;

$orders = $client->listOrders(
    page: 1,
    pageSize: 20,
    status: PaymentStatus::PAID,
    chainType: 'BSC',
    startDate: '2025-12-01',
    endDate: '2025-12-31'
);

foreach ($orders['data']['list'] as $order) {
    echo $order['order_id'] . ": " . $order['actual_amount'] . " USDT\n";
}
```

### Verify Webhook Signature

```php
<?php

use CryptomePay\Client;
use CryptomePay\PaymentStatus;

$client = new Client(
    $_ENV['CRYPTOMEPAY_API_KEY'],
    $_ENV['CRYPTOMEPAY_API_SECRET']
);

// Get webhook payload
$payload = json_decode(file_get_contents('php://input'), true);

// Verify signature
if (!$client->verifyWebhookSignature($payload)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

// Process payment
if ($payload['status'] === PaymentStatus::PAID) {
    $orderId = $payload['order_id'];
    $txHash = $payload['block_transaction_id'];

    // Fulfill order...
}

echo 'ok';
```

### Sandbox Environment

```php
// Switch to sandbox for testing
$client->useSandbox();

// Create test payment
$payment = $client->createPayment(
    orderId: 'TEST_001',
    amount: 100.01,  // Auto-success amount
    notifyUrl: 'https://webhook.site/test'
);

// Switch back to production
$client->useProduction();
```

## Laravel Integration

### Service Provider

```php
<?php
// app/Providers/CryptomePayServiceProvider.php

namespace App\Providers;

use CryptomePay\Client;
use Illuminate\Support\ServiceProvider;

class CryptomePayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            return new Client(
                config('services.cryptomepay.key'),
                config('services.cryptomepay.secret')
            );
        });
    }
}
```

### Configuration

```php
// config/services.php

return [
    // ...
    'cryptomepay' => [
        'key' => env('CRYPTOMEPAY_API_KEY'),
        'secret' => env('CRYPTOMEPAY_API_SECRET'),
    ],
];
```

### Controller

```php
<?php
// app/Http/Controllers/PaymentController.php

namespace App\Http\Controllers;

use CryptomePay\Client;
use CryptomePay\PaymentStatus;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private Client $cryptomePay
    ) {}

    public function create(Request $request)
    {
        $order = Order::create([
            'user_id' => auth()->id(),
            'amount' => $request->amount,
            'status' => 'pending',
        ]);

        $payment = $this->cryptomePay->createPayment(
            orderId: "ORD_{$order->id}",
            amount: $order->amount,
            notifyUrl: route('webhooks.cryptomepay'),
            redirectUrl: route('orders.show', $order),
            chainType: 'BSC'
        );

        if ($payment['status_code'] !== 200) {
            return back()->withErrors(['payment' => $payment['message']]);
        }

        $order->update([
            'trade_id' => $payment['data']['trade_id'],
        ]);

        return redirect($payment['data']['payment_url']);
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        if (!$this->cryptomePay->verifyWebhookSignature($payload)) {
            return response('Invalid signature', 401);
        }

        $orderId = str_replace('ORD_', '', $payload['order_id']);
        $order = Order::find($orderId);

        if (!$order || $order->status === 'paid') {
            return response('ok');
        }

        if ($payload['status'] === PaymentStatus::PAID) {
            $order->update([
                'status' => 'paid',
                'tx_hash' => $payload['block_transaction_id'],
                'paid_amount' => $payload['actual_amount'],
                'paid_at' => now(),
            ]);

            // Dispatch fulfillment job
            FulfillOrder::dispatch($order);
        }

        return response('ok');
    }
}
```

### Routes

```php
// routes/web.php

Route::post('/api/payments', [PaymentController::class, 'create'])
    ->middleware('auth');

Route::post('/webhooks/cryptomepay', [PaymentController::class, 'webhook'])
    ->name('webhooks.cryptomepay')
    ->withoutMiddleware(['csrf']);
```

## Constants

```php
use CryptomePay\ChainType;
use CryptomePay\PaymentStatus;

// Chain types
ChainType::TRC20      // TRON network
ChainType::BSC        // BNB Smart Chain
ChainType::POLYGON    // Polygon PoS
ChainType::ETH        // Ethereum Mainnet
ChainType::ARBITRUM   // Arbitrum One

// Payment status
PaymentStatus::PENDING  // 1
PaymentStatus::PAID     // 2
PaymentStatus::EXPIRED  // 3

// Helper methods
PaymentStatus::getName(2)     // "Paid"
PaymentStatus::isPaid(2)      // true
ChainType::isValid('BSC')     // true
```

## Error Handling

```php
use CryptomePay\Client;
use CryptomePay\Exception\CryptomePayException;
use CryptomePay\Exception\ApiException;
use CryptomePay\Exception\NetworkException;

$client = new Client('xxx', 'secret');

try {
    $payment = $client->createPayment(
        orderId: 'ORDER_001',
        amount: 100.00,
        notifyUrl: 'https://example.com/webhook'
    );
} catch (NetworkException $e) {
    echo "Network error: " . $e->getMessage() . "\n";
} catch (ApiException $e) {
    echo "API error: " . $e->getMessage() . "\n";
    echo "Status code: " . $e->getStatusCode() . "\n";
    echo "Request ID: " . $e->getRequestId() . "\n";
} catch (CryptomePayException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

## API Reference

### Client

| Method | Description |
|--------|-------------|
| `createPayment($orderId, $amount, $notifyUrl, ...)` | Create a new payment |
| `queryPaymentByTradeId($tradeId)` | Query payment by trade_id |
| `queryPaymentByOrderId($orderId)` | Query payment by order_id |
| `listOrders($page, $pageSize, ...)` | List orders with filters |
| `getMerchantInfo()` | Get merchant information |
| `verifyWebhookSignature($payload)` | Verify webhook signature |
| `useSandbox()` | Switch to sandbox environment |
| `useProduction()` | Switch to production environment |

## Requirements

- PHP 7.4 or higher
- ext-curl
- ext-json

## License

MIT License - see [LICENSE](LICENSE) for details.

## Support

- Documentation: https://docs.cryptomepay.com
- Email: support@cryptomepay.com
- GitHub Issues: https://github.com/cryptome-ai/cryptome-pay-php/issues
