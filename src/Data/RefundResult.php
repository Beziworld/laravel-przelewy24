<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use Beziworld\Przelewy24\Support\Arr;

/**
 * Per-item outcome of POST /transaction/refund.
 */
final readonly class RefundResult
{
    public function __construct(
        public int $orderId,
        public string $sessionId,
        public int $amount,
        public bool $accepted,
        public ?string $message = null,
        public ?string $description = null,
    ) {}

    /**
     * @param array<array-key, mixed> $item
     */
    public static function fromArray(array $item): self
    {
        return new self(
            orderId: Arr::int($item, 'orderId') ?? 0,
            sessionId: Arr::string($item, 'sessionId') ?? '',
            amount: Arr::int($item, 'amount') ?? 0,
            accepted: Arr::bool($item, 'status') ?? false,
            message: Arr::string($item, 'message'),
            description: Arr::string($item, 'description'),
        );
    }
}
