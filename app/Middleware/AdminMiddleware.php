<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Autorise l'accès au back-office.
 *
 * Le rôle est vérifié côté serveur, sur chaque requête : masquer un
 * bouton dans l'interface ne constitue pas une protection (§13.2).
 */
final class AdminMiddleware extends Middleware
{
    protected function process(Request $request): ?Response
    {
        if (!Auth::check()) {
            $this->storeIntendedUrl($request);

            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => __('errors.unauthorized')], 401);
            }

            Session::flashError(__('errors.unauthorized'));

            return Response::redirect($this->adminLoginUrl());
        }

        if (Auth::isStaff()) {
            return null;
        }

        // Authentifié mais sans rôle d'administration.
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => __('errors.forbidden')], 403);
        }

        Session::flashError(__('errors.forbidden'));

        return Response::redirect($this->clientLoginUrl());
    }
}
