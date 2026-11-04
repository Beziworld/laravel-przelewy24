<?php

declare(strict_types=1);

use Beziworld\Przelewy24\Support\Signature;

it('signs registration with the documented field order and types', function (): void {
    expect(Signature::forRegistration('order/1', 11, 1000, 'PLN', 'crc'))
        ->toBe(hash('sha384', '{"sessionId":"order/1","merchantId":11,"amount":1000,"currency":"PLN","crc":"crc"}'));
});

it('signs verification with the documented field order and types', function (): void {
    expect(Signature::forVerification('order-1', 987654321, 1000, 'PLN', 'crc'))
        ->toBe(hash('sha384', '{"sessionId":"order-1","orderId":987654321,"amount":1000,"currency":"PLN","crc":"crc"}'));
});

it('signs transaction notifications with the documented field order and types', function (): void {
    expect(Signature::forTransactionNotification(11, 22, 'order-1', 1000, 1000, 'PLN', 33, 25, 'p24-ąę', 'crc'))
        ->toBe(hash('sha384', '{"merchantId":11,"posId":22,"sessionId":"order-1","amount":1000,"originAmount":1000,"currency":"PLN","orderId":33,"methodId":25,"statement":"p24-ąę","crc":"crc"}'));
});

it('signs refund notifications with the documented field order and types', function (): void {
    expect(Signature::forRefundNotification(33, 'order-1', 'uuid-1', 11, 500, 'PLN', 0, 'crc'))
        ->toBe(hash('sha384', '{"orderId":33,"sessionId":"order-1","refundsUuid":"uuid-1","merchantId":11,"amount":500,"currency":"PLN","status":0,"crc":"crc"}'));
});

it('compares signatures case-insensitively in constant time', function (): void {
    $sign = Signature::forRegistration('a', 1, 1, 'PLN', 'c');

    expect(Signature::matches($sign, strtoupper($sign)))->toBeTrue()
        ->and(Signature::matches($sign, substr($sign, 1)))->toBeFalse();
});
