<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Data;

/**
 * A transaction registered in Przelewy24, ready for redirecting the customer.
 */
final readonly class RegisteredTransaction
{
    public function __construct(
        public string $sessionId,
        public string $token,
        public string $redirectUrl,
    ) {}
}
