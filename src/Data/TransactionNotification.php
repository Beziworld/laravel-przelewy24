<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use Beziworld\Przelewy24\Exceptions\InvalidNotification;
use Beziworld\Przelewy24\Support\Arr;
use Beziworld\Przelewy24\Support\Signature;

/**
 * Payment notification sent by Przelewy24 to the urlStatus endpoint.
 *
 * The notification only states that the customer paid; the payment is not
 * settled until it is confirmed with PUT /transaction/verify.
 */
final readonly class TransactionNotification
{
    public function __construct(
        public int $merchantId,
        public int $posId,
        public string $sessionId,
        public int $amount,
        public int $originAmount,
        public string $currency,
        public int $orderId,
        public int $methodId,
        public string $statement,
        public string $sign,
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidNotification When a required field is missing or has a wrong type.
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            merchantId: Arr::int($payload, 'merchantId') ?? throw InvalidNotification::missingField('merchantId'),
            posId: Arr::int($payload, 'posId') ?? throw InvalidNotification::missingField('posId'),
            sessionId: Arr::string($payload, 'sessionId') ?? throw InvalidNotification::missingField('sessionId'),
            amount: Arr::int($payload, 'amount') ?? throw InvalidNotification::missingField('amount'),
            originAmount: Arr::int($payload, 'originAmount') ?? throw InvalidNotification::missingField('originAmount'),
            currency: Arr::string($payload, 'currency') ?? throw InvalidNotification::missingField('currency'),
            orderId: Arr::int($payload, 'orderId') ?? throw InvalidNotification::missingField('orderId'),
            methodId: Arr::int($payload, 'methodId') ?? throw InvalidNotification::missingField('methodId'),
            statement: Arr::string($payload, 'statement') ?? throw InvalidNotification::missingField('statement'),
            sign: Arr::string($payload, 'sign') ?? throw InvalidNotification::missingField('sign'),
        );
    }

    public function hasValidSignature(string $crc): bool
    {
        return Signature::matches(
            Signature::forTransactionNotification(
                $this->merchantId,
                $this->posId,
                $this->sessionId,
                $this->amount,
                $this->originAmount,
                $this->currency,
                $this->orderId,
                $this->methodId,
                $this->statement,
                $crc,
            ),
            $this->sign,
        );
    }
}
