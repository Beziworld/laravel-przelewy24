<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Enums;

/**
 * Payment channel flags of the "channel" registration field.
 *
 * Flags may be combined with Channel::combine(), e.g.
 * Channel::combine(Channel::Cards, Channel::Blik).
 */
enum Channel: int
{
    case Cards = 1;
    case Transfers = 2;
    case TraditionalTransfer = 4;
    case All24x7 = 16;
    case Prepayment = 32;
    case PayByLinkOnly = 64;
    case Installments = 128;
    case Wallets = 256;
    case CardsOnly = 4096;
    case Blik = 8192;
    case AllExceptBlik = 16384;

    public static function combine(self ...$channels): int
    {
        return array_reduce($channels, static fn(int $carry, self $channel): int => $carry | $channel->value, 0);
    }
}
