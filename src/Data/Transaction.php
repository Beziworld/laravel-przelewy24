<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use Beziworld\Przelewy24\Enums\TransactionStatus;
use Beziworld\Przelewy24\Support\Arr;

/**
 * Transaction details returned by GET /transaction/by/sessionId/{sessionId}.
 */
final readonly class Transaction
{
    public function __construct(
        public string $sessionId,
        public ?int $orderId,
        public ?TransactionStatus $status,
        public int $amount,
        public string $currency,
        public ?string $statement = null,
        public ?string $date = null,
        public ?string $dateOfTransaction = null,
        public ?string $clientEmail = null,
        public ?string $clientName = null,
        public ?int $paymentMethod = null,
        public ?string $description = null,
        public ?int $batchId = null,
        public ?string $fee = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $status = Arr::int($data, 'status');

        return new self(
            sessionId: Arr::string($data, 'sessionId') ?? '',
            orderId: Arr::int($data, 'orderId'),
            status: $status !== null ? TransactionStatus::tryFrom($status) : null,
            amount: Arr::int($data, 'amount') ?? 0,
            currency: Arr::string($data, 'currency') ?? '',
            statement: Arr::string($data, 'statement'),
            date: Arr::string($data, 'date'),
            dateOfTransaction: Arr::string($data, 'dateOfTransaction'),
            clientEmail: Arr::string($data, 'clientEmail'),
            clientName: Arr::string($data, 'clientName'),
            paymentMethod: Arr::int($data, 'paymentMethod'),
            description: Arr::string($data, 'description'),
            batchId: Arr::int($data, 'batchId'),
            fee: Arr::string($data, 'fee'),
        );
    }

    public function isPaid(): bool
    {
        return $this->status?->isPaid() ?? false;
    }
}
