<?php

declare(strict_types=1);

use Beziworld\Przelewy24\Data\RefundItem;
use Beziworld\Przelewy24\Data\TransactionRequest;
use Beziworld\Przelewy24\Enums\Channel;
use Beziworld\Przelewy24\Enums\TransactionStatus;
use Beziworld\Przelewy24\Exceptions\ApiException;
use Beziworld\Przelewy24\Exceptions\InvalidConfiguration;
use Beziworld\Przelewy24\Facades\Przelewy24;
use Beziworld\Przelewy24\Support\Signature;
use Beziworld\Przelewy24\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const P24_API = 'https://sandbox.przelewy24.pl/api/v1';

it('registers a transaction', function (): void {
    Http::fake([P24_API . '/transaction/register' => Http::response(['data' => ['token' => 'TOKEN-123'], 'responseCode' => 0])]);

    $transaction = Przelewy24::register(new TransactionRequest(
        sessionId: 'order-1001',
        amount: 12345,
        description: 'Zamówienie #1001',
        email: 'jan@example.com',
        client: 'Jan Kowalski',
        channel: Channel::combine(Channel::Cards, Channel::Blik),
        cart: [['sellerId' => 'shop', 'sellerCategory' => 'books']],
    ));

    expect($transaction->token)->toBe('TOKEN-123')
        ->and($transaction->sessionId)->toBe('order-1001')
        ->and($transaction->redirectUrl)->toBe('https://sandbox.przelewy24.pl/trnRequest/TOKEN-123');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Basic ' . base64_encode(TestCase::MERCHANT_ID . ':api-key'))
            && $body['merchantId'] === TestCase::MERCHANT_ID
            && $body['posId'] === TestCase::MERCHANT_ID
            && $body['amount'] === 12345
            && $body['currency'] === 'PLN'
            && $body['country'] === 'PL'
            && $body['language'] === 'pl'
            && $body['urlReturn'] === 'https://shop.test/thank-you'
            && $body['urlStatus'] === 'http://localhost/przelewy24/notification'
            && $body['client'] === 'Jan Kowalski'
            && $body['channel'] === 8193
            && $body['cart'] === [['sellerId' => 'shop', 'sellerCategory' => 'books']]
            && ! array_key_exists('address', $body)
            && $body['sign'] === Signature::forRegistration('order-1001', TestCase::MERCHANT_ID, 12345, 'PLN', TestCase::CRC);
    });
});

it('prefers the configured status url', function (): void {
    config()->set('przelewy24.url_status', 'https://shop.test/custom-status');
    app()->forgetInstance(\Beziworld\Przelewy24\Przelewy24Config::class);
    app()->forgetInstance(\Beziworld\Przelewy24\Przelewy24Client::class);
    Przelewy24::clearResolvedInstances();
    Http::fake(['*' => Http::response(['data' => ['token' => 'T']])]);

    Przelewy24::register(new TransactionRequest('s', 100, 'd', 'e@x.pl'));

    Http::assertSent(fn(Request $request): bool => $request->data()['urlStatus'] === 'https://shop.test/custom-status');
});

it('requires a return url', function (): void {
    config()->set('przelewy24.url_return', null);
    app()->forgetInstance(\Beziworld\Przelewy24\Przelewy24Config::class);
    app()->forgetInstance(\Beziworld\Przelewy24\Przelewy24Client::class);
    Przelewy24::clearResolvedInstances();

    expect(fn() => Przelewy24::register(new TransactionRequest('s', 100, 'd', 'e@x.pl')))
        ->toThrow(InvalidConfiguration::class);
});

it('maps registration errors', function (): void {
    Http::fake(['*' => Http::response(['error' => 'Invalid input data', 'code' => 400], 400)]);

    expect(fn() => Przelewy24::register(new TransactionRequest('s', 100, 'd', 'e@x.pl')))
        ->toThrow(ApiException::class, 'Przelewy24 error: Invalid input data');
});

it('verifies a transaction', function (): void {
    Http::fake([P24_API . '/transaction/verify' => Http::response(['data' => ['status' => 'success'], 'responseCode' => 0])]);

    Przelewy24::verify('order-1001', 300100200, 12345);

    Http::assertSent(fn(Request $request): bool => $request->method() === 'PUT'
        && $request->data()['orderId'] === 300100200
        && $request->data()['sign'] === Signature::forVerification('order-1001', 300100200, 12345, 'PLN', TestCase::CRC));
});

it('fails verification when the api does not confirm it', function (): void {
    Http::fake(['*' => Http::response(['data' => ['status' => 'error']])]);

    expect(fn() => Przelewy24::verify('s', 1, 100))->toThrow(ApiException::class, 'did not confirm');
});

