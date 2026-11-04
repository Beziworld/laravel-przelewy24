<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Events;

use Beziworld\Przelewy24\Data\RefundNotification;

/**
 * Dispatched for every authentic refund notification; check $notification->isCompleted().
 */
final readonly class RefundNotificationReceived
{
    public function __construct(
        public RefundNotification $notification,
    ) {}
}
