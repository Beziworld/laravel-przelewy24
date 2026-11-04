<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Events;

use Beziworld\Przelewy24\Data\TransactionNotification;
use Beziworld\Przelewy24\Exceptions\ApiException;

/**
 * Dispatched when automatic verification of an authentic notification failed.
 *
 * The notification endpoint answers with HTTP 500, so Przelewy24 retries it.
 */
final readonly class TransactionVerificationFailed
{
    public function __construct(
        public TransactionNotification $notification,
        public ApiException $exception,
    ) {}
}
