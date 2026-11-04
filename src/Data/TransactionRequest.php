<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use InvalidArgumentException;

/**
 * Input of POST /transaction/register.
 *
 * Amounts are integers in minor units (grosze); use Support\Amount::toMinor()
 * to convert decimal values. Currency, country, language and URLs default to
 * the package configuration when left null.
 */
final readonly class TransactionRequest
{
    /**
     * @param int|null $channel Bitmask built with Enums\Channel::combine().
     * @param int|null $timeLimit Minutes to complete the payment, 0 disables the limit (0-99).
     * @param list<array<string, mixed>> $cart Basket items as defined by the API ("sellerId", "sellerCategory", "name", ...).
     * @param array<string, mixed> $additional The "additional" object (shipping details, PSU data).
     */
    public function __construct(
        public string $sessionId,
        public int $amount,
        public string $description,
        public string $email,
        public ?string $currency = null,
        public ?string $country = null,
        public ?string $language = null,
        public ?string $urlReturn = null,
        public ?string $urlStatus = null,
        public ?string $client = null,
        public ?string $address = null,
        public ?string $zip = null,
        public ?string $city = null,
        public ?string $phone = null,
        public ?int $method = null,
        public ?int $channel = null,
        public ?int $timeLimit = null,
        public ?bool $waitForResult = null,
        public ?bool $regulationAccept = null,
        public ?int $shipping = null,
        public ?string $transferLabel = null,
        public ?string $methodRefId = null,
        public array $cart = [],
        public array $additional = [],
    ) {
        if ($sessionId === '' || mb_strlen($sessionId) > 100) {
            throw new InvalidArgumentException('Session id must contain between 1 and 100 characters.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number of minor units.');
        }

        if ($timeLimit !== null && ($timeLimit < 0 || $timeLimit > 99)) {
            throw new InvalidArgumentException('Time limit must be between 0 and 99 minutes.');
        }
    }

    /**
     * Optional fields that are sent only when set.
     *
     * @return array<string, mixed>
     */
    public function optionalFields(): array
    {
        $fields = [
            'client' => $this->client,
            'address' => $this->address,
            'zip' => $this->zip,
            'city' => $this->city,
            'phone' => $this->phone,
            'method' => $this->method,
            'channel' => $this->channel,
            'timeLimit' => $this->timeLimit,
            'waitForResult' => $this->waitForResult,
            'regulationAccept' => $this->regulationAccept,
            'shipping' => $this->shipping,
            'transferLabel' => $this->transferLabel,
            'methodRefId' => $this->methodRefId,
            'cart' => $this->cart === [] ? null : $this->cart,
            'additional' => $this->additional === [] ? null : $this->additional,
        ];

        return array_filter($fields, static fn(mixed $value): bool => $value !== null);
    }
}
