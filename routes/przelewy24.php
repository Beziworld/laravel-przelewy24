<?php

declare(strict_types=1);

use Beziworld\Przelewy24\Http\Controllers\RefundNotificationController;
use Beziworld\Przelewy24\Http\Controllers\TransactionNotificationController;
use Beziworld\Przelewy24\Http\Middleware\VerifyNotificationSource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

$configured = Config::get('przelewy24.notifications.middleware', []);
$middleware = [...(is_array($configured) ? $configured : []), VerifyNotificationSource::class];

Route::post((string) Config::get('przelewy24.notifications.transaction_path', 'przelewy24/notification'), TransactionNotificationController::class)
    ->middleware($middleware)
    ->name((string) Config::get('przelewy24.notifications.transaction_route', 'przelewy24.notification'));

Route::post((string) Config::get('przelewy24.notifications.refund_path', 'przelewy24/refund-notification'), RefundNotificationController::class)
    ->middleware($middleware)
    ->name((string) Config::get('przelewy24.notifications.refund_route', 'przelewy24.refund-notification'));
