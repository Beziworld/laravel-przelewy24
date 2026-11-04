<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Exceptions;

/**
 * Raised when an incoming notification is malformed, forged or addressed to another merchant.
 */
final class InvalidNotification extends Przelewy24Exception
{
    public static function missingField(string $field): self
    {
        return new self(sprintf('Przelewy24 notification is missing the "%s" field.', $field));
    }

    public static function malformedBody(): self
    {
        return new self('Przelewy24 notification body is not a JSON object.');
    }

    /**
     * Decodes the raw request body.
     *
     * The raw body is used instead of the parsed request input because global
     * middleware such as TrimStrings and ConvertEmptyStringsToNull alter
     * values covered by the signature.
     *
     * @return array<array-key, mixed>
     *
     * @throws self When the body is not a JSON object.
     */
    public static function decode(string $body): array
    {
        try {
            $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw self::malformedBody();
        }

        return is_array($payload) ? $payload : throw self::malformedBody();
    }

    public static function invalidSignature(): self
    {
        return new self('Przelewy24 notification signature is invalid.');
    }

    public static function foreignMerchant(int $merchantId, ?int $posId = null): self
    {
        return new self($posId === null
            ? sprintf('Przelewy24 notification addressed to merchant %d does not belong to this shop.', $merchantId)
            : sprintf('Przelewy24 notification addressed to merchant %d / POS %d does not belong to this shop.', $merchantId, $posId));
    }
}
