# Laravel Przelewy24

[![tests](https://github.com/beziworld/laravel-przelewy24/actions/workflows/tests.yml/badge.svg)](https://github.com/beziworld/laravel-przelewy24/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/beziworld/laravel-przelewy24.svg)](https://packagist.org/packages/beziworld/laravel-przelewy24)
[![License](https://img.shields.io/packagist/l/beziworld/laravel-przelewy24.svg)](LICENSE.md)

Laravel integration for the [Przelewy24](https://www.przelewy24.pl) REST API v1: transaction registration,
signed notifications with automatic verification, refunds and payment methods.

[Polska wersja dokumentacji](README.pl.md)

## Features

- Transaction registration with every optional field of the API (cart, channels, time limit, customer data)
- Notification endpoint that checks the SHA-384 signature, the merchant and the source IP, then verifies the
  payment (`PUT /transaction/verify`) and dispatches `TransactionVerified`
- Optional manual verification flow for custom amount checks
- Refunds and signed refund notifications
- Transaction lookup, payment methods, `testAccess`
- Integer amounts in grosze plus an `Amount` helper that converts decimals without float errors
- Sandbox and production, PHP 8.3+, Laravel 12 and 13, PHPStan level 8

## Installation

```bash
composer require beziworld/laravel-przelewy24
php artisan vendor:publish --tag=przelewy24-config   # optional
```

```dotenv
PRZELEWY24_MERCHANT_ID=123456
PRZELEWY24_POS_ID=123456              # usually equal to the merchant id
PRZELEWY24_API_KEY=report-key         # "Klucz do raportów" in the panel
PRZELEWY24_CRC=crc-key
PRZELEWY24_SANDBOX=true
PRZELEWY24_URL_RETURN=https://shop.test/payment/return
```

All credentials are in the Przelewy24 panel under *Moje dane > Dane API i konfiguracja*. Sandbox requires a
separate sandbox account.

## Payment flow

```
Customer → your checkout → Przelewy24::register() → redirect to Przelewy24
Przelewy24 → POST /przelewy24/notification → signature check → PUT /transaction/verify → TransactionVerified
Customer ← redirected back to PRZELEWY24_URL_RETURN
```

The payment is **not** settled until it is verified, so never mark an order as paid on the return URL. The
package registers the notification route and passes its URL as `urlStatus` automatically.

### 1. Register the transaction and redirect

```php
use Beziworld\Przelewy24\Data\TransactionRequest;
use Beziworld\Przelewy24\Exceptions\ApiException;
use Beziworld\Przelewy24\Facades\Przelewy24;
use Beziworld\Przelewy24\Support\Amount;

class CheckoutController
{
    public function pay(Order $order): RedirectResponse
    {
        try {
            $transaction = Przelewy24::register(new TransactionRequest(
                sessionId: $order->uuid,                     // unique per payment attempt, max. 100 chars
                amount: Amount::toMinor($order->total),       // "123.45" → 12345
                description: "Order {$order->number}",
                email: $order->email,
                client: $order->customer_name,
                urlReturn: route('orders.show', $order),
            ));
        } catch (ApiException $e) {
            report($e);

            return back()->withErrors('The payment could not be started.');
        }

        $order->update(['p24_session_id' => $transaction->sessionId]);

        return redirect()->away($transaction->redirectUrl);
    }
}
```

### 2. Mark the order as paid

```php
use Beziworld\Przelewy24\Events\TransactionVerified;

class MarkOrderAsPaid
{
    public function handle(TransactionVerified $event): void
    {
        $notification = $event->notification;
        $order = Order::where('p24_session_id', $notification->sessionId)->firstOrFail();

        if ($order->isPaid()) {
            return;                                       // notifications can be repeated
        }

        if ($notification->amount !== Amount::toMinor($order->total) || $notification->currency !== 'PLN') {
            Log::alert('Przelewy24 amount mismatch', ['order' => $order->id]);

            return;
        }

        $order->markAsPaid(p24OrderId: $notification->orderId);
    }
}
```

`TransactionVerificationFailed` is dispatched when verification fails; the endpoint answers HTTP 500 so that
Przelewy24 retries the notification.

### Manual verification

Set `PRZELEWY24_AUTO_VERIFY=false` to verify inside your own listener, e.g. after checking the amount:

```php
use Beziworld\Przelewy24\Events\TransactionNotificationReceived;

Event::listen(function (TransactionNotificationReceived $event) {
    $order = Order::where('p24_session_id', $event->notification->sessionId)->firstOrFail();

    if ($event->notification->amount === Amount::toMinor($order->total)) {
        Przelewy24::verifyNotification($event->notification);
        $order->markAsPaid();
    }
});
```

## Refunds

```php
use Beziworld\Przelewy24\Data\RefundItem;

$results = Przelewy24::refund(
    items: [new RefundItem(orderId: $order->p24_order_id, sessionId: $order->p24_session_id, amount: 5000, description: 'Return')],
    requestId: (string) Str::uuid(),
    refundsUuid: "refund-{$order->id}",
);
```

The final result arrives as a refund notification at `/przelewy24/refund-notification` (pass its URL as the 4th
argument, `route('przelewy24.refund-notification')`) and is dispatched as `RefundNotificationReceived`:

```php
Event::listen(function (RefundNotificationReceived $event) {
    if ($event->notification->isCompleted()) {
        // refund of $event->notification->amount done
    }
});
```

## Other calls

```php
Przelewy24::testAccess();                          // bool, checks credentials
Przelewy24::transaction($sessionId);               // status, orderId, amount, fee...
Przelewy24::paymentMethods('pl', amount: 12345);   // list<PaymentMethod> with logos for your own picker
```

Restrict the methods shown on the payment page with `channel: Channel::combine(Channel::Cards, Channel::Blik)` or
preselect one with `method: 154`.

## Security

- Every notification must match the SHA-384 signature computed with your CRC key and must be addressed to your
  merchant and POS id.
- Notifications are accepted only from the IP ranges published by Przelewy24 (`PRZELEWY24_VERIFY_IP`). Behind a
  load balancer configure Laravel trusted proxies, otherwise the proxy address is checked.
- The routes use the `api` middleware group (no CSRF). Change `notifications.middleware` if you need more.
- Signatures are checked against the raw request body, so global middleware such as `TrimStrings` cannot break them.
- `TransactionNotificationReceived` is dispatched **before** verification. Never mark an order as paid there unless
  you verify the payment yourself (manual mode); use `TransactionVerified` instead.
- Przelewy24 repeats notifications until the transaction is verified, so make listeners idempotent (key on
  `sessionId` / `orderId`).
- When transactions are registered from queued jobs or console commands, the notification URL is generated from
  `APP_URL`. Set `APP_URL` correctly or configure `PRZELEWY24_URL_STATUS` explicitly.
- Registrations and refunds are never retried automatically after a timeout; only idempotent requests (GET, verify)
  are.

## Exceptions

All exceptions extend `Przelewy24Exception`: `ApiException` (API error or transport, with `httpStatus()` and
`payload()`), `InvalidNotification`, `InvalidConfiguration`.

## Development

```bash
composer install
composer ci   # pint --test, phpstan (level 8), pest
```

## Author

Created and maintained by **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Issues and feature requests: [GitHub issues](https://github.com/beziworld/laravel-przelewy24/issues)
- Security vulnerabilities: see [SECURITY.md](SECURITY.md)

Need help integrating payments in your shop or a custom Laravel project? Get in touch through
[beziworld.eu](https://beziworld.eu).

### Other packages by the author

- [beziworld/laravel-tpay](https://github.com/beziworld/laravel-tpay): Tpay payments and BLIK
- [beziworld/laravel-smsapi](https://github.com/beziworld/laravel-smsapi): SMSAPI messages and notifications
- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): DirectAdmin hosting panel API

## License

MIT. See [LICENSE.md](LICENSE.md). This package is not affiliated with PayPro S.A. / Przelewy24.
