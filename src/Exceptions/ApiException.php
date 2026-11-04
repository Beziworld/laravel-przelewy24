<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Exceptions;

use Throwable;

/**
 * Raised when Przelewy24 rejects a request or cannot be reached.
 */
final class ApiException extends Przelewy24Exception
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly array $payload = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public static function fromResponse(array $payload, int $httpStatus): self
    {
        $error = $payload['error'] ?? null;

        $description = match (true) {
            is_string($error) && $error !== '' => $error,
            is_array($error) => self::describeItems($error),
            default => sprintf('request failed with HTTP status %d', $httpStatus),
        };

        return new self('Przelewy24 error: ' . $description, $httpStatus, $payload);
    }

    public static function transport(Throwable $previous): self
    {
        return new self('Could not connect to Przelewy24: ' . $previous->getMessage(), previous: $previous);
    }

    public static function unexpectedResponse(int $httpStatus): self
    {
        return new self('Przelewy24 returned an unexpected response.', $httpStatus);
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private static function describeItems(array $items): string
    {
        $messages = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['message']) && is_string($item['message'])) {
                $messages[] = $item['message'];
            }
        }

        return $messages === [] ? 'request rejected' : implode('; ', array_unique($messages));
    }
}
