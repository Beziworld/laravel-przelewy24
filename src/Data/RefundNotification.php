<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use Beziworld\Przelewy24\Enums\RefundStatus;
use Beziworld\Przelewy24\Exceptions\InvalidNotification;
use Beziworld\Przelewy24\Support\Arr;
use Beziworld\Przelewy24\Support\Signature;
use DateTimeImmutable;

/**
 * Notification about the result of a refund request.
 */
final readonly class RefundNotification
{
    public function __construct(
        public int $orderId,
        public string $sessionId,
        public int $merchantId,
        public string $requestId,
        public string $refundsUuid,
        public int $amount,
        public string $currency,
        public ?DateTimeImmutable $processedAt,
        public ?RefundStatus $status,
        public int $statusCode,
        public string $sign,
    ) {}

    /**
     * @param array<array-key, mixed> $payload
     *
     * @throws InvalidNotification When a required field is missing or has a wrong type.
     */
    public static function fromArray(array $payload): self
    {
        $timestamp = Arr::int($payload, 'timestamp');
        $status = Arr::int($payload, 'status') ?? throw InvalidNotification::missingField('status');

        return new self(
            orderId: Arr::int($payload, 'orderId') ?? throw InvalidNotification::missingField('orderId'),
            sessionId: Arr::string($payload, 'sessionId') ?? throw InvalidNotification::missingField('sessionId'),
            merchantId: Arr::int($payload, 'merchantId') ?? throw InvalidNotification::missingField('merchantId'),
            requestId: Arr::string($payload, 'requestId') ?? '',
            refundsUuid: Arr::string($payload, 'refundsUuid') ?? throw InvalidNotification::missingField('refundsUuid'),
            amount: Arr::int($payload, 'amount') ?? throw InvalidNotification::missingField('amount'),
            currency: Arr::string($payload, 'currency') ?? throw InvalidNotification::missingField('currency'),
            processedAt: $timestamp !== null ? (new DateTimeImmutable())->setTimestamp($timestamp) : null,
            status: RefundStatus::tryFrom($status),
            statusCode: $status,
            sign: Arr::string($payload, 'sign') ?? throw InvalidNotification::missingField('sign'),
        );
    }

    public function hasValidSignature(string $crc): bool
    {
        return Signature::matches(
            Signature::forRefundNotification(
                $this->orderId,
                $this->sessionId,
                $this->refundsUuid,
                $this->merchantId,
                $this->amount,
                $this->currency,
                $this->statusCode,
                $crc,
            ),
            $this->sign,
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === RefundStatus::Completed;
    }
}
