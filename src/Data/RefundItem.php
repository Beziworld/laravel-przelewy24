<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

use InvalidArgumentException;

/**
 * A single refund of a paid transaction.
 */
final readonly class RefundItem
{
    public function __construct(
        public int $orderId,
        public string $sessionId,
        public int $amount,
        public ?string $description = null,
    ) {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be a positive number of minor units.');
        }

        if ($description !== null && mb_strlen($description) > 35) {
            throw new InvalidArgumentException('Refund description must not exceed 35 characters.');
        }
    }

    /**
     * @return array{orderId: int, sessionId: string, amount: int, description?: string}
     */
    public function toArray(): array
    {
        $item = ['orderId' => $this->orderId, 'sessionId' => $this->sessionId, 'amount' => $this->amount];

        if ($this->description !== null) {
            $item['description'] = $this->description;
        }

        return $item;
    }
}
