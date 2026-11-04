<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Http\Controllers;

use Beziworld\Przelewy24\Data\TransactionNotification;
use Beziworld\Przelewy24\Events\TransactionNotificationReceived;
use Beziworld\Przelewy24\Events\TransactionVerificationFailed;
use Beziworld\Przelewy24\Events\TransactionVerified;
use Beziworld\Przelewy24\Exceptions\ApiException;
use Beziworld\Przelewy24\Exceptions\InvalidNotification;
use Beziworld\Przelewy24\Przelewy24Client;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

/**
 * Receives payment notifications sent to urlStatus.
 *
 * Flow: parse the JSON body, check that it is addressed to this merchant,
 * check the SHA-384 signature, dispatch TransactionNotificationReceived and,
 * when auto verification is enabled, confirm the payment through the API and
 * dispatch TransactionVerified. A failed verification answers HTTP 500 so
 * that Przelewy24 repeats the notification.
 */
final class TransactionNotificationController
{
    public function __construct(
        private readonly Przelewy24Client $client,
        private readonly Dispatcher $events,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $notification = $this->authenticate($request);
        } catch (InvalidNotification $exception) {
            $this->logger->warning('Rejected Przelewy24 notification: ' . $exception->getMessage(), ['ip' => $request->ip()]);

            return new JsonResponse(['status' => 'error', 'message' => $exception->getMessage()], 400);
        }

        $this->events->dispatch(new TransactionNotificationReceived($notification));

        if (! (bool) $this->config->get('przelewy24.notifications.auto_verify', true)) {
            return new JsonResponse(['status' => 'received']);
        }

        try {
            $this->client->verifyNotification($notification);
        } catch (ApiException $exception) {
            $this->logger->error('Przelewy24 transaction verification failed: ' . $exception->getMessage(), [
                'sessionId' => $notification->sessionId,
                'orderId' => $notification->orderId,
            ]);
            $this->events->dispatch(new TransactionVerificationFailed($notification, $exception));

            return new JsonResponse(['status' => 'error', 'message' => 'Verification failed.'], 500);
        }

        $this->events->dispatch(new TransactionVerified($notification));

        return new JsonResponse(['status' => 'verified']);
    }

    /**
     * @throws InvalidNotification
     */
    private function authenticate(Request $request): TransactionNotification
    {
        $notification = TransactionNotification::fromArray(InvalidNotification::decode($request->getContent()));
        $config = $this->client->config();

        if ($notification->merchantId !== $config->merchantId || $notification->posId !== $config->posId) {
            throw InvalidNotification::foreignMerchant($notification->merchantId, $notification->posId);
        }

        if (! $notification->hasValidSignature($config->crc)) {
            throw InvalidNotification::invalidSignature();
        }

        return $notification;
    }
}