it('reads transaction details', function (): void {
    Http::fake([P24_API . '/transaction/by/sessionId/order%2F1' => Http::response(['data' => [
        'sessionId' => 'order/1', 'orderId' => 300100200, 'status' => 2, 'amount' => 12345, 'currency' => 'PLN',
        'clientEmail' => 'jan@example.com', 'paymentMethod' => 25, 'fee' => '1.23',
    ], 'responseCode' => 0])]);

    $transaction = Przelewy24::transaction('order/1');

    expect($transaction->status)->toBe(TransactionStatus::Completed)
        ->and($transaction->isPaid())->toBeTrue()
        ->and($transaction->orderId)->toBe(300100200)
        ->and($transaction->fee)->toBe('1.23');
});

it('requests refunds and maps per-item results', function (): void {
    Http::fake([P24_API . '/transaction/refund' => Http::response(['data' => [
        ['orderId' => 1, 'sessionId' => 's1', 'amount' => 100, 'description' => 'zwrot', 'status' => true, 'message' => 'success'],
    ], 'responseCode' => 0], 201)]);

    $results = Przelewy24::refund([new RefundItem(1, 's1', 100, 'zwrot')], 'req-1', 'uuid-1', 'https://shop.test/refunds');

    expect($results)->toHaveCount(1)
        ->and($results[0]->accepted)->toBeTrue()
        ->and($results[0]->message)->toBe('success');

    Http::assertSent(fn(Request $request): bool => $request->data() === [
        'requestId' => 'req-1',
        'refunds' => [['orderId' => 1, 'sessionId' => 's1', 'amount' => 100, 'description' => 'zwrot']],
        'refundsUuid' => 'uuid-1',
        'urlStatus' => 'https://shop.test/refunds',
    ]);
});

it('reports rejected refunds with their reasons', function (): void {
    Http::fake(['*' => Http::response(['error' => [
        ['orderId' => 1, 'status' => false, 'message' => 'Insufficient funds available'],
    ], 'code' => 409], 409)]);

    expect(fn() => Przelewy24::refund([new RefundItem(1, 's', 1)], 'r', 'u'))
        ->toThrow(ApiException::class, 'Insufficient funds available');
});

it('requires at least one refund item', function (): void {
    expect(fn() => Przelewy24::refund([], 'r', 'u'))->toThrow(InvalidArgumentException::class);
});

it('lists payment methods', function (): void {
    Http::fake([P24_API . '/payment/methods/pl?amount=1000&currency=PLN' => Http::response(['data' => [
        ['id' => 154, 'name' => 'BLIK', 'group' => 'Blik', 'status' => true, 'imgUrl' => 'https://x/blik.png', 'mobile' => true],
    ]])]);

    $methods = Przelewy24::paymentMethods('pl', 1000, 'pln');

    expect($methods[0]->id)->toBe(154)
        ->and($methods[0]->available)->toBeTrue()
        ->and($methods[0]->imageUrl)->toBe('https://x/blik.png');
});

it('tests api access', function (): void {
    Http::fake(['*' => Http::sequence()
        ->push(['data' => true, 'error' => ''])
        ->push(['error' => 'Incorrect authentication', 'code' => 401], 401)
        ->push(['error' => 'Unknown error'], 500),
    ]);

    expect(Przelewy24::testAccess())->toBeTrue()
        ->and(Przelewy24::testAccess())->toBeFalse()
        ->and(fn() => Przelewy24::testAccess())->toThrow(ApiException::class);
});

it('maps non-json responses', function (): void {
    Http::fake(['*' => Http::sequence()->push('<html>', 502)->push('', 200)]);

    expect(fn() => Przelewy24::transaction('s'))->toThrow(ApiException::class, 'HTTP status 502')
        ->and(fn() => Przelewy24::transaction('s'))->toThrow(ApiException::class, 'unexpected response');
});

it('retries and then wraps connection errors', function (): void {
    $attempts = 0;
    Http::fake(function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('refused');
    });

    expect(fn() => Przelewy24::transaction('s'))->toThrow(ApiException::class, 'Could not connect to Przelewy24: refused')
        ->and($attempts)->toBe(2);
});

it('never retries registrations that may already have been processed', function (): void {
    $attempts = 0;
    Http::fake(function () use (&$attempts): never {
        $attempts++;

        throw new ConnectionException('timeout');
    });

    expect(fn() => Przelewy24::register(new TransactionRequest('s', 100, 'd', 'e@x.pl')))->toThrow(ApiException::class)
        ->and($attempts)->toBe(1);
});
