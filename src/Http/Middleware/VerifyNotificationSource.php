<?php

declare(strict_types=1);

namespace Beziworld\Przelewy24\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Accepts notifications only from the addresses published by Przelewy24.
 *
 * The check relies on Request::ip(); behind a load balancer configure the
 * trusted proxies of the application, otherwise the proxy address is seen.
 */
final class VerifyNotificationSource
{
    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) $this->config->get('przelewy24.notifications.verify_ip', true)) {
            return $next($request);
        }

        $allowed = $this->config->get('przelewy24.notifications.allowed_ips', []);
        $ranges = is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : [];
        $ip = $request->ip();

        if ($ip === null || ! IpUtils::checkIp($ip, $ranges)) {
            throw new AccessDeniedHttpException('Notification received from an address not published by Przelewy24.');
        }

        return $next($request);
    }
}
