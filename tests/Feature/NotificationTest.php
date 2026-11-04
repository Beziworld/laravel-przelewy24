<?php

declare(strict_types=1);

use Beziworld\Przelewy24\Enums\RefundStatus;
use Beziworld\Przelewy24\Events\RefundNotificationReceived;
use Beziworld\Przelewy24\Events\TransactionNotificationReceived;
use Beziworld\Przelewy24\Events\TransactionVerificationFailed;
use Beziworld\Przelewy24\Events\TransactionVerified;
use Beziworld\Przelewy24\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Event::fake([
        TransactionNotificationReceived::class,
        TransactionVerified::class,
        TransactionVerificationFailed::class,
        RefundNotificationReceived::class,
    ]);
});

it('verifies an authentic notification and dispatches events', function (): void {
    Http::fake(['*/transaction/verify' => Http::response(['data' => ['status' => 'success'], 'responseCode' => 0])]);

    $this->postJson('/przelewy24/notification', TestCase::signedNotification())
        ->assertOk()
        ->assertJson(['status' => 'verified']);

    Http::assertSent(fn(Request $request): bool => $request->data()['sessionId'] === 'order-1001'
        && $request->data()['orderId'] === 300100200
        && $request->data()['amount'] === 12345);
    Event::assertDispatched(TransactionNotificationReceived::class);
    Event::assertDispatched(TransactionVerified::class, fn(TransactionVerified $event): bool => $event->notification->statement === 'p24-A1-B2-C3');
});

it('rejects notifications with a forged signature', function (): void {
    Http::fake();
    $payload = TestCase::signedNotification();
    $payload['amount'] = 1;

    $this->postJson('/przelewy24/notification', $payload)->assertStatus(400);

    Http::assertNothingSent();
    Event::assertNotDispatched(TransactionNotificationReceived::class);
});

it('rejects notifications addressed to another merchant', function (): void {
    $this->postJson('/przelewy24/notification', TestCase::signedNotification(['merchantId' => 999, 'posId' => 999]))
        ->assertStatus(400)
        ->assertJsonPath('message', fn(string $message): bool => str_contains($message, 'does not belong'));
});

it('rejects incomplete notifications', function (): void {
    $payload = TestCase::signedNotification();
    unset($payload['orderId']);

    $this->postJson('/przelewy24/notification', $payload)->assertStatus(400);
});

it('answers 500 when verification fails so that przelewy24 retries', function (): void {
    Http::fake(['*' => Http::response(['error' => 'Invalid input data', 'code' => 400], 400)]);

    $this->postJson('/przelewy24/notification', TestCase::signedNotification())->assertStatus(500);

    Event::assertDispatched(TransactionVerificationFailed::class);
    Event::assertNotDispatched(TransactionVerified::class);
});

it('leaves verification to the application when auto verify is disabled', function (): void {
    config()->set('przelewy24.notifications.auto_verify', false);
    Http::fake();

    $this->postJson('/przelewy24/notification', TestCase::signedNotification())
        ->assertOk()
        ->assertJson(['status' => 'received']);

    Http::assertNothingSent();
    Event::assertDispatched(TransactionNotificationReceived::class);
});

it('accepts notifications only from published addresses when ip verification is on', function (): void {
    config()->set('przelewy24.notifications.verify_ip', true);
    Http::fake(['*' => Http::response(['data' => ['status' => 'success']])]);

    $this->postJson('/przelewy24/notification', TestCase::signedNotification())->assertForbidden();

    $this->withServerVariables(['REMOTE_ADDR' => '91.220.177.15'])
        ->postJson('/przelewy24/notification', TestCase::signedNotification())
        ->assertOk();
});

it('handles refund notifications', function (): void {
    $payload = [
        'orderId' => 300100200,
        'sessionId' => 'order-1001',
        'merchantId' => TestCase::MERCHANT_ID,
        'requestId' => 'req-1',
        'refundsUuid' => 'uuid-1',
        'amount' => 500,
        'currency' => 'PLN',
        'timestamp' => 1767225600,
        'status' => 0,
    ];
    $payload['sign'] = hash('sha384', sprintf(
        '{"orderId":300100200,"sessionId":"order-1001","refundsUuid":"uuid-1","merchantId":%d,"amount":500,"currency":"PLN","status":0,"crc":"%s"}',
        TestCase::MERCHANT_ID,
        TestCase::CRC,
    ));

    $this->postJson('/przelewy24/refund-notification', $payload)->assertOk();

    Event::assertDispatched(RefundNotificationReceived::class, fn(RefundNotificationReceived $event): bool => $event->notification->isCompleted()
        && $event->notification->status === RefundStatus::Completed
        && $event->notification->processedAt?->getTimestamp() === 1767225600);

    $payload['status'] = 1;
    $this->postJson('/przelewy24/refund-notification', $payload)->assertStatus(400);
});

it('verifies signatures against the raw body, unaffected by trimming middleware', function (): void {
    Http::fake(['*/transaction/verify' => Http::response(['data' => ['status' => 'success']])]);

    $this->postJson('/przelewy24/notification', TestCase::signedNotification(['statement' => ' p24-A1 ', 'sessionId' => 'order 7 ']))
        ->assertOk();

    Event::assertDispatched(TransactionVerified::class, fn(TransactionVerified $event): bool => $event->notification->statement === ' p24-A1 ');
});

it('rejects a body that is not json', function (): void {
    $this->call('POST', '/przelewy24/notification', [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not-json')
        ->assertStatus(400);
});

it('accepts refund notifications with an unknown status code', function (): void {
    $payload = [
        'orderId' => 1, 'sessionId' => 's', 'merchantId' => TestCase::MERCHANT_ID, 'requestId' => 'r',
        'refundsUuid' => 'u', 'amount' => 5, 'currency' => 'PLN', 'timestamp' => 1, 'status' => 7,
    ];
    $payload['sign'] = hash('sha384', sprintf('{"orderId":1,"sessionId":"s","refundsUuid":"u","merchantId":%d,"amount":5,"currency":"PLN","status":7,"crc":"%s"}', TestCase::MERCHANT_ID, TestCase::CRC));

    $this->postJson('/przelewy24/refund-notification', $payload)->assertOk();

    Event::assertDispatched(RefundNotificationReceived::class, fn(RefundNotificationReceived $event): bool => $event->notification->status === null
        && $event->notification->statusCode === 7
        && ! $event->notification->isCompleted());
});
