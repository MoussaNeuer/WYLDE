<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Exige une session authentifiée.
 *
 * Le contrôle du rôle est fait par AdminMiddleware : ici on ne vérifie
 * que la présence d'un compte valide.
 */
final class AuthMiddleware extends Middleware
{
    protected function process(Request $request): ?Response
    {
        if (Auth::check()) {
            return null;
        }

        $this->storeIntendedUrl($request);

        if ($request->wantsJson()) {
            return Response::json(
                ['ok' => false, 'error' => __('errors.unauthorized')],
                401
            );
        }

        Session::flashError(__('errors.unauthorized'));

        return Response::redirect($this->clientLoginUrl());
    }
}
