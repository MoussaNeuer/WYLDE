<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\SecurityHeaders;

/**
 * Envoie les en-têtes de sécurité.
 *
 * Ils sont déjà émis par public/index.php pour toutes les requêtes ;
 * ce middleware permet de les réappliquer sur un périmètre précis,
 * notamment pour durcir la réponse du back-office.
 */
final class SecurityHeadersMiddleware extends Middleware
{
    protected function process(Request $request): ?Response
    {
        SecurityHeaders::send();

        return null;
    }
}
