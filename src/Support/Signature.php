<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Support;

use JsonException;

/**
 * Computes Przelewy24 SHA-384 signatures.
 *
 * Every signature is the SHA-384 hash of a JSON object whose keys appear in
 * the exact order mandated by the API, with unescaped slashes and Unicode.
 * Numeric identifiers and amounts must be JSON integers; all other values
 * are strings. The CRC key is always the last element.
 */
final class Signature
{
    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    /**
     * Signature of POST /transaction/register.
     */
    public static function forRegistration(string $sessionId, int $merchantId, int $amount, string $currency, string $crc): string
    {
        return self::hash([
            'sessionId' => $sessionId,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ]);
    }

    /**
     * Signature of PUT /transaction/verify.
     */
    public static function forVerification(string $sessionId, int $orderId, int $amount, string $currency, string $crc): string
    {
        return self::hash([
            'sessionId' => $sessionId,
            'orderId' => $orderId,
            'amount' => $amount,
            'currency' => $currency,
            'crc' => $crc,
        ]);
    }

    /**
     * Signature of the transaction notification sent to urlStatus.
     */
    public static function forTransactionNotification(
        int $merchantId,
        int $posId,
        string $sessionId,
        int $amount,
        int $originAmount,
        string $currency,
        int $orderId,
        int $methodId,
        string $statement,
        string $crc,
    ): string {
        return self::hash([
            'merchantId' => $merchantId,
            'posId' => $posId,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'originAmount' => $originAmount,
            'currency' => $currency,
            'orderId' => $orderId,
            'methodId' => $methodId,
            'statement' => $statement,
            'crc' => $crc,
        ]);
    }

    /**
     * Signature of the refund notification.
     */
    public static function forRefundNotification(
        int $orderId,
        string $sessionId,
        string $refundsUuid,
        int $merchantId,
        int $amount,
        string $currency,
        int $status,
        string $crc,
    ): string {
        return self::hash([
            'orderId' => $orderId,
            'sessionId' => $sessionId,
            'refundsUuid' => $refundsUuid,
            'merchantId' => $merchantId,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'crc' => $crc,
        ]);
    }

    /**
     * Compares a received signature with the expected one in constant time.
     */
    public static function matches(string $expected, string $received): bool
    {
        return hash_equals($expected, strtolower($received));
    }

    /**
     * @param array<string, string|int> $fields
     *
     * @throws JsonException Never in practice: the input consists of scalars only.
     */
    private static function hash(array $fields): string
    {
        return hash('sha384', json_encode($fields, self::JSON_FLAGS));
    }
}
