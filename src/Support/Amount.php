<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Support;

use InvalidArgumentException;

/**
 * Converts between decimal amounts and the integer minor units used by Przelewy24.
 *
 * Conversion is performed on strings to avoid binary floating point errors
 * (e.g. 19.99 * 100 = 1998.9999999999998).
 */
final class Amount
{
    /**
     * Converts a decimal amount such as "123.45" or 123.45 into minor units (12345).
     *
     * @throws InvalidArgumentException When the value is negative or has more than two decimal places.
     */
    public static function toMinor(string|int|float $amount): int
    {
        if (is_int($amount)) {
            return self::guardNonNegative($amount * 100);
        }

        $normalized = is_float($amount)
            ? number_format($amount, 2, '.', '')
            : str_replace([' ', ','], ['', '.'], trim($amount));

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid non-negative amount with at most two decimal places.', (string) $amount));
        }

        return self::guardNonNegative(((int) $matches[1]) * 100 + (int) str_pad($matches[2] ?? '0', 2, '0'));
    }

    /**
     * Formats minor units as a decimal string, e.g. 12345 becomes "123.45".
     */
    public static function toDecimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return sprintf('%s%d.%02d', $sign, intdiv($minor, 100), $minor % 100);
    }

    private static function guardNonNegative(int $minor): int
    {
        if ($minor < 0) {
            throw new InvalidArgumentException('Amount must not be negative.');
        }

        return $minor;
    }
}
