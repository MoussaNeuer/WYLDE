<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

/**
 * Résolution des middlewares par alias, avec cache d'instances.
 */
final class MiddlewareRegistry
{
    /** @var array<string, class-string> */
    private const ALIASES = [
        'auth'        => AuthMiddleware::class,
        'admin'       => AdminMiddleware::class,
        'guest'       => GuestMiddleware::class,
        'csrf'        => CsrfMiddleware::class,
        'rate_limit'  => RateLimitMiddleware::class,
        'security'    => SecurityHeadersMiddleware::class,
    ];

    /** @var array<string, object> */
    private static array $instances = [];

    public static function resolve(string $middleware): object
    {
        $class = self::ALIASES[$middleware] ?? $middleware;

        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }

        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware inconnu : {$middleware} ({$class})");
        }

        return self::$instances[$class] = new $class();
    }

    /** @return array<int, string> */
    public static function aliases(): array
    {
        return array_keys(self::ALIASES);
    }
}
