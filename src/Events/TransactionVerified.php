<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Events;

use Beziworld\Przelewy24\Data\TransactionNotification;

/**
 * Dispatched after Przelewy24 confirmed the transaction; the order can be marked as paid.
 *
 * Przelewy24 may repeat notifications, so listeners must be idempotent and
 * must compare $notification->amount and currency with the stored order.
 */
final readonly class TransactionVerified
{
    public function __construct(
        public TransactionNotification $notification,
    ) {}
}
