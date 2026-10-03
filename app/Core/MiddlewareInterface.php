<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contrat commun des middlewares.
 *
 * handle() renvoie soit null pour laisser la requête poursuivre,
 * soit une Response pour interrompre la chaîne (redirection, refus).
 */
interface MiddlewareInterface
{
    public function handle(Request $request): ?Response;
}
