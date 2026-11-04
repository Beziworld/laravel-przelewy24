<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Enums;

/**
 * Outcome reported in a refund notification.
 */
enum RefundStatus: int
{
    case Completed = 0;
    case Rejected = 1;
}
