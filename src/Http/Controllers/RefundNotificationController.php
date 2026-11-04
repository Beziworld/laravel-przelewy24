<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Http\Controllers;

use Beziworld\Przelewy24\Data\RefundNotification;
use Beziworld\Przelewy24\Events\RefundNotificationReceived;
use Beziworld\Przelewy24\Exceptions\InvalidNotification;
use Beziworld\Przelewy24\Przelewy24Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Receives refund result notifications and dispatches RefundNotificationReceived.
 */
final class RefundNotificationController
{
    public function __construct(
        private readonly Przelewy24Config $config,
        private readonly Dispatcher $events,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $notification = RefundNotification::fromArray(InvalidNotification::decode($request->getContent()));

            if ($notification->merchantId !== $this->config->merchantId) {
                throw InvalidNotification::foreignMerchant($notification->merchantId);
            }

            if (! $notification->hasValidSignature($this->config->crc)) {
                throw InvalidNotification::invalidSignature();
            }
        } catch (InvalidNotification $exception) {
            $this->logger->warning('Rejected Przelewy24 refund notification: ' . $exception->getMessage(), ['ip' => $request->ip()]);

            return new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 400);
        }

        $this->events->dispatch(new RefundNotificationReceived($notification));

        return new JsonResponse(['status' => 'received']);
    }
}
