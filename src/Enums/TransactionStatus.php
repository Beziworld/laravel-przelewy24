<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Enums;

/**
 * Transaction status returned by GET /transaction/by/sessionId.
 */
enum TransactionStatus: int
{
    case NoPayment = 0;
    case AdvancePayment = 1;
    case Completed = 2;
    case Returned = 3;

    public function isPaid(): bool
    {
        return $this === self::Completed;
    }
}
