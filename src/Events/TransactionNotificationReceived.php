<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Events;

use Beziworld\Przelewy24\Data\TransactionNotification;

/**
 * Dispatched for every authentic payment notification, before automatic verification.
 *
 * When auto verification is disabled, listeners are responsible for
 * comparing the amount with the order and calling Przelewy24::verifyNotification().
 */
final readonly class TransactionNotificationReceived
{
    public function __construct(
        public TransactionNotification $notification,
    ) {}
}
