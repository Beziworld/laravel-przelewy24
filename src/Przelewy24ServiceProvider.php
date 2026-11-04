<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Przelewy24 client and the notification routes.
 */
final class Przelewy24ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/przelewy24.php', 'przelewy24');

        $this->app->singleton(Przelewy24Config::class, static function (Application $app): Przelewy24Config {
            /** @var array<string, mixed> $config */
            $config = $app->make(Config::class)->get('przelewy24', []);

            return Przelewy24Config::fromArray($config);
        });

        $this->app->singleton(Przelewy24Client::class, static fn(Application $app): Przelewy24Client => new Przelewy24Client(
            $app->make(HttpFactory::class),
            $app->make(Przelewy24Config::class),
            static fn(): ?string => self::notificationUrl($app),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/przelewy24.php' => $this->app->configPath('przelewy24.php'),
            ], 'przelewy24-config');
        }

        if ((bool) $this->app->make(Config::class)->get('przelewy24.notifications.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/przelewy24.php');
        }
    }

    /**
     * Absolute URL of the package notification route, when it is registered.
     */
    private static function notificationUrl(Application $app): ?string
    {
        $name = $app->make(Config::class)->get('przelewy24.notifications.transaction_route');

        if (! is_string($name) || ! $app->make(Router::class)->has($name)) {
            return null;
        }

        return $app->make(UrlGenerator::class)->route($name);
    }
}
