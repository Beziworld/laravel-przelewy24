<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Tests;

use Beziworld\Przelewy24\Przelewy24ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const int MERCHANT_ID = 112233;

    public const string CRC = 'crc-secret';

    protected function getPackageProviders($app): array
    {
        return [Przelewy24ServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('przelewy24.merchant_id', (string) self::MERCHANT_ID);
        $app['config']->set('przelewy24.api_key', 'api-key');
        $app['config']->set('przelewy24.crc', self::CRC);
        $app['config']->set('przelewy24.sandbox', true);
        $app['config']->set('przelewy24.url_return', 'https://shop.test/thank-you');
        $app['config']->set('przelewy24.notifications.verify_ip', false);
    }

    /**
     * Builds a correctly signed transaction notification payload.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function signedNotification(array $overrides = []): array
    {
        $payload = array_merge([
            'merchantId' => self::MERCHANT_ID,
            'posId' => self::MERCHANT_ID,
            'sessionId' => 'order-1001',
            'amount' => 12345,
            'originAmount' => 12345,
            'currency' => 'PLN',
            'orderId' => 300100200,
            'methodId' => 25,
            'statement' => 'p24-A1-B2-C3',
        ], $overrides);

        $payload['sign'] = hash('sha384', json_encode(
            [...array_intersect_key($payload, array_flip(['merchantId', 'posId', 'sessionId', 'amount', 'originAmount', 'currency', 'orderId', 'methodId', 'statement'])), 'crc' => self::CRC],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return $payload;
    }
}
