<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Logger;
use App\Core\Middleware;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/**
 * Limitation de débit par IP.
 *
 * Deux quotas : un global sur toutes les requêtes, et un second, plus
 * strict, sur les actions sensibles (connexion, checkout).
 */
final class RateLimitMiddleware extends Middleware
{
    protected function process(Request $request): ?Response
    {
        $max    = (int) config('security.rate_limit.max', 60);
        $window = (int) config('security.rate_limit.window', 60);

        if (RateLimiter::attempt('global:' . $request->path(), $request->ip(), $max, $window)) {
            return null;
        }

        $retryAfter = RateLimiter::availableIn('global:' . $request->path(), $request->ip(), $window);

        Logger::warning('Rate limit exceeded on ' . $request->method() . ' ' . $request->path());

        $response = $request->wantsJson()
            ? Response::json(['ok' => false, 'error' => __('errors.too_many_requests')], 429)
            : view('errors/error', [
                'status'  => 429,
                'message' => __('errors.too_many_requests'),
            ], 'layouts/shop', 429);

        return $response->setHeader('Retry-After', (string) max(1, $retryAfter));
    }
}
