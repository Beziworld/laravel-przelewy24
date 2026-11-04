<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24;

use Beziworld\Przelewy24\Exceptions\InvalidConfiguration;

/**
 * Validated, immutable view of the "przelewy24" configuration file.
 */
final readonly class Przelewy24Config
{
    public const string PRODUCTION_HOST = 'https://secure.przelewy24.pl';

    public const string SANDBOX_HOST = 'https://sandbox.przelewy24.pl';

    public function __construct(
        public int $merchantId,
        public int $posId,
        public string $apiKey,
        public string $crc,
        public bool $sandbox = true,
        public string $currency = 'PLN',
        public string $country = 'PL',
        public string $language = 'pl',
        public ?string $urlReturn = null,
        public ?string $urlStatus = null,
        public int $timeout = 20,
        public int $connectTimeout = 5,
        public int $retries = 1,
        public int $retryDelay = 300,
    ) {}

    /**
     * @param array<string, mixed> $config
     *
     * @throws InvalidConfiguration When a required credential is missing or malformed.
     */
    public static function fromArray(array $config): self
    {
        $merchantId = self::positiveInt($config['merchant_id'] ?? null)
            ?? throw InvalidConfiguration::missing('merchant_id', 'PRZELEWY24_MERCHANT_ID');
        $posId = self::positiveInt($config['pos_id'] ?? null) ?? $merchantId;
        $apiKey = self::string($config['api_key'] ?? null)
            ?? throw InvalidConfiguration::missing('api_key', 'PRZELEWY24_API_KEY');
        $crc = self::string($config['crc'] ?? null)
            ?? throw InvalidConfiguration::missing('crc', 'PRZELEWY24_CRC');

        /** @var array<string, mixed> $http */
        $http = is_array($config['http'] ?? null) ? $config['http'] : [];

        return new self(
            merchantId: $merchantId,
            posId: $posId,
            apiKey: $apiKey,
            crc: $crc,
            sandbox: (bool) ($config['sandbox'] ?? true),
            currency: strtoupper(self::string($config['currency'] ?? null) ?? 'PLN'),
            country: strtoupper(self::string($config['country'] ?? null) ?? 'PL'),
            language: strtolower(self::string($config['language'] ?? null) ?? 'pl'),
            urlReturn: self::string($config['url_return'] ?? null),
            urlStatus: self::string($config['url_status'] ?? null),
            timeout: self::positiveInt($http['timeout'] ?? null) ?? 20,
            connectTimeout: self::positiveInt($http['connect_timeout'] ?? null) ?? 5,
            retries: max(0, is_numeric($http['retries'] ?? null) ? (int) $http['retries'] : 1),
            retryDelay: max(0, is_numeric($http['retry_delay'] ?? null) ? (int) $http['retry_delay'] : 300),
        );
    }

    /**
     * Host of the payment panel for the configured environment.
     */
    public function host(): string
    {
        return $this->sandbox ? self::SANDBOX_HOST : self::PRODUCTION_HOST;
    }

    public function apiUrl(): string
    {
        return $this->host() . '/api/v1';
    }

    private static function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        return null;
    }
}
