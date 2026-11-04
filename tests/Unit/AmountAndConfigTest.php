<?php

declare(strict_types=1);

use Beziworld\Przelewy24\Data\RefundItem;
use Beziworld\Przelewy24\Data\TransactionRequest;
use Beziworld\Przelewy24\Enums\Channel;
use Beziworld\Przelewy24\Exceptions\InvalidConfiguration;
use Beziworld\Przelewy24\Przelewy24Config;
use Beziworld\Przelewy24\Support\Amount;

it('converts decimal amounts to minor units without float errors', function (string|int|float $input, int $expected): void {
    expect(Amount::toMinor($input))->toBe($expected);
})->with([
    ['19.99', 1999],
    [19.99, 1999],
    ['0,5', 50],
    ['1 234.05', 123405],
    [12, 1200],
    ['7', 700],
]);

it('rejects invalid amounts', function (string|int $input): void {
    expect(fn() => Amount::toMinor($input))->toThrow(InvalidArgumentException::class);
})->with(['1.999', 'abc', '-5', -1]);

it('formats minor units as decimals', function (): void {
    expect(Amount::toDecimal(123405))->toBe('1234.05')
        ->and(Amount::toDecimal(5))->toBe('0.05')
        ->and(Amount::toDecimal(-150))->toBe('-1.50');
});

it('builds the configuration with sensible defaults', function (): void {
    $config = Przelewy24Config::fromArray(['merchant_id' => '11', 'api_key' => 'k', 'crc' => 'c', 'sandbox' => false]);

    expect($config->posId)->toBe(11)
        ->and($config->apiUrl())->toBe('https://secure.przelewy24.pl/api/v1')
        ->and($config->currency)->toBe('PLN');
});

it('requires credentials', function (array $config, string $env): void {
    expect(fn() => Przelewy24Config::fromArray($config))->toThrow(InvalidConfiguration::class, $env);
})->with([
    [[], 'PRZELEWY24_MERCHANT_ID'],
    [['merchant_id' => 1], 'PRZELEWY24_API_KEY'],
    [['merchant_id' => 1, 'api_key' => 'k'], 'PRZELEWY24_CRC'],
]);

it('combines payment channels into a bitmask', function (): void {
    expect(Channel::combine(Channel::Cards, Channel::Blik))->toBe(8193);
});

it('validates transaction and refund input', function (): void {
    expect(fn() => new TransactionRequest('', 100, 'x', 'a@b.c'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new TransactionRequest('s', 0, 'x', 'a@b.c'))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new TransactionRequest('s', 1, 'x', 'a@b.c', timeLimit: 100))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new RefundItem(1, 's', 0))->toThrow(InvalidArgumentException::class)
        ->and(fn() => new RefundItem(1, 's', 1, str_repeat('x', 36)))->toThrow(InvalidArgumentException::class);
});
