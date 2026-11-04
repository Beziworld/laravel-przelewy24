<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Exceptions;

/**
 * Raised when the package configuration is incomplete.
 */
final class InvalidConfiguration extends Przelewy24Exception
{
    public static function missing(string $key, string $env): self
    {
        return new self(sprintf('Przelewy24 "%s" is not configured. Set %s in the environment.', $key, $env));
    }

    public static function missingReturnUrl(): self
    {
        return new self('No return URL given. Pass urlReturn to the transaction or set PRZELEWY24_URL_RETURN.');
    }
}
