# Laravel Przelewy24

Integracja Laravela z REST API v1 [Przelewy24](https://www.przelewy24.pl): rejestracja transakcji, podpisane
notyfikacje z automatyczną weryfikacją, zwroty i lista metod płatności.

[English documentation](README.md)

## Możliwości

- Rejestracja transakcji ze wszystkimi polami API (koszyk, kanały, limit czasu, dane klienta)
- Endpoint notyfikacji: sprawdza podpis SHA-384, merchanta i adres IP, potwierdza płatność
  (`PUT /transaction/verify`) i wysyła event `TransactionVerified`
- Opcjonalna ręczna weryfikacja dla własnej kontroli kwoty
- Zwroty i podpisane notyfikacje o zwrotach
- Podgląd transakcji, metody płatności, `testAccess`
- Kwoty w groszach jako int oraz helper `Amount` bez błędów zaokrągleń float
- Sandbox i produkcja, PHP 8.3+, Laravel 12 i 13, PHPStan level 8

## Instalacja

```bash
composer require beziworld/laravel-przelewy24
php artisan vendor:publish --tag=przelewy24-config   # opcjonalnie
```

```dotenv
PRZELEWY24_MERCHANT_ID=123456
PRZELEWY24_POS_ID=123456              # zwykle równy merchant id
PRZELEWY24_API_KEY=klucz-do-raportow
PRZELEWY24_CRC=klucz-crc
PRZELEWY24_SANDBOX=true
PRZELEWY24_URL_RETURN=https://sklep.pl/platnosc/powrot
```

Dane znajdziesz w panelu Przelewy24: *Moje dane > Dane API i konfiguracja*. Sandbox wymaga osobnego konta.

## Przepływ płatności

Płatność **nie** jest rozliczona, dopóki nie zostanie zweryfikowana. Nigdy nie oznaczaj zamówienia jako
opłaconego na stronie powrotu. Pakiet sam rejestruje trasę notyfikacji i przekazuje jej adres jako `urlStatus`.

### 1. Rejestracja i przekierowanie

```php
use Beziworld\Przelewy24\Data\TransactionRequest;
use Beziworld\Przelewy24\Facades\Przelewy24;
use Beziworld\Przelewy24\Support\Amount;

$transaction = Przelewy24::register(new TransactionRequest(
    sessionId: $order->uuid,                     // unikalny dla każdej próby płatności
    amount: Amount::toMinor($order->total),       // "123.45" → 12345
    description: "Zamówienie {$order->number}",
    email: $order->email,
    client: $order->customer_name,
    urlReturn: route('orders.show', $order),
));

$order->update(['p24_session_id' => $transaction->sessionId]);

return redirect()->away($transaction->redirectUrl);
```

### 2. Oznaczenie zamówienia jako opłaconego

```php
use Beziworld\Przelewy24\Events\TransactionVerified;

Event::listen(function (TransactionVerified $event) {
    $n = $event->notification;
    $order = Order::where('p24_session_id', $n->sessionId)->firstOrFail();

    if ($order->isPaid()) {
        return;                                  // notyfikacje mogą się powtarzać
    }

    if ($n->amount === Amount::toMinor($order->total) && $n->currency === 'PLN') {
        $order->markAsPaid(p24OrderId: $n->orderId);
    }
});
```

Gdy weryfikacja się nie powiedzie, pakiet wysyła `TransactionVerificationFailed` i odpowiada HTTP 500, więc
Przelewy24 ponowi notyfikację.

### Ręczna weryfikacja

Ustaw `PRZELEWY24_AUTO_VERIFY=false` i w listenerze `TransactionNotificationReceived` wywołaj
`Przelewy24::verifyNotification($event->notification)` po własnych kontrolach.

## Zwroty

```php
use Beziworld\Przelewy24\Data\RefundItem;

Przelewy24::refund(
    items: [new RefundItem($order->p24_order_id, $order->p24_session_id, 5000, 'Zwrot towaru')],
    requestId: (string) Str::uuid(),
    refundsUuid: "refund-{$order->id}",
    urlStatus: route('przelewy24.refund-notification'),
);
```

Wynik zwrotu przychodzi jako event `RefundNotificationReceived` (`$event->notification->isCompleted()`).

## Inne wywołania

```php
Przelewy24::testAccess();                          // sprawdzenie danych dostępowych
Przelewy24::transaction($sessionId);               // status, orderId, kwota, prowizja
Przelewy24::paymentMethods('pl', amount: 12345);   // metody z logotypami do własnego wyboru
```

## Bezpieczeństwo

- Każda notyfikacja musi mieć poprawny podpis SHA-384 (klucz CRC) i być adresowana do Twojego merchanta i POS.
- Notyfikacje są przyjmowane tylko z adresów IP publikowanych przez Przelewy24. Za load balancerem skonfiguruj
  trusted proxies w Laravelu.
- Trasy korzystają z grupy middleware `api` (bez CSRF).
- Podpis jest sprawdzany na surowej treści żądania, więc middleware typu `TrimStrings` go nie psuje.
- `TransactionNotificationReceived` przychodzi **przed** weryfikacją. Nie oznaczaj tam zamówienia jako opłaconego
  (chyba że weryfikujesz ręcznie); używaj `TransactionVerified`.
- Przelewy24 ponawia notyfikacje do skutku, więc listenery muszą być idempotentne (klucz: `sessionId` / `orderId`).
- Przy rejestracji transakcji z kolejki lub konsoli adres notyfikacji powstaje z `APP_URL`. Ustaw go poprawnie albo
  podaj `PRZELEWY24_URL_STATUS`.
- Rejestracje i zwroty nie są ponawiane automatycznie po timeoucie; ponawiane są tylko żądania idempotentne.

## Autor

Pakiet tworzy i rozwija **Kamil Ćwiertnia**, [beziworld.eu](https://beziworld.eu).

- E-mail: [biuro@beziworld.eu](mailto:biuro@beziworld.eu)
- Zgłoszenia błędów: [GitHub issues](https://github.com/beziworld/laravel-przelewy24/issues)
- Podatności bezpieczeństwa: [SECURITY.md](SECURITY.md)

Potrzebujesz wdrożenia płatności w sklepie albo projektu w Laravelu? Zapraszam do kontaktu przez
[beziworld.eu](https://beziworld.eu).

### Inne pakiety autora

- [beziworld/laravel-tpay](https://github.com/beziworld/laravel-tpay): płatności Tpay i BLIK
- [beziworld/laravel-smsapi](https://github.com/beziworld/laravel-smsapi): SMS przez SMSAPI
- [beziworld/laravel-directadmin](https://github.com/beziworld/laravel-directadmin): API panelu DirectAdmin

## Licencja

MIT. Pakiet nie jest powiązany z PayPro S.A. / Przelewy24.
