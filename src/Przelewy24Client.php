<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24;

use Beziworld\Przelewy24\Data\PaymentMethod;
use Beziworld\Przelewy24\Data\RefundItem;
use Beziworld\Przelewy24\Data\RefundResult;
use Beziworld\Przelewy24\Data\RegisteredTransaction;
use Beziworld\Przelewy24\Data\Transaction;
use Beziworld\Przelewy24\Data\TransactionNotification;
use Beziworld\Przelewy24\Data\TransactionRequest;
use Beziworld\Przelewy24\Exceptions\ApiException;
use Beziworld\Przelewy24\Exceptions\InvalidConfiguration;
use Beziworld\Przelewy24\Support\Arr;
use Beziworld\Przelewy24\Support\Signature;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Throwable;

/**
 * Client of the Przelewy24 REST API v1.
 */
class Przelewy24Client
{
    /**
     * @param (Closure(): ?string)|null $defaultStatusUrl Resolves the notification URL used when neither the request nor the configuration defines one.
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Przelewy24Config $config,
        private readonly ?Closure $defaultStatusUrl = null,
    ) {}

    public function config(): Przelewy24Config
    {
        return $this->config;
    }

    /**
     * Checks that the credentials are accepted by the API.
     */
    public function testAccess(): bool
    {
        try {
            $payload = $this->send('GET', '/testAccess');
        } catch (ApiException $exception) {
            if ($exception->httpStatus() === 401) {
                return false;
            }

            throw $exception;
        }

        return ($payload['data'] ?? null) === true;
    }

    /**
     * Registers a transaction and returns the token with the payment page URL.
     *
     * @throws ApiException When Przelewy24 rejects the registration.
     * @throws InvalidConfiguration When no return URL is available.
     */
    public function register(TransactionRequest $request): RegisteredTransaction
    {
        $currency = strtoupper($request->currency ?? $this->config->currency);
        $urlReturn = $request->urlReturn ?? $this->config->urlReturn ?? throw InvalidConfiguration::missingReturnUrl();
        $urlStatus = $request->urlStatus ?? $this->config->urlStatus ?? ($this->defaultStatusUrl)?->__invoke();

        $body = [
            'merchantId' => $this->config->merchantId,
            'posId' => $this->config->posId,
            'sessionId' => $request->sessionId,
            'amount' => $request->amount,
            'currency' => $currency,
            'description' => $request->description,
            'email' => $request->email,
            'country' => strtoupper($request->country ?? $this->config->country),
            'language' => strtolower($request->language ?? $this->config->language),
            'urlReturn' => $urlReturn,
            'sign' => Signature::forRegistration($request->sessionId, $this->config->merchantId, $request->amount, $currency, $this->config->crc),
        ];

        if ($urlStatus !== null) {
            $body['urlStatus'] = $urlStatus;
        }

        $payload = $this->send('POST', '/transaction/register', $body + $request->optionalFields());
        $token = Arr::string(Arr::array($payload, 'data'), 'token') ?? throw ApiException::unexpectedResponse(200);

        return new RegisteredTransaction($request->sessionId, $token, $this->redirectUrl($token));
    }

    /**
     * Confirms a paid transaction. Until it is verified the money is not settled to the merchant.
     *
     * @throws ApiException When Przelewy24 does not confirm the transaction.
     */
    public function verify(string $sessionId, int $orderId, int $amount, ?string $currency = null): void
    {
        $currency = strtoupper($currency ?? $this->config->currency);

        $payload = $this->send('PUT', '/transaction/verify', [
            'merchantId' => $this->config->merchantId,
            'posId' => $this->config->posId,
            'sessionId' => $sessionId,
            'amount' => $amount,
            'currency' => $currency,
            'orderId' => $orderId,
            'sign' => Signature::forVerification($sessionId, $orderId, $amount, $currency, $this->config->crc),
        ]);

        if (Arr::string(Arr::array($payload, 'data'), 'status') !== 'success') {
            throw new ApiException('Przelewy24 did not confirm the transaction verification.', 200, $payload);
        }
    }

