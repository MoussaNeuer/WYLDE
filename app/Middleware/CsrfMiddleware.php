<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Vérifie le jeton CSRF sur toute méthode mutante
 * (POST, PUT, PATCH, DELETE) — cf. §13.3.
 */
final class CsrfMiddleware extends Middleware
{
    /** @var array<int, string> */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    protected function process(Request $request): ?Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return null;
        }

        if ($this->isExempt($request)) {
            return null;
        }

        if (Csrf::verifyRequest($request)) {
            return null;
        }

        Logger::warning('CSRF rejected on ' . $request->method() . ' ' . $request->path());

        if ($request->wantsJson()) {
            // 403 et non le 419 « maison » : mod_fcgid ne connaît pas les
            // codes hors table Apache et réécrit alors la réponse en 500.
            return Response::json(['ok' => false, 'error' => __('errors.csrf')], 403);
        }

        Session::flashError(__('errors.csrf'));

        $referer = $request->header('Referer');

        return Response::redirect(
            is_string($referer) && $referer !== '' ? $referer : url('/')
        );
    }

    private function isExempt(Request $request): bool
    {
        /** @var array<int, string> $except */
        $except = (array) Config::get('security.csrf.except_paths', []);

        foreach ($except as $pattern) {
            if ($request->path() === $pattern) {
                return true;
            }
        }

        return false;
    }
}
