<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Facades;

use Beziworld\Przelewy24\Przelewy24Client;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool testAccess()
 * @method static \Beziworld\Przelewy24\Data\RegisteredTransaction register(\Beziworld\Przelewy24\Data\TransactionRequest $request)
 * @method static void verify(string $sessionId, int $orderId, int $amount, ?string $currency = null)
 * @method static void verifyNotification(\Beziworld\Przelewy24\Data\TransactionNotification $notification)
 * @method static \Beziworld\Przelewy24\Data\Transaction transaction(string $sessionId)
 * @method static list<\Beziworld\Przelewy24\Data\RefundResult> refund(list<\Beziworld\Przelewy24\Data\RefundItem> $items, string $requestId, string $refundsUuid, ?string $urlStatus = null)
 * @method static list<\Beziworld\Przelewy24\Data\PaymentMethod> paymentMethods(string $language = 'pl', ?int $amount = null, ?string $currency = null)
 * @method static string redirectUrl(string $token)
 * @method static \Beziworld\Przelewy24\Przelewy24Config config()
 *
 * @see Przelewy24Client
 */
final class Przelewy24 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Przelewy24Client::class;
    }
}