    /**
     * Confirms the transaction described by a received notification.
     */
    public function verifyNotification(TransactionNotification $notification): void
    {
        $this->verify($notification->sessionId, $notification->orderId, $notification->amount, $notification->currency);
    }

    /**
     * Fetches transaction details by the merchant session id.
     */
    public function transaction(string $sessionId): Transaction
    {
        $payload = $this->send('GET', '/transaction/by/sessionId/' . rawurlencode($sessionId));

        return Transaction::fromArray(Arr::array($payload, 'data'));
    }

    /**
     * Requests refunds of paid transactions.
     *
     * @param list<RefundItem> $items
     * @param string $requestId Unique identifier of this refund request (max. 45 characters).
     * @param string $refundsUuid Identifier echoed in refund notifications (max. 35 characters).
     * @return list<RefundResult>
     *
     * @throws ApiException When the request is rejected; for HTTP 409 the per-item reasons are in payload().
     */
    public function refund(array $items, string $requestId, string $refundsUuid, ?string $urlStatus = null): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('At least one refund item is required.');
        }

        $body = [
            'requestId' => $requestId,
            'refunds' => array_map(static fn(RefundItem $item): array => $item->toArray(), $items),
            'refundsUuid' => $refundsUuid,
        ];

        if ($urlStatus !== null) {
            $body['urlStatus'] = $urlStatus;
        }

        $results = [];

        foreach (Arr::array($this->send('POST', '/transaction/refund', $body), 'data') as $item) {
            if (is_array($item)) {
                $results[] = RefundResult::fromArray($item);
            }
        }

        return $results;
    }

    /**
     * Lists payment methods available for the merchant.
     *
     * @param string $language "pl" or "en".
     * @return list<PaymentMethod>
     */
    public function paymentMethods(string $language = 'pl', ?int $amount = null, ?string $currency = null): array
    {
        $query = array_filter(
            ['amount' => $amount, 'currency' => $currency !== null ? strtoupper($currency) : null],
            static fn(mixed $value): bool => $value !== null,
        );

        $methods = [];

        foreach (Arr::array($this->send('GET', '/payment/methods/' . rawurlencode($language), $query), 'data') as $item) {
            if (is_array($item)) {
                $methods[] = PaymentMethod::fromArray($item);
            }
        }

        return $methods;
    }

    /**
     * Builds the payment page URL for a registered transaction token.
     */
    public function redirectUrl(string $token): string
    {
        return $this->config->host() . '/trnRequest/' . rawurlencode($token);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<array-key, mixed>
     */
    private function send(string $method, string $path, array $data = []): array
    {
        try {
            $response = $this->request()->send($method, ltrim($path, '/'), $method === 'GET'
                ? ['query' => $data]
                : ['json' => $data]);
        } catch (ConnectionException $exception) {
            throw ApiException::transport($exception);
        }

        return $this->decode($response);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            throw $response->failed()
                ? ApiException::fromResponse([], $response->status())
                : ApiException::unexpectedResponse($response->status());
        }

        if ($response->failed()) {
            throw ApiException::fromResponse($payload, $response->status());
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->createPendingRequest()
            ->baseUrl($this->config->apiUrl() . '/')
            ->withBasicAuth((string) $this->config->posId, $this->config->apiKey)
            ->acceptJson()
            ->withUserAgent('beziworld-laravel-przelewy24/1.0')
            ->timeout($this->config->timeout)
            ->connectTimeout($this->config->connectTimeout);

        if ($this->config->retries > 0) {
            $request->retry(
                $this->config->retries + 1,
                $this->config->retryDelay,
                self::isSafeToRetry(...),
                throw: false,
            );
        }

        return $request;
    }

    /**
     * Retries only idempotent requests that failed at the transport level.
     *
     * A timed-out POST may already have been processed by Przelewy24, so
     * registrations and refunds are never repeated automatically. Verification
     * (PUT) is idempotent and safe to repeat.
     */
    private static function isSafeToRetry(Throwable $exception, mixed $request = null, ?string $method = null): bool
    {
        return $exception instanceof ConnectionException && in_array($method, ['GET', 'HEAD', 'PUT'], true);
    }
}
