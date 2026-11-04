<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Values from the Przelewy24 panel (Moje dane > Dane API i konfiguracja):
    | merchant id, shop (POS) id, CRC key and the API key ("Klucz do
    | raportów"). The POS id equals the merchant id unless the account has
    | several shops.
    |
    */

    'merchant_id' => env('PRZELEWY24_MERCHANT_ID'),

    'pos_id' => env('PRZELEWY24_POS_ID'),

    'api_key' => env('PRZELEWY24_API_KEY'),

    'crc' => env('PRZELEWY24_CRC'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Sandbox uses https://sandbox.przelewy24.pl with credentials of the
    | sandbox account, production uses https://secure.przelewy24.pl.
    |
    */

    'sandbox' => (bool) env('PRZELEWY24_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Transaction defaults
    |--------------------------------------------------------------------------
    |
    | "url_return" is where the customer lands after the payment. When
    | "url_status" is empty and notifications are enabled, the URL of the
    | package notification route is used.
    |
    */

    'currency' => env('PRZELEWY24_CURRENCY', 'PLN'),

    'country' => env('PRZELEWY24_COUNTRY', 'PL'),

    'language' => env('PRZELEWY24_LANGUAGE', 'pl'),

    'url_return' => env('PRZELEWY24_URL_RETURN'),

    'url_status' => env('PRZELEWY24_URL_STATUS'),

    /*
    |--------------------------------------------------------------------------
    | HTTP client
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => (int) env('PRZELEWY24_TIMEOUT', 20),
        'connect_timeout' => (int) env('PRZELEWY24_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('PRZELEWY24_RETRIES', 1),
        'retry_delay' => (int) env('PRZELEWY24_RETRY_DELAY', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Routes receiving transaction and refund notifications. Every
    | notification is checked against its SHA-384 signature and, when
    | "verify_ip" is enabled, against the published Przelewy24 addresses.
    |
    | With "auto_verify" enabled the transaction route confirms the payment
    | through PUT /transaction/verify and dispatches TransactionVerified.
    | Disable it to verify manually in a TransactionNotificationReceived
    | listener, e.g. after comparing the amount with the order.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('PRZELEWY24_NOTIFICATIONS_ENABLED', true),
        'auto_verify' => (bool) env('PRZELEWY24_AUTO_VERIFY', true),
        'middleware' => ['api'],
        'transaction_path' => env('PRZELEWY24_NOTIFICATION_PATH', 'przelewy24/notification'),
        'transaction_route' => 'przelewy24.notification',
        'refund_path' => env('PRZELEWY24_REFUND_NOTIFICATION_PATH', 'przelewy24/refund-notification'),
        'refund_route' => 'przelewy24.refund-notification',
        'verify_ip' => (bool) env('PRZELEWY24_VERIFY_IP', true),
        'allowed_ips' => [
            '5.252.202.254',
            '5.252.202.255',
            '20.215.81.124',
            '20.215.183.48/28',
            '91.220.177.0/24',
            '134.112.88.8/29',
            '193.178.213.0/24',
        ],
    ],

];
