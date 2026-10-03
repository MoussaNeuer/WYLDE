<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Interdit l'accès aux pages de connexion à un utilisateur déjà
 * authentifié.
 */
final class GuestMiddleware extends Middleware
{
    protected function process(Request $request): ?Response
    {
        if (!Auth::check()) {
            return null;
        }

        $target = str_starts_with($request->path(), '/admin')
            ? url('/admin')
            : url('/account');

        return Response::redirect($target);
    }
}
